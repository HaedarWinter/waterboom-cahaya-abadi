<?php
/**
 * Integrity Check — Invarian Keuangan & Status
 * Jalankan: php scripts/integrity_check.php
 * 
 * Memeriksa konsistensi data booking, pembayaran, ledger, dan kuota.
 * Exit 0 bila semua lolos; exit 1 bila ada pelanggaran.
 */
declare(strict_types=1);

require_once __DIR__ . '/../tests/guard.php';
require_test_database_guard('SCRIPT: BLOCKED (guard rejected configuration)');

require __DIR__ . '/../app/init.php';

$db = \App\Core\Database::getInstance();
$conn = $db->getConnection();
require_test_database_connection($conn, 'SCRIPT: BLOCKED (guard rejected connection)');
$violations = [];

function check(string $name, string $sql): void {
    global $conn, $violations;
    $result = $conn->query($sql);
    if (!$result) {
        $violations[] = "$name: query gagal — " . $conn->error;
        return;
    }
    $row = $result->fetch_assoc();
    $count = (int)($row['violations'] ?? $row['c'] ?? 0);
    if ($count > 0) {
        $violations[] = "$name: $count pelanggaran";
    }
}

echo "Memeriksa invarian sistem...\n\n";

// ─── 1. ORPHAN REFERENCES ────────────────────────────────────────────
check(
    'Orphan booking_status_history',
    "SELECT COUNT(*) as violations FROM booking_status_history h 
     WHERE NOT EXISTS (SELECT 1 FROM reservasi r WHERE r.id = h.booking_id)"
);

check(
    'Orphan payments',
    "SELECT COUNT(*) as violations FROM payments p 
     WHERE NOT EXISTS (SELECT 1 FROM reservasi r WHERE r.id = p.booking_id)"
);

check(
    'Orphan refunds',
    "SELECT COUNT(*) as violations FROM refunds rf 
     WHERE NOT EXISTS (SELECT 1 FROM reservasi r WHERE r.id = rf.booking_id)"
);

// ─── 2. PAYMENT ↔ LEDGER KONSISTENSI ─────────────────────────────────
check(
    'Payment TERVERIFIKASI tanpa ledger',
    "SELECT COUNT(*) as violations FROM payments p 
     WHERE p.status = 'TERVERIFIKASI' 
       AND NOT EXISTS (
           SELECT 1 FROM ledger_entries le 
           WHERE le.source_type = 'PAYMENT' AND le.source_id = p.id
       )"
);

check(
    'Ledger payment orphan',
    "SELECT COUNT(*) as violations FROM ledger_entries le
     WHERE le.source_type = 'PAYMENT'
       AND NOT EXISTS (
           SELECT 1 FROM payments p WHERE p.id = le.source_id AND p.status = 'TERVERIFIKASI'
       )"
);

check(
    'Payment TERVERIFIKASI dengan >1 ledger TIKET',
    "SELECT COUNT(*) as violations FROM payments p
     WHERE p.status = 'TERVERIFIKASI'
       AND (SELECT COUNT(*) FROM ledger_entries le 
            WHERE le.source_type = 'PAYMENT' AND le.source_id = p.id AND le.category = 'TIKET'
           ) > 1"
);

// ─── 3. REFUND ↔ LEDGER KONSISTENSI ──────────────────────────────────
check(
    'Refund DIREFUND tanpa ledger OUT',
    "SELECT COUNT(*) as violations FROM refunds rf
     WHERE rf.status = 'DIREFUND'
       AND NOT EXISTS (
           SELECT 1 FROM ledger_entries le
           WHERE le.source_type = 'REFUND' AND le.source_id = rf.id AND le.direction = 'OUT'
       )"
);

// ─── 4. KUOTA KONSISTENSI ────────────────────────────────────────────
// Kuota terisi harus sama dengan jumlah booking aktif (tidak terminal) pada tanggal tersebut.
// Terminal: SELESAI, DIREFUND, KEDALUWARSA, DIBATALKAN, TIDAK_HADIR.
check(
    'Kuota terisi tidak sesuai dengan booking aktif',
    "SELECT COUNT(*) as violations FROM (
        SELECT kh.tanggal, kh.terisi, 
               COALESCE(SUM(r.jumlah_dewasa + r.jumlah_anak), 0) as actual
        FROM kuota_harian kh
        LEFT JOIN reservasi r ON r.tanggal_kunjungan = kh.tanggal
            AND r.status NOT IN ('SELESAI', 'DIREFUND', 'KEDALUWARSA', 'DIBATALKAN', 'TIDAK_HADIR')
        GROUP BY kh.tanggal, kh.terisi
        HAVING kh.terisi != actual
    ) AS diff"
);

// ─── 5. SALDO LEDGER ─────────────────────────────────────────────────
// Saldo per akun kas = SUM(amount WHERE direction=IN) - SUM(amount WHERE direction=OUT).
// Cek apakah ada anomali besar (selisih > 0 setelah dikurangi). Ini lebih ke sanity check.
check(
    'Saldo ledger negatif per akun (kas defisit mencurigakan)',
    "SELECT COUNT(*) as violations FROM (
        SELECT ca.id, ca.name,
               COALESCE(SUM(CASE WHEN le.direction='IN' THEN le.amount ELSE 0 END), 0) as total_in,
               COALESCE(SUM(CASE WHEN le.direction='OUT' THEN le.amount ELSE 0 END), 0) as total_out,
               (COALESCE(SUM(CASE WHEN le.direction='IN' THEN le.amount ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN le.direction='OUT' THEN le.amount ELSE 0 END), 0)) as balance
        FROM cash_accounts ca
        LEFT JOIN ledger_entries le ON le.cash_account_id = ca.id
        WHERE ca.is_active = 1
        GROUP BY ca.id, ca.name
        HAVING balance < 0
    ) AS neg"
);

// ─── 6. REKONSILIASI APPEND-ONLY ───────────────────────────────────────
check(
    'Rekonsiliasi tanpa ledger entry',
    "SELECT COUNT(*) as violations FROM ledger_reconciliations lr
     WHERE NOT EXISTS (
         SELECT 1 FROM ledger_entries le WHERE le.id = lr.ledger_entry_id
     )"
);

// ─── 7. DAILY CLOSING KONSISTENSI ────────────────────────────────────
// system_balance di daily_closings harus = SUM(ledger pada tanggal <= closing_date).
// Check apakah ada closing dengan selisih besar. (Kompleks; skip untuk MVP atau lakukan di laporan).
// Untuk sekarang, cek apakah ada entry ledger pada periode tertutup tanpa reopened_at.
check(
    'Ledger entries pada periode tertutup (tanpa pembukaan kembali)',
    "SELECT COUNT(*) as violations FROM ledger_entries le
     WHERE EXISTS (
         SELECT 1 FROM daily_closings dc
         WHERE dc.cash_account_id = le.cash_account_id
           AND dc.closing_date = le.entry_date
           AND dc.status = 'CLOSED'
           AND dc.reopened_at IS NULL
     )"
);

// ─── 8. BOOKING STATUS TRANSISI ILEGAL (HEURISTIK) ───────────────────
// Cek apakah ada reservasi dengan status yang tidak ada di ENUM atau transisi mencurigakan.
// Semua status harus salah satu dari 10 yang valid.
check(
    'Booking dengan status tidak dikenal',
    "SELECT COUNT(*) as violations FROM reservasi
     WHERE status NOT IN (
         'MENUNGGU_BAYAR', 'MENUNGGU_VERIFIKASI', 'LUNAS', 'DITOLAK', 'KEDALUWARSA',
         'DIBATALKAN', 'SELESAI', 'REFUND_DIAJUKAN', 'DIREFUND', 'TIDAK_HADIR'
     )"
);

// ─── 9. QR TOKEN KONSISTENSI ─────────────────────────────────────────
// Booking LUNAS harus punya qr_token; booking batal/ditolak/kedaluwarsa tidak boleh punya.
check(
    'Booking LUNAS tanpa QR token',
    "SELECT COUNT(*) as violations FROM reservasi
     WHERE status IN ('LUNAS', 'SELESAI') AND (qr_token IS NULL OR qr_token = '')"
);

check(
    'Booking terminal (batal/tolak/expire) masih punya QR token',
    "SELECT COUNT(*) as violations FROM reservasi
     WHERE status IN ('DIBATALKAN', 'DITOLAK', 'KEDALUWARSA', 'DIREFUND', 'TIDAK_HADIR')
       AND qr_token IS NOT NULL AND qr_token != ''"
);

// ─── 10. CHECK-IN KONSISTENSI ────────────────────────────────────────
check(
    'Booking SELESAI tanpa check-in flag',
    "SELECT COUNT(*) as violations FROM reservasi
     WHERE status = 'SELESAI' AND is_checkin = 0"
);

check(
    'Booking belum SELESAI tapi sudah check-in',
    "SELECT COUNT(*) as violations FROM reservasi
     WHERE status != 'SELESAI' AND is_checkin = 1"
);

// ─── HASIL ───────────────────────────────────────────────────────────
echo "Selesai.\n\n";

if (count($violations) === 0) {
    echo "✓ Semua invarian lolos. Tidak ada pelanggaran.\n";
    exit(0);
} else {
    echo "✗ Ditemukan pelanggaran:\n\n";
    foreach ($violations as $v) {
        echo "  • $v\n";
    }
    echo "\n";
    exit(1);
}
