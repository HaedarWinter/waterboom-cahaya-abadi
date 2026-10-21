-- ══════════════════════════════════════════════════════════════
-- ROLLBACK V3: State Machine Booking
-- ══════════════════════════════════════════════════════════════
-- PERINGATAN: rollback ini kehilangan informasi status baru.
--   REFUND_DIAJUKAN, DIREFUND, KEDALUWARSA, dan TIDAK_HADIR
--   semuanya dipetakan menjadi Dibatalkan.
--   Jalankan hanya sebelum status baru dipakai di produksi.
--   Backup dulu.
-- ══════════════════════════════════════════════════════════════
USE db_ujikom;

ALTER TABLE reservasi
    MODIFY COLUMN status VARCHAR(30) NULL DEFAULT 'MENUNGGU_BAYAR';

UPDATE reservasi
SET status = CASE
    WHEN status IN ('MENUNGGU_BAYAR', 'MENUNGGU_VERIFIKASI') THEN 'Pending'
    WHEN status = 'LUNAS' THEN 'Dikonfirmasi'
    WHEN status = 'SELESAI' THEN 'Selesai'
    WHEN status = 'DITOLAK' THEN 'Ditolak'
    WHEN status IN ('KEDALUWARSA', 'DIBATALKAN', 'REFUND_DIAJUKAN', 'DIREFUND', 'TIDAK_HADIR') THEN 'Dibatalkan'
    ELSE 'Pending'
END;

ALTER TABLE reservasi
    MODIFY COLUMN status ENUM('Pending', 'Dikonfirmasi', 'Selesai', 'Dibatalkan', 'Ditolak')
    NULL DEFAULT 'Pending';
