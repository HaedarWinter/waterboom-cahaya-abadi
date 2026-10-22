-- ══════════════════════════════════════════════════════════════
-- ROLLBACK V3: Cash Account & Ledger Backfill
-- ══════════════════════════════════════════════════════════════
-- PERINGATAN: menghapus entri ledger yang berasal dari backfill.
-- Jalankan hanya sebelum ledger dipakai produksi.
-- Backup dulu.
-- ══════════════════════════════════════════════════════════════
USE db_ujikom;

DELETE FROM ledger_entries WHERE note LIKE 'Backfill verifikasi payment %';
DELETE FROM cash_accounts WHERE code = 'KAS_001';

