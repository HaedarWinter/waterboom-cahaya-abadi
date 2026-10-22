-- V4_006: Notifications - outbox + WA templates
-- Idempotent

START TRANSACTION;

-- 1. notification_outbox (append-only, retry queue)
CREATE TABLE IF NOT EXISTS `notification_outbox` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `channel` ENUM('WHATSAPP','EMAIL','SMS') NOT NULL DEFAULT 'WHATSAPP',
    `recipient` VARCHAR(100) NOT NULL,
    `template_key` VARCHAR(50),
    `subject` VARCHAR(255),
    `body` TEXT NOT NULL,
    `status` ENUM('PENDING','SENT','FAILED','DEAD') NOT NULL DEFAULT 'PENDING',
    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `last_error` TEXT,
    `scheduled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sent_at` DATETIME,
    `idempotency_key` VARCHAR(100),
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_outbox_idempotency` (`idempotency_key`),
    INDEX `idx_outbox_status_scheduled` (`status`, `scheduled_at`),
    INDEX `idx_outbox_channel_recipient` (`channel`, `recipient`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. wa_templates (message templates)
CREATE TABLE IF NOT EXISTS `wa_templates` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key` VARCHAR(50) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `subject` VARCHAR(255),
    `body` TEXT NOT NULL,
    `variables` JSON,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` INT,
    `updated_by` INT,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wa_templates_key` (`key`),
    INDEX `idx_wa_templates_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Seed default templates
INSERT IGNORE INTO `wa_templates` (`key`, `name`, `subject`, `body`, `variables`, `created_by`) VALUES
('booking_created', 'Booking Dibuat', 'Booking Baru', 
'Halo {nama},\n\nBooking Anda berhasil dibuat:\n- Kode: {kode_booking}\n- Tanggal Kunjungan: {tanggal_kunjungan}\n- Total: Rp {total_harga}\n\nSilakan lakukan pembayaran sebelum {deadline}.\n\nTerima kasih,\nCahaya Abadi',
'["nama","kode_booking","tanggal_kunjungan","total_harga","deadline"]', 1),

('payment_verified', 'Pembayaran Diverifikasi', 'Pembayaran Berhasil',
'Halo {nama},\n\nPembayaran untuk booking {kode_booking} telah diverifikasi.\n\nTiket Anda sudah siap. Kunjungi {ticket_url} untuk mengunduh tiket (QR Code).\n\nTanggal Kunjungan: {tanggal_kunjungan}\n\nSampai jumpa di Cahaya Abadi!',
'["nama","kode_booking","ticket_url","tanggal_kunjungan"]', 1),

('payment_rejected', 'Pembayaran Ditolak', 'Bukti Ditolak',
'Halo {nama},\n\nBukti pembayaran untuk booking {kode_booking} ditolak:\nAlasan: {reason}\n\nSilakan unggah ulang bukti yang valid sebelum {deadline}.\n\n{base_url}/booking/{kode_booking}',
'["nama","kode_booking","reason","deadline","base_url"]', 1),

('booking_expired', 'Booking Kedaluwarsa', 'Booking Kedaluwarsa',
'Halo {nama},\n\nBooking {kode_booking} untuk tanggal {tanggal_kunjungan} telah kedaluwarsa karena tidak ada pembayaran sampai batas waktu.\n\nJika masih ingin berkunjung, silakan buat booking baru.\n\nTerima kasih.',
'["nama","kode_booking","tanggal_kunjungan"]', 1),

('refund_approved', 'Refund Disetujui', 'Refund Disetujui',
'Halo {nama},\n\nPengajuan refund untuk booking {kode_booking} telah disetujui.\nJumlah: Rp {amount}\nDana akan dikembalikan ke rekening Anda dalam 1-3 hari kerja.\n\nTerima kasih.',
'["nama","kode_booking","amount"]', 1);

COMMIT;