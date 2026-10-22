-- Rollback V4_003: Booking core
-- Hanya untuk database terisolasi/test

START TRANSACTION;

-- Drop tabel baru (cascade FK ke booking_items)
DROP TABLE IF EXISTS `ticket_prices`;
DROP TABLE IF EXISTS `ticket_types`;
DROP TABLE IF EXISTS `booking_items`;

-- Hapus kolom dari reservasi
SET @has_payment_deadline = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservasi' AND COLUMN_NAME = 'payment_deadline_at');
SET @has_channel = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservasi' AND COLUMN_NAME = 'channel');
SET @has_visit_date = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservasi' AND COLUMN_NAME = 'visit_date');

SET @sql = IF(@has_payment_deadline = 1, 'ALTER TABLE `reservasi` DROP COLUMN `payment_deadline_at`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(@has_channel = 1, 'ALTER TABLE `reservasi` DROP COLUMN `channel`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(@has_visit_date = 1, 'ALTER TABLE `reservasi` DROP COLUMN `visit_date`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Hapus indeks
ALTER TABLE `reservasi` DROP INDEX IF EXISTS `idx_reservasi_status_deadline`;
ALTER TABLE `reservasi` DROP INDEX IF EXISTS `idx_reservasi_visit_date`;

COMMIT;