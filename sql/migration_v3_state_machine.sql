-- ══════════════════════════════════════════════════════════════
-- MIGRATION V3: State Machine Booking
-- ══════════════════════════════════════════════════════════════
-- Mengubah 5 status lama menjadi 10 status PRD.
--
-- Pemetaan data lama:
--   Pending tanpa bukti       -> MENUNGGU_BAYAR
--   Pending dengan bukti      -> MENUNGGU_VERIFIKASI
--   Dikonfirmasi              -> LUNAS
--   Selesai                   -> SELESAI
--   Dibatalkan                -> DIBATALKAN
--   Ditolak                   -> DITOLAK
--
-- Sifat: idempotent. Aman dijalankan ulang.
-- Prasyarat: backup database. Perubahan ini mengubah data status lama.
-- ══════════════════════════════════════════════════════════════
USE db_ujikom;

-- Kolom sementara bertipe teks agar nilai lama dan baru bisa hidup bersama.
ALTER TABLE reservasi
    MODIFY COLUMN status VARCHAR(30) NULL DEFAULT 'Pending';

UPDATE reservasi
SET status = CASE
    WHEN status = 'Pending' AND (bukti_bayar IS NULL OR bukti_bayar = '') THEN 'MENUNGGU_BAYAR'
    WHEN status = 'Pending' THEN 'MENUNGGU_VERIFIKASI'
    WHEN status = 'Dikonfirmasi' THEN 'LUNAS'
    WHEN status = 'Selesai' THEN 'SELESAI'
    WHEN status = 'Dibatalkan' THEN 'DIBATALKAN'
    WHEN status = 'Ditolak' THEN 'DITOLAK'
    ELSE COALESCE(status, 'MENUNGGU_BAYAR')
END;

ALTER TABLE reservasi
    MODIFY COLUMN status ENUM(
        'MENUNGGU_BAYAR',
        'MENUNGGU_VERIFIKASI',
        'LUNAS',
        'DITOLAK',
        'KEDALUWARSA',
        'DIBATALKAN',
        'SELESAI',
        'REFUND_DIAJUKAN',
        'DIREFUND',
        'TIDAK_HADIR'
    ) NOT NULL DEFAULT 'MENUNGGU_BAYAR';

ALTER TABLE reservasi
    ADD INDEX IF NOT EXISTS idx_reservasi_status (status);
