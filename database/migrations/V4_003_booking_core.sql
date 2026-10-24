-- V4_003: Booking core - payment_deadline_at, channel, booking_items
-- Idempotent: cek kolom/tabel sebelum ADD/CREATE

START TRANSACTION;

-- 1. Tambah kolom ke reservasi (cek dulu)
SET @has_payment_deadline = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservasi' AND COLUMN_NAME = 'payment_deadline_at');
SET @has_channel = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservasi' AND COLUMN_NAME = 'channel');
SET @has_visit_date = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservasi' AND COLUMN_NAME = 'visit_date');

SET @sql = IF(@has_payment_deadline = 0, 'ALTER TABLE `reservasi` ADD COLUMN `payment_deadline_at` DATETIME NULL AFTER `created_at`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(@has_channel = 0, 'ALTER TABLE `reservasi` ADD COLUMN `channel` ENUM(\'ONLINE\',\'COUNTER\') NOT NULL DEFAULT \'ONLINE\' AFTER `metode_bayar`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(@has_visit_date = 0, 'ALTER TABLE `reservasi` ADD COLUMN `visit_date` DATE NULL AFTER `tanggal_kunjungan`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill visit_date dari tanggal_kunjungan (VARCHAR → DATE)
UPDATE `reservasi` SET `visit_date` = STR_TO_DATE(`tanggal_kunjungan`, '%Y-%m-%d') WHERE `visit_date` IS NULL AND `tanggal_kunjungan` IS NOT NULL;

-- 2. Indeks untuk query expire & lookup
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservasi' AND INDEX_NAME = 'idx_reservasi_status_deadline');
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `reservasi` ADD INDEX `idx_reservasi_status_deadline` (`status`, `payment_deadline_at`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservasi' AND INDEX_NAME = 'idx_reservasi_visit_date');
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `reservasi` ADD INDEX `idx_reservasi_visit_date` (`visit_date`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Tabel referensi tiket harus dibuat sebelum tabel yang memiliki foreign key.
CREATE TABLE IF NOT EXISTS `ticket_types` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(20) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ticket_types_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabel booking_items (snapshot harga)
CREATE TABLE IF NOT EXISTS `booking_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id` INT NOT NULL,
    `ticket_type_id` BIGINT UNSIGNED NOT NULL,
    `qty` INT UNSIGNED NOT NULL,
    `unit_price` BIGINT UNSIGNED NOT NULL,
    `subtotal` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_booking_items_booking` FOREIGN KEY (`booking_id`) REFERENCES `reservasi`(`id`) ON DELETE CASCADE,
    INDEX `idx_booking_items_booking` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabel ticket_prices (harga efektif per tanggal)
CREATE TABLE IF NOT EXISTS `ticket_prices` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ticket_type_id` BIGINT UNSIGNED NOT NULL,
    `price` BIGINT UNSIGNED NOT NULL,
    `effective_from` DATE NOT NULL,
    `effective_to` DATE NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_ticket_prices_type` FOREIGN KEY (`ticket_type_id`) REFERENCES `ticket_types`(`id`) ON DELETE CASCADE,
    INDEX `idx_ticket_prices_type_effective` (`ticket_type_id`, `effective_from`, `effective_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Seed ticket_types default
INSERT IGNORE INTO `ticket_types` (`code`, `name`, `description`, `sort_order`) VALUES
('ADULT_WEEKDAY', 'Dewasa Weekday', 'Tiket dewasa hari biasa', 1),
('CHILD_WEEKDAY', 'Anak Weekday', 'Tiket anak hari biasa', 2),
('ADULT_WEEKEND', 'Dewasa Weekend', 'Tiket dewasa akhir pekan', 3),
('CHILD_WEEKEND', 'Anak Weekend', 'Tiket anak akhir pekan', 4);

COMMIT;
