-- ══════════════════════════════════════════════════════════════
-- MIGRATION V3: Fondasi Data Keuangan
-- ══════════════════════════════════════════════════════════════
-- Tujuan:
--   1. Membekukan skema yang selama ini dibuat runtime ke migrasi.
--   2. Menambah tabel pembayaran, riwayat status, refund, kas, dan tutup harian.
--   3. Backfill pembayaran lama dari tabel reservasi tanpa mengarang entri kas.
--
-- Sifat: idempotent (aman dijalankan berulang).
-- Prasyarat: backup database. Jalankan rollback hanya bila migrasi baru diterapkan.
-- Catatan: tabel laporan_keuangan dan audit_logs dipertahankan sebagai data lama.
--           Keduanya bukan buku kas append-only.
-- ══════════════════════════════════════════════════════════════
USE db_ujikom;

-- ──────────────────────────────────────────────────────────────
-- 1. Selaraskan skema lama yang belum ada di file migrasi
-- ──────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NULL,
    admin_username VARCHAR(50) NULL,
    action VARCHAR(50) NOT NULL,
    target_table VARCHAR(50) NULL,
    target_id INT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_action (action),
    INDEX idx_audit_admin (admin_id),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS laporan_keuangan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    tipe ENUM('Masuk', 'Keluar') NOT NULL DEFAULT 'Keluar',
    kategori VARCHAR(50) NOT NULL DEFAULT 'Operasional',
    nominal DECIMAL(15, 2) NOT NULL DEFAULT 0,
    keterangan TEXT NULL,
    lampiran VARCHAR(255) NULL,
    tanggal DATE NOT NULL,
    status ENUM('Final', 'Draft') DEFAULT 'Final',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_laporan_tanggal (tanggal),
    INDEX idx_laporan_tipe (tipe)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE laporan_keuangan
    ADD INDEX IF NOT EXISTS idx_laporan_tanggal (tanggal),
    ADD INDEX IF NOT EXISTS idx_laporan_tipe (tipe);

ALTER TABLE audit_logs
    ADD INDEX IF NOT EXISTS idx_audit_action (action),
    ADD INDEX IF NOT EXISTS idx_audit_admin (admin_id),
    ADD INDEX IF NOT EXISTS idx_audit_created (created_at);

-- Kolom check-in sudah dipakai kode tetapi belum ada di db.sql/migration_v2.
ALTER TABLE reservasi
    ADD COLUMN IF NOT EXISTS is_checkin TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS waktu_checkin DATETIME NULL;

-- Selaraskan peran dengan PRD. Nilai lama tetap dipertahankan.
ALTER TABLE admin
    MODIFY COLUMN role ENUM('Super Admin', 'Admin', 'Finance', 'Gate', 'Akuntan')
    NOT NULL DEFAULT 'Admin';

-- ──────────────────────────────────────────────────────────────
-- 2. Akun kas
-- ──────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS cash_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('CASH', 'BANK', 'EWALLET') NOT NULL,
    account_number VARCHAR(50) NULL,
    account_holder VARCHAR(100) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cash_account_code (code),
    INDEX idx_cash_account_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ──────────────────────────────────────────────────────────────
-- 3. Pembayaran dan bukti
-- ──────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    attempt_no INT NOT NULL DEFAULT 1,
    method ENUM('TRANSFER_BANK', 'CASH_COUNTER', 'QRIS', 'LAINNYA')
        NOT NULL DEFAULT 'TRANSFER_BANK',
    provider_ref VARCHAR(100) NULL,
    amount BIGINT NOT NULL,
    unique_code INT NOT NULL DEFAULT 0,
    amount_received BIGINT NULL,
    transfer_at DATETIME NULL,
    destination_account_id INT NULL,
    status ENUM(
        'BELUM_ADA_BUKTI',
        'MENUNGGU_VERIFIKASI',
        'TERVERIFIKASI',
        'DITOLAK',
        'DIREFUND_SEBAGIAN',
        'DIREFUND_PENUH'
    ) NOT NULL DEFAULT 'BELUM_ADA_BUKTI',
    verified_by INT NULL,
    verified_at DATETIME NULL,
    rejection_reason TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payment_attempt (booking_id, attempt_no),
    INDEX idx_payment_booking (booking_id),
    INDEX idx_payment_status (status),
    INDEX idx_payment_destination (destination_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS payment_proofs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    storage_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NULL,
    mime_type VARCHAR(100) NULL,
    file_size INT UNSIGNED NULL,
    sha256 CHAR(64) NULL,
    is_legacy TINYINT(1) NOT NULL DEFAULT 0,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_proof_payment (payment_id),
    INDEX idx_proof_sha256 (sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ──────────────────────────────────────────────────────────────
-- 4. Riwayat status booking
-- ──────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS booking_status_history (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    from_status VARCHAR(30) NULL,
    to_status VARCHAR(30) NOT NULL,
    actor_id INT NULL,
    actor_role VARCHAR(30) NULL,
    reason TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_history_booking (booking_id),
    INDEX idx_history_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ──────────────────────────────────────────────────────────────
-- 5. Buku kas append-only
-- ──────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS ledger_entries (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    entry_no VARCHAR(30) NOT NULL,
    entry_date DATE NOT NULL,
    posted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cash_account_id INT NOT NULL,
    direction ENUM('IN', 'OUT') NOT NULL,
    category ENUM(
        'TIKET',
        'KODE_UNIK',
        'REFUND',
        'BIAYA_OPERASIONAL',
        'KOREKSI',
        'LAINNYA',
        'SALDO_AWAL',
        'BACKFILL'
    ) NOT NULL,
    amount BIGINT NOT NULL,
    source_type ENUM('PAYMENT', 'REFUND', 'EXPENSE', 'ADJUSTMENT', 'BACKFILL') NOT NULL,
    source_id INT NULL,
    reverses_entry_id BIGINT NULL,
    reconciled_at DATETIME NULL,
    reconciliation_id INT NULL,
    created_by INT NULL,
    note TEXT NULL,
    reverses_entry_key BIGINT AS (COALESCE(reverses_entry_id, 0)) STORED,
    UNIQUE KEY uq_ledger_entry_no (entry_no),
    UNIQUE KEY uq_ledger_source (source_type, source_id, category, reverses_entry_key),
    INDEX idx_ledger_account_date (cash_account_id, entry_date),
    INDEX idx_ledger_reconciled (reconciled_at),
    CONSTRAINT chk_ledger_amount_positive CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ──────────────────────────────────────────────────────────────
-- 6. Refund
-- ──────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS refunds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    payment_id INT NULL,
    amount BIGINT NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('DIAJUKAN', 'DISETUJUI', 'DITOLAK', 'DIREFUND')
        NOT NULL DEFAULT 'DIAJUKAN',
    requested_by INT NULL,
    approved_by INT NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_refund_booking (booking_id),
    INDEX idx_refund_status (status),
    CONSTRAINT chk_refund_amount_positive CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ──────────────────────────────────────────────────────────────
-- 7. Tutup harian
-- ──────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS daily_closings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    closing_date DATE NOT NULL,
    cash_account_id INT NOT NULL,
    total_in BIGINT NOT NULL DEFAULT 0,
    total_out BIGINT NOT NULL DEFAULT 0,
    system_balance BIGINT NOT NULL DEFAULT 0,
    physical_balance BIGINT NULL,
    difference BIGINT NOT NULL DEFAULT 0,
    status ENUM('OPEN', 'CLOSED') NOT NULL DEFAULT 'CLOSED',
    closed_by INT NULL,
    closed_at DATETIME NULL,
    reopened_by INT NULL,
    reopened_at DATETIME NULL,
    reopen_reason TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_closing_day_account (closing_date, cash_account_id),
    INDEX idx_closing_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ──────────────────────────────────────────────────────────────
-- 8. Pengaturan baru (hanya menambah, tidak menimpa nilai yang ada)
-- ──────────────────────────────────────────────────────────────

INSERT INTO settings (setting_key, setting_value, label, kategori) VALUES
    ('payment_deadline_minutes', '120', 'Batas waktu pembayaran (menit)', 'pembayaran'),
    ('unique_code_enabled', '1', 'Aktifkan kode unik transfer', 'pembayaran'),
    ('reversal_approval_threshold', '1000000', 'Batas nominal reversal yang perlu persetujuan Super Admin', 'pembayaran'),
    ('reconciliation_alert_days', '3', 'Peringatan transaksi belum terekonsiliasi (hari)', 'pembayaran')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- ──────────────────────────────────────────────────────────────
-- 9. Backfill pembayaran lama
-- ──────────────────────────────────────────────────────────────
-- Satu reservasi lama menjadi satu percobaan pembayaran (attempt_no = 1).
-- Entri buku kas TIDAK dibuat di sini: finance harus mengonfirmasi saldo awal
-- dan bukti lama sebelum entri kas dikunci.

INSERT INTO payments (
    booking_id,
    attempt_no,
    method,
    amount,
    amount_received,
    transfer_at,
    status,
    verified_at,
    rejection_reason,
    created_at
)
SELECT
    r.id,
    1,
    CASE
        WHEN r.metode_bayar = 'Transfer Bank' THEN 'TRANSFER_BANK'
        ELSE 'CASH_COUNTER'
    END,
    r.total_harga,
    CASE
        WHEN r.status IN ('Dikonfirmasi', 'Selesai') THEN r.total_harga
        ELSE NULL
    END,
    NULL,
    CASE
        WHEN r.status = 'Ditolak' THEN 'DITOLAK'
        WHEN r.status IN ('Dikonfirmasi', 'Selesai') THEN 'TERVERIFIKASI'
        WHEN r.status = 'Pending' AND r.bukti_bayar IS NOT NULL THEN 'MENUNGGU_VERIFIKASI'
        WHEN r.status = 'Dibatalkan' AND r.bukti_bayar IS NOT NULL THEN 'MENUNGGU_VERIFIKASI'
        ELSE 'BELUM_ADA_BUKTI'
    END,
    CASE
        WHEN r.status IN ('Dikonfirmasi', 'Selesai') THEN r.created_at
        ELSE NULL
    END,
    r.catatan_tolak,
    r.created_at
FROM reservasi r
WHERE NOT EXISTS (
    SELECT 1
    FROM payments p
    WHERE p.booking_id = r.id
      AND p.attempt_no = 1
);

-- Bukti lama tetap dicatat sebagai jalur legacy. File belum dipindah ke luar
-- web root; pemindahan dan hashing dilakukan saat verifikasi berikutnya.
INSERT INTO payment_proofs (payment_id, storage_path, original_name, is_legacy)
SELECT
    p.id,
    CONCAT('assets/bukti_bayar/', r.bukti_bayar),
    r.bukti_bayar,
    1
FROM reservasi r
JOIN payments p
  ON p.booking_id = r.id
 AND p.attempt_no = 1
WHERE r.bukti_bayar IS NOT NULL
  AND r.bukti_bayar <> ''
  AND NOT EXISTS (
      SELECT 1
      FROM payment_proofs pp
      WHERE pp.payment_id = p.id
  );
