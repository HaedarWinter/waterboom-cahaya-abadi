-- Rollback V4_005: Daily closings

START TRANSACTION;

-- Drop index
DROP INDEX IF EXISTS `idx_ledger_entries_locked` ON `ledger_entries`;

-- Drop column is_locked
SET @has_locked = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ledger_entries' AND COLUMN_NAME = 'is_locked');
SET @sql = IF(@has_locked = 1, 'ALTER TABLE `ledger_entries` DROP COLUMN `is_locked`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Drop table
DROP TABLE IF EXISTS `daily_closings`;

COMMIT;