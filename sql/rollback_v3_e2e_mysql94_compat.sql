-- E2E compatibility rollback.
-- WARNING: deletes tables and migration-created data.
-- Run only on an isolated test database after owner approval.

DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS daily_closings;
DROP TABLE IF EXISTS refunds;
DROP TABLE IF EXISTS payment_proofs;
DROP TABLE IF EXISTS ledger_entries;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS cash_accounts;
DROP TABLE IF EXISTS laporan_keuangan;
DROP TABLE IF EXISTS faq;
DROP TABLE IF EXISTS berita;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS kuota_harian;
DROP TABLE IF EXISTS reservasi;
DROP TABLE IF EXISTS galeri;
DROP TABLE IF EXISTS buku_tamu;
