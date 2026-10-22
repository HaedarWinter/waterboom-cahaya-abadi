-- Rollback V4_004: Expenses table

START TRANSACTION;

-- Hapus ledger entries yang dibuat dari expenses (source_type = EXPENSE)
DELETE FROM `ledger_entries` WHERE `source_type` = 'EXPENSE';

-- Drop tabel expenses
DROP TABLE IF EXISTS `expenses`;

COMMIT;