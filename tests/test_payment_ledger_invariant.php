<?php
/**
 * Test: Payment-Ledger Invariant
 * Jalankan: php tests/test_payment_ledger_invariant.php
 * Memastikan 1 verifikasi = 1 payment TERVERIFIKASI = 1 ledger entry
 */
declare(strict_types=1);

require_once __DIR__ . '/guard.php';
require_test_database_guard('SCRIPT: BLOCKED (guard rejected configuration)');

if (!defined('WRITE_SCRIPT_APPROVED') || WRITE_SCRIPT_APPROVED !== true) {
    fwrite(STDERR, "BLOCKED: write policy not approved for " . basename(__FILE__) . "\n");
    exit(3);
}

require_once __DIR__ . '/../app/init.php';

use App\Core\Database;
use App\Services\BookingStateService;

$db = Database::getInstance();
$conn = $db->getConnection();
require_test_database_connection($conn, 'SCRIPT: BLOCKED (guard rejected connection)');

$fail = 0;
function check($cond, $msg) {
    global $fail;
    if ($cond) {
        echo "  ✓ $msg\n";
    } else {
        echo "  ✗ FAIL: $msg\n";
        $fail++;
    }
}

// ── Setup: buat booking dummy ──
$conn->query("DELETE FROM reservasi WHERE kode_booking LIKE 'INV%'");
$r = $conn->query("INSERT INTO reservasi (kode_booking, nama, no_hp, email, tanggal_kunjungan, jenis_tiket, jumlah_dewasa, jumlah_anak, total_harga, status, created_at)
              VALUES ('INV001', 'Budi', '08123456789', 'b@t.com', CURDATE(), 'Weekday', 1, 0, 20000, 'MENUNGGU_BAYAR', NOW())");
if (!$r) { echo "INSERT booking failed: " . $conn->error . "\n"; exit(1); }
$bookingId = $conn->insert_id;
echo "Created booking #$bookingId\n";

echo "Test 1: Transisi LUNAS menciptakan payment + ledger yang konsisten\n";

$r1 = BookingStateService::transition($bookingId, BookingStateService::MENUNGGU_VERIFIKASI, null, 'PENGUNJUNG', 'Upload');
check($r1['success'], 'Transisi MENUNGGU_VERIFIKASI: ' . $r1['message']);

$r2 = BookingStateService::transition($bookingId, BookingStateService::LUNAS, 1, 'ADMIN', 'Verify');
check($r2['success'], 'Transisi LUNAS: ' . $r2['message']);

$pay = $conn->query("SELECT id, status, amount_received FROM payments WHERE booking_id = $bookingId")->fetch_assoc();
check($pay !== null, 'Payment ada');
check($pay['status'] === 'TERVERIFIKASI', 'Payment status TERVERIFIKASI');
check((int)$pay['amount_received'] === 20000, 'Payment amount_received = 20000');

$led = $conn->query("SELECT * FROM ledger_entries WHERE source_type = 'PAYMENT' AND source_id = {$pay['id']}")->fetch_assoc();
check($led !== null, 'Ledger entry ada');
check((int)$led['amount'] === 20000, 'Ledger amount = 20000');
check($led['direction'] === 'IN', 'Ledger direction = IN');
check($led['category'] === 'TIKET', 'Ledger category = TIKET');
check($led['source_type'] === 'PAYMENT', 'Ledger source_type = PAYMENT');

// ── Test 2: Idempotency — transisi LUNAS dua kali tidak bikin ledger ganda ──
echo "\nTest 2: Idempotency (transisi LUNAS dua kali)\n";
$before = $conn->query("SELECT COUNT(*) as c FROM ledger_entries WHERE source_type = 'PAYMENT' AND source_id = {$pay['id']}")->fetch_assoc()['c'];
$r3 = BookingStateService::transition($bookingId, BookingStateService::LUNAS, 1, 'ADMIN', 'Re-verify');
check($r3['success'] === false, 'Transisi LUNAS kedua ditolak (sudah LUNAS)');
$after = $conn->query("SELECT COUNT(*) as c FROM ledger_entries WHERE source_type = 'PAYMENT' AND source_id = {$pay['id']}")->fetch_assoc()['c'];
check($before === $after, "Ledger entries tidak bertambah (sebelum=$before, sesudah=$after)");

// ── Test 3: Invariant global ──
echo "\nTest 3: Global invariant (tidak ada orphan)\n";
$orphanPay = $conn->query("SELECT COUNT(*) as c FROM payments WHERE status = 'TERVERIFIKASI' AND NOT EXISTS (SELECT 1 FROM ledger_entries WHERE source_type = 'PAYMENT' AND source_id = payments.id)")->fetch_assoc()['c'];
check((int)$orphanPay === 0, "Tidak ada payment TERVERIFIKASI tanpa ledger ($orphanPay)");
$orphanLed = $conn->query("SELECT COUNT(*) as c FROM ledger_entries WHERE source_type = 'PAYMENT' AND NOT EXISTS (SELECT 1 FROM payments WHERE id = ledger_entries.source_id AND status = 'TERVERIFIKASI')")->fetch_assoc()['c'];
check((int)$orphanLed === 0, "Tidak ada ledger tanpa payment TERVERIFIKASI ($orphanLed)");

// ── Cleanup ──
$conn->query("DELETE FROM ledger_entries WHERE source_type = 'PAYMENT' AND source_id = {$pay['id']}");
$conn->query("DELETE FROM payments WHERE id = {$pay['id']}");
$conn->query("DELETE FROM booking_status_history WHERE booking_id = $bookingId");
$conn->query("DELETE FROM reservasi WHERE id = $bookingId");

echo "\n";
if ($fail === 0) {
    echo "✓ Semua test lulus.\n";
    exit(0);
} else {
    echo "✗ $fail test gagal.\n";
    exit(1);
}