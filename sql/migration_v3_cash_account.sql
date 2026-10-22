-- ══════════════════════════════════════════════════════════════
-- MIGRATION V3: Cash Account & Ledger Backfill
-- ══════════════════════════════════════════════════════════════
-- Seed akun kas generik & backfill ledger dari payments TERVERIFIKASI
-- Idempotent. Jalankan setelah migration_v3_foundation & state_machine.
-- ══════════════════════════════════════════════════════════════
USE db_ujikom;

-- 1. Seed akun kas generik
INSERT INTO cash_accounts (code, name, type, is_active)
VALUES ('KAS_001', 'Kas Utama', 'CASH', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), type = VALUES(type), is_active = VALUES(is_active);

-- 2. Backfill ledger dari payments TERVERIFIKASI yang belum punya entri
--    Hanya payments yang status TERVERIFIKASI dan belum ada di ledger_entries
INSERT INTO ledger_entries (
    entry_no,
    entry_date,
    cash_account_id,
    direction,
    category,
    amount,
    source_type,
    source_id,
    created_by,
    note
)
SELECT
    CONCAT('LED-', DATE_FORMAT(p.verified_at, '%Y%m%d'), '-', LPAD(p.id, 6, '0')),
    DATE(p.verified_at),
    ca.id,
    'IN',
    'TIKET',
    p.amount_received,
    'PAYMENT',
    p.id,
    p.verified_by,
    CONCAT('Backfill verifikasi payment #', p.id, ' booking #', p.booking_id)
FROM payments p
JOIN cash_accounts ca ON ca.code = 'KAS_001'
WHERE p.status = 'TERVERIFIKASI'
  AND p.amount_received IS NOT NULL
  AND p.amount_received > 0
  AND p.verified_at IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM ledger_entries le
      WHERE le.source_type = 'PAYMENT' AND le.source_id = p.id
);

