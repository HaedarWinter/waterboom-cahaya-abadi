<?php
namespace App\Services;

use App\Core\Database;
use App\Models\Setting;

class BookingStateService {
    public const MENUNGGU_BAYAR = 'MENUNGGU_BAYAR';
    public const MENUNGGU_VERIFIKASI = 'MENUNGGU_VERIFIKASI';
    public const LUNAS = 'LUNAS';
    public const DITOLAK = 'DITOLAK';
    public const KEDALUWARSA = 'KEDALUWARSA';
    public const DIBATALKAN = 'DIBATALKAN';
    public const SELESAI = 'SELESAI';
    public const REFUND_DIAJUKAN = 'REFUND_DIAJUKAN';
    public const DIREFUND = 'DIREFUND';
    public const TIDAK_HADIR = 'TIDAK_HADIR';

    private const TRANSITIONS = [
        self::MENUNGGU_BAYAR => [self::MENUNGGU_VERIFIKASI, self::KEDALUWARSA, self::DIBATALKAN],
        self::MENUNGGU_VERIFIKASI => [self::LUNAS, self::DITOLAK, self::DIBATALKAN],
        self::DITOLAK => [self::MENUNGGU_VERIFIKASI, self::KEDALUWARSA, self::DIBATALKAN],
        self::LUNAS => [self::SELESAI, self::REFUND_DIAJUKAN, self::TIDAK_HADIR],
        self::REFUND_DIAJUKAN => [self::DIREFUND, self::LUNAS],
        self::SELESAI => [],
        self::KEDALUWARSA => [],
        self::DIBATALKAN => [],
        self::DIREFUND => [],
        self::TIDAK_HADIR => [],
    ];

    private const LABELS = [
        self::MENUNGGU_BAYAR => 'Menunggu Bayar',
        self::MENUNGGU_VERIFIKASI => 'Menunggu Verifikasi',
        self::LUNAS => 'Lunas',
        self::DITOLAK => 'Ditolak',
        self::KEDALUWARSA => 'Kedaluwarsa',
        self::DIBATALKAN => 'Dibatalkan',
        self::SELESAI => 'Selesai',
        self::REFUND_DIAJUKAN => 'Refund Diajukan',
        self::DIREFUND => 'Direfund',
        self::TIDAK_HADIR => 'Tidak Hadir',
    ];

    private const RELEASE_QUOTA_ON = [self::KEDALUWARSA, self::DIBATALKAN, self::DIREFUND];

    public static function labels(): array {
        return self::LABELS;
    }

    public static function label(?string $status): string {
        return self::LABELS[$status] ?? ($status ?? '-');
    }

    public static function badgeClass(?string $status): string {
        if ($status === self::LUNAS) return 'badge-success';
        if ($status === self::SELESAI) return 'badge-info';
        if ($status === self::MENUNGGU_BAYAR) return 'badge-warning';
        if ($status === self::MENUNGGU_VERIFIKASI) return 'badge-warning';
        if ($status === self::REFUND_DIAJUKAN) return 'badge-info';
        return 'badge-danger';
    }

    public static function allowedFrom(?string $status): array {
        return self::TRANSITIONS[$status] ?? [];
    }

    public static function canTransition(?string $from, string $to): bool {
        return in_array($to, self::allowedFrom($from), true);
    }

    public static function deadlineAt(array $booking): ?\DateTimeImmutable {
        if (empty($booking['created_at'])) return null;
        $minutes = max(1, (int)Setting::get('payment_deadline_minutes', 120));
        try {
            return (new \DateTimeImmutable($booking['created_at']))->modify("+{$minutes} minutes");
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function isExpired(array $booking): bool {
        if (!in_array($booking['status'] ?? '', [self::MENUNGGU_BAYAR, self::DITOLAK], true)) {
            return false;
        }
        $deadline = self::deadlineAt($booking);
        return $deadline !== null && $deadline < new \DateTimeImmutable('now');
    }

    /**
     * Satu-satunya jalur perubahan status booking.
     * Mengunci baris, memvalidasi transisi, melepas kuota bila terminal,
     * dan menulis riwayat dalam transaksi yang sama.
     */
    public static function transition(
        int $bookingId,
        string $to,
        ?int $actorId = null,
        ?string $actorRole = null,
        ?string $reason = null,
        ?string $qrToken = null,
        ?string $proofFilename = null
    ): array {
        if (!isset(self::LABELS[$to])) {
            return ['success' => false, 'message' => 'Status tujuan tidak dikenal.'];
        }

        $db = Database::getInstance();
        $db->begin();

        try {
            $result = $db->query("SELECT * FROM reservasi WHERE id = ? FOR UPDATE", [$bookingId], "i");
            if (!$result || $result->num_rows === 0) {
                $db->rollback();
                return ['success' => false, 'message' => 'Reservasi tidak ditemukan.'];
            }

            $booking = $result->fetch_assoc();
            $from = $booking['status'];

            if ($from === $to) {
                $db->rollback();
                return ['success' => false, 'message' => 'Status sudah ' . self::label($to) . '.'];
            }

            if (!self::canTransition($from, $to)) {
                $db->rollback();
                return [
                    'success' => false,
                    'message' => 'Transisi ' . self::label($from) . ' ke ' . self::label($to) . ' tidak diizinkan.',
                ];
            }

            if ($to === self::MENUNGGU_VERIFIKASI && $actorRole === 'PENGUNJUNG') {
                $deadline = self::deadlineAt($booking);
                if ($deadline !== null && $deadline < new \DateTimeImmutable('now')) {
                    $db->rollback();
                    return ['success' => false, 'message' => 'Batas waktu pembayaran sudah lewat.'];
                }
            }

            $token = $qrToken;
            if ($to === self::LUNAS) {
                $token = $qrToken ?: bin2hex(random_bytes(16));
            } elseif (in_array($to, [self::DITOLAK, self::DIBATALKAN, self::KEDALUWARSA, self::DIREFUND, self::TIDAK_HADIR], true)) {
                $token = null;
            } else {
                $token = $booking['qr_token'];
            }

            $catatanTolak = $to === self::DITOLAK ? $reason : null;
            $checkin = $booking['is_checkin'];
            $waktuCheckin = $booking['waktu_checkin'];
            if ($to === self::SELESAI) {
                if ((int)$booking['is_checkin'] === 1) {
                    $db->rollback();
                    return ['success' => false, 'message' => 'Tiket sudah pernah di-checkin pada ' . $booking['waktu_checkin'] . '.'];
                }
                $checkin = 1;
                $waktuCheckin = date('Y-m-d H:i:s');
            }

            $update = $db->query(
                "UPDATE reservasi
                 SET status = ?, qr_token = ?, catatan_tolak = ?, bukti_bayar = COALESCE(?, bukti_bayar),
                     is_checkin = ?, waktu_checkin = ?
                 WHERE id = ?",
                [$to, $token, $catatanTolak, $proofFilename, $checkin, $waktuCheckin, $bookingId],
                "ssssisi"
            );

            if (!$update) {
                throw new \RuntimeException('Gagal memperbarui status reservasi.');
            }

            if (in_array($to, self::RELEASE_QUOTA_ON, true)) {
                self::releaseQuota($booking);
            }

            $history = $db->query(
                "INSERT INTO booking_status_history
                    (booking_id, from_status, to_status, actor_id, actor_role, reason)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$bookingId, $from, $to, $actorId, $actorRole, $reason],
                "isssss"
            );

            if (!$history) {
                throw new \RuntimeException('Gagal menulis riwayat status.');
            }

            $db->commit();
            return [
                'success' => true,
                'message' => 'Status diubah ke ' . self::label($to) . '.',
                'from' => $from,
                'to' => $to,
            ];
        } catch (\Throwable $e) {
            $db->rollback();
            error_log('BookingStateService: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Gagal mengubah status.'];
        }
    }

    public static function submitProof(int $bookingId, string $filename, ?string $actorRole = 'PENGUNJUNG'): array {
        return self::transition(
            $bookingId,
            self::MENUNGGU_VERIFIKASI,
            null,
            $actorRole,
            'Bukti pembayaran diunggah pengunjung.',
            null,
            $filename
        );
    }

    public static function checkIn(int $bookingId, ?int $actorId = null, ?string $actorRole = null): array {
        return self::transition($bookingId, self::SELESAI, $actorId, $actorRole, 'Check-in di pintu masuk.');
    }

    /**
     * Job kedaluwarsa idempoten. Setiap kandidat dikunci ulang di transisi,
     * sehingga dua worker paralel tidak melepas kuota dua kali.
     */
    public static function expireOverdue(int $limit = 50): int {
        $db = Database::getInstance();
        $minutes = max(1, (int)Setting::get('payment_deadline_minutes', 120));
        $result = $db->query(
            "SELECT id FROM reservasi
             WHERE status IN (?, ?)
               AND metode_bayar = 'Transfer Bank'
               AND created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)
             ORDER BY created_at ASC
             LIMIT ?",
            [self::MENUNGGU_BAYAR, self::DITOLAK, $minutes, $limit],
            "ssii"
        );

        $count = 0;
        while ($result && ($row = $result->fetch_assoc())) {
            $out = self::transition(
                (int)$row['id'],
                self::KEDALUWARSA,
                null,
                'SYSTEM',
                'Otomatis kedaluwarsa: melewati batas waktu pembayaran.'
            );
            if ($out['success']) $count++;
        }
        return $count;
    }

    private static function releaseQuota(array $booking): void {
        $db = Database::getInstance();
        $total = (int)$booking['jumlah_dewasa'] + (int)$booking['jumlah_anak'];
        if ($total <= 0) return;

        $db->query(
            "UPDATE kuota_harian SET terisi = GREATEST(0, terisi - ?) WHERE tanggal = ?",
            [$total, $booking['tanggal_kunjungan']],
            "is"
        );
    }
}
