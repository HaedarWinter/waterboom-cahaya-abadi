-- V4_004: Expenses table - replace manual laporan_keuangan
-- Idempotent: cek tabel/kolom sebelum CREATE/ADD

START TRANSACTION;

-- 1. Tabel expenses (pengeluaran terstruktur)
CREATE TABLE IF NOT EXISTS `expenses` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `expense_date` DATE NOT NULL,
    `cash_account_id` INT NOT NULL,
    `category` ENUM('OPERASIONAL','MAINTENANCE','UTILITIES','PAYROLL','MARKETING','LAINNYA') NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `amount` BIGINT UNSIGNED NOT NULL,
    `reference_no` VARCHAR(50),
    `receipt_path` VARCHAR(500),
    `status` ENUM('DRAFT','POSTED','VOIDED') NOT NULL DEFAULT 'DRAFT',
    `created_by` INT,
    `approved_by` INT,
    `approved_at` DATETIME,
    `voided_by` INT,
    `voided_at` DATETIME,
    `void_reason` TEXT,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_expenses_cash_account` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts`(`id`) ON DELETE RESTRICT,
    INDEX `idx_expenses_date_account` (`expense_date`, `cash_account_id`),
    INDEX `idx_expenses_status` (`status`),
    INDEX `idx_expenses_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Migrasi data dari laporan_keuangan (jika ada) ke expenses + ledger
-- Hanya jalan sekali: cek tabel lama ada
SET @has_laporan = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laporan_keuangan');
SET @sql = IF(@has_laporan > 0, 
    'INSERT IGNORE INTO `expenses` (`expense_date`, `cash_account_id`, `category`, `description`, `amount`, `receipt_path`, `status`, `created_by`, `created_at`, `updated_at`)
     SELECT `tanggal`, 1, 
            CASE 
                WHEN `kategori` LIKE "%Operasional%" THEN "OPERASIONAL"
                WHEN `kategori` LIKE "%Maintenance%" THEN "MAINTENANCE"
                WHEN `kategori` LIKE "%Utilitas%" OR `kategori` LIKE "%Listrik%" OR `kategori` LIKE "%Air%" THEN "UTILITIES"
                WHEN `kategori` LIKE "%Gaji%" THEN "PAYROLL"
                WHEN `kategori` LIKE "%Marketing%" THEN "MARKETING"
                ELSE "LAINNYA"
            END,
            `keterangan`, CAST(`nominal` AS UNSIGNED), `lampiran`, "POSTED", `admin_id`, `created_at`, `created_at`
     FROM `laporan_keuangan` WHERE `tipe` = "Keluar"',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Ledger entries untuk expenses yang sudah POSTED (idempoten via unique constraint uq_ledger_source)
SET @sql = IF(@has_laporan > 0,
    'INSERT IGNORE INTO `ledger_entries` (`entry_no`, `entry_date`, `cash_account_id`, `direction`, `category`, `amount`, `source_type`, `source_id`, `created_by`, `note`)
     SELECT 
         CONCAT("LED-EXP-", DATE_FORMAT(`expense_date`, "%Y%m%d"), "-", LPAD(`id`, 6, "0")),
         `expense_date`, `cash_account_id`, "OUT", "BIAYA_OPERASIONAL", `amount`, "EXPENSE", `id`, `created_by`,
         CONCAT("Migrasi dari laporan_keuangan: ", `description`)
     FROM `expenses` WHERE `status` = "POSTED"',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;