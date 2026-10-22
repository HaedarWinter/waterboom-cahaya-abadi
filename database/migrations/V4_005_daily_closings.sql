-- V4_005: Daily closings - tutup harian & penguncian periode
-- Idempotent

START TRANSACTION;

-- 1. Tabel daily_closings
CREATE TABLE IF NOT EXISTS `daily_closings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `closing_date` DATE NOT NULL,
    `cash_account_id` INT NOT NULL,
    `opening_balance` BIGINT NOT NULL DEFAULT 0,
    `total_in` BIGINT NOT NULL DEFAULT 0,
    `total_out` BIGINT NOT NULL DEFAULT 0,
    `closing_balance` BIGINT NOT NULL DEFAULT 0,
    `physical_cash` BIGINT NOT NULL DEFAULT 0,
    `difference` BIGINT NOT NULL DEFAULT 0,
    `difference_note` VARCHAR(500),
    `status` ENUM('OPEN','CLOSED','REOPENED') NOT NULL DEFAULT 'OPEN',
    `closed_by` INT,
    `closed_at` DATETIME,
    `reopened_by` INT,
    `reopened_at` DATETIME,
    `reopen_reason` TEXT,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_daily_closings_cash_account` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts`(`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uq_daily_closing_date_account` (`closing_date`, `cash_account_id`),
    INDEX `idx_daily_closings_status` (`status`),
    INDEX `idx_daily_closings_closed_by` (`closed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tambah kolom `is_locked` ke ledger_entries untuk periode tertutup
-- Cek kolom sudah ada
SET @has_locked = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ledger_entries' AND COLUMN_NAME = 'is_locked');
SET @sql = IF(@has_locked = 0, 'ALTER TABLE `ledger_entries` ADD COLUMN `is_locked` TINYINT(1) NOT NULL DEFAULT 0 AFTER `reconciliation_id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Indeks untuk query lock check
SET @idx_locked = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ledger_entries' AND INDEX_NAME = 'idx_ledger_entries_locked');
SET @sql = IF(@idx_locked = 0, 'ALTER TABLE `ledger_entries` ADD INDEX `idx_ledger_entries_locked` (`is_locked`, `entry_date`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;