-- ══════════════════════════════════════════════════════════════
-- ROLLBACK V3: Fondasi Data Keuangan
-- ══════════════════════════════════════════════════════════════
-- PERINGATAN:
--   Rollback ini menghapus tabel baru beserta seluruh isinya.
--   Hanya jalankan bila tabel baru belum dipakai di produksi.
--   Backup dulu.
--
-- Yang TIDAK dihapus:
--   - audit_logs dan laporan_keuangan: tabel lama, mungkin sudah ada
--     sebelum migrasi v3.
--   - kolom reservasi.is_checkin dan reservasi.waktu_checkin: mungkin
--     sudah ada sebelum migrasi v3.
--   - nilai settings baru: mungkin sudah diubah owner.
--   - data reservasi dan pembayaran lama.
-- ══════════════════════════════════════════════════════════════
USE db_ujikom;

DROP TABLE IF EXISTS daily_closings;
DROP TABLE IF EXISTS refunds;
DROP TABLE IF EXISTS ledger_entries;
DROP TABLE IF EXISTS booking_status_history;
DROP TABLE IF EXISTS payment_proofs;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS cash_accounts;
