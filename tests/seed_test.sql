-- Seed SQL for db_ujikom_test (Ledger characterization tests)
-- Creates minimal schema + fixture data covering all test cases.

SET FOREIGN_KEY_CHECKS = 0;

-- ================================================================
-- 1. settings (minimal rows required by Setting::get or defaults)
-- ================================================================
CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(50) NOT NULL PRIMARY KEY,
    `setting_value` TEXT,
    `label` VARCHAR(100) DEFAULT NULL,
    `kategori` VARCHAR(50) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`, `label`, `kategori`) VALUES
    ('kuota_default', '200', 'Kuota default harian', 'booking'),
    ('payment_deadline_minutes', '120', 'Batas waktu pembayaran (menit)', 'pembayaran'),
    ('unique_code_enabled', '1', 'Aktifkan kode unik', 'pembayaran'),
    ('reversal_approval_threshold', '1000000', 'Threshold approval reversal', 'pembayaran'),
    ('reconciliation_alert_days', '3', 'Peringatan transaksi belum terekonsiliasi', 'pembayaran')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- ================================================================
-- 2. admin (test user with Finance role & finance.view permission)
-- ================================================================
CREATE TABLE IF NOT EXISTS `admin` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('Super Admin','Admin','Finance','Gate','Akuntan') NOT NULL DEFAULT 'Admin',
    `status` ENUM('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif',
    `permissions` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- id=1 Finance user for tests
INSERT INTO `admin` (`id`,`username`,`email`,`password`,`role`,`status`,`permissions`)
VALUES (1, 'test', 'test@test.local',
        '$2y$10$XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX', -- dummy hash
        'Finance', 'Aktif',
        '{"finance":{"view":true}}')
ON DUPLICATE KEY UPDATE `id`=1;

-- ================================================================
-- 3. cash_accounts (at least one active)
-- ================================================================
CREATE TABLE IF NOT EXISTS `cash_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(30) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `type` ENUM('CASH','BANK','EWALLET') NOT NULL,
    `account_number` VARCHAR(50) NULL,
    `account_holder` VARCHAR(100) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_cash_account_code` (`code`),
    INDEX `idx_cash_account_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `cash_accounts` (`id`,`code`,`name`,`type`,`account_number`,`account_holder`,`is_active`)
VALUES (1, 'KAS_001', 'Kas Utama', 'CASH', NULL, NULL, 1)
ON DUPLICATE KEY UPDATE `id`=1;

-- ================================================================
-- 4. ledger_entries (fixture: 4 entries + 1 orphan for baseline)
-- ================================================================
CREATE TABLE IF NOT EXISTS `ledger_entries` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `entry_no` VARCHAR(30) NOT NULL,
    `entry_date` DATE NOT NULL,
    `posted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `cash_account_id` INT NOT NULL,
    `direction` ENUM('IN','OUT') NOT NULL,
    `category` ENUM(
        'TIKET','KODE_UNIK','REFUND','BIAYA_OPERASIONAL',
        'KOREKSI','LAINNYA','SALDO_AWAL','BACKFILL'
    ) NOT NULL,
    `amount` BIGINT NOT NULL,
    `source_type` ENUM('PAYMENT','REFUND','EXPENSE','ADJUSTMENT','BACKFILL') NOT NULL,
    `source_id` INT NULL,
    `reverses_entry_id` BIGINT NULL,
    `reconciled_at` DATETIME NULL,
    `reconciliation_id` INT NULL,
    `created_by` INT NULL,
    `note` TEXT NULL,
    `reverses_entry_key` BIGINT AS (COALESCE(`reverses_entry_id`,0)) STORED,
    UNIQUE KEY `uq_ledger_entry_no` (`entry_no`),
    UNIQUE KEY `uq_ledger_source` (`source_type`,`source_id`,`category`,`reverses_entry_key`),
    INDEX `idx_ledger_account_date` (`cash_account_id`,`entry_date`),
    INDEX `idx_ledger_reconciled` (`reconciled_at`),
    CONSTRAINT `chk_ledger_amount_positive` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Fixtures (amount in rupiah, BIGINT)
-- 1) TIKET IN 500.000
-- 2) BIAYA_OPERASIONAL OUT 150.000
-- 3) REFUND OUT 50.000
-- 4) KODE_UNIK IN 25.000
-- All for 2025-01-15, cash_account_id=1, created_by=1
INSERT INTO `ledger_entries` (
    `id`,`entry_no`,`entry_date`,`posted_at`,`cash_account_id`,`direction`,`category`,
    `amount`,`source_type`,`source_id`,`created_by`,`note`
) VALUES
    (1, 'LED-001', '2025-01-15', '2025-01-15 09:00:00', 1, 'IN', 'TIKET',
     500000, 'PAYMENT', 1, 1, 'Tiket masuk mingguan'),
    (2, 'LED-002', '2025-01-15', '2025-01-15 10:00:00', 1, 'OUT', 'BIAYA_OPERASIONAL',
     150000, 'EXPENSE', 1, 1, 'Beli chlorine'),
    (3, 'LED-003', '2025-01-15', '2025-01-15 11:00:00', 1, 'OUT', 'REFUND',
     50000, 'REFUND', 1, 1, 'Refund keluhan pelanggan'),
    (4, 'LED-004', '2025-01-15', '2025-01-15 12:00:00', 1, 'IN', 'KODE_UNIK',
     25000, 'PAYMENT', 1, 1, 'Kode unik transfer')
ON DUPLICATE KEY UPDATE `id`=VALUES(`id`);

-- Orphan fixture: source_type=PAYMENT, source_id=999999 (non-existent payment)
-- This reproduces the integrity_check baseline where a ledger entry references missing payment.
INSERT INTO `ledger_entries` (
    `id`,`entry_no`,`entry_date`,`posted_at`,`cash_account_id`,`direction`,`category`,
    `amount`,`source_type`,`source_id`,`created_by`,`note`
) VALUES
    (999, 'LED-ORPHAN', '2025-01-15', '2025-01-15 13:00:00', 1, 'IN', 'TIKET',
     100000, 'PAYMENT', 999999, 1, 'Orphan fixture for characterization')
ON DUPLICATE KEY UPDATE `id`=VALUES(`id`);

-- ================================================================
-- 5. audit_logs (existing schema from migration v3)
-- ================================================================
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT NULL,
    `admin_username` VARCHAR(50) NULL,
    `action` VARCHAR(50) NOT NULL,
    `target_table` VARCHAR(50) NULL,
    `target_id` INT NULL,
    `details` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_audit_action` (`action`),
    INDEX `idx_audit_admin` (`admin_id`),
    INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ================================================================
-- 6. booking_status_history & payments (for orphan baseline)
-- ================================================================
CREATE TABLE IF NOT EXISTS `booking_status_history` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `booking_id` INT NOT NULL,
    `from_status` VARCHAR(30) NULL,
    `to_status` VARCHAR(30) NOT NULL,
    `actor_id` INT NULL,
    `actor_role` VARCHAR(30) NULL,
    `reason` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_history_booking` (`booking_id`),
    INDEX `idx_history_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `booking_id` INT NOT NULL,
    `attempt_no` INT NOT NULL DEFAULT 1,
    `method` ENUM('TRANSFER_BANK','CASH_COUNTER','QRIS','LAINNYA') NOT NULL DEFAULT 'TRANSFER_BANK',
    `provider_ref` VARCHAR(100) NULL,
    `amount` BIGINT NOT NULL,
    `unique_code` INT NOT NULL DEFAULT 0,
    `amount_received` BIGINT NULL,
    `transfer_at` DATETIME NULL,
    `destination_account_id` INT NULL,
    `status` ENUM(
        'BELUM_ADA_BUKTI','MENUNGGU_VERIFIKASI','TERVERIFIKASI','DITOLAK',
        'DIREFUND_SEBAGIAN','DIREFUND_PENUH'
    ) NOT NULL DEFAULT 'BELUM_ADA_BUKTI',
    `verified_by` INT NULL,
    `verified_at` DATETIME NULL,
    `rejection_reason` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_payment_attempt` (`booking_id`,`attempt_no`),
    INDEX `idx_payment_booking` (`booking_id`),
    INDEX `idx_payment_status` (`status`),
    INDEX `idx_payment_destination` (`destination_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Orphan fixtures (match integrity_check findings)
INSERT INTO `booking_status_history` (`id`,`booking_id`,`from_status`,`to_status`,`actor_id`,`actor_role`,`reason`)
VALUES (1, 999999, 'MENUNGGU_BAYAR', 'LUNAS', 1, 'SYSTEM', 'Orphan fixture'),
       (2, 999998, 'LUNAS', 'SELESAI', 1, 'SYSTEM', 'Orphan fixture')
ON DUPLICATE KEY UPDATE `id`=VALUES(`id`);

INSERT INTO `payments` (`id`,`booking_id`,`attempt_no`,`method`,`amount`,`status`,`created_at`)
VALUES (999999, 999999, 1, 'TRANSFER_BANK', 500000, 'TERVERIFIKASI', '2025-01-15 09:00:00')
ON DUPLICATE KEY UPDATE `id`=VALUES(`id`);

SET FOREIGN_KEY_CHECKS = 1;

-- End of seed