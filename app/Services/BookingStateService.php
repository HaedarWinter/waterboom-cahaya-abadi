<?php
namespace App\Services;

use App\Core\Database;
use App\Models\Setting;

class BookingStateService {
    private static array $stateConfig = [];
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
    private static array $RELEASE_QUOTA_ON = [self::DIBATALKAN, self::KEDALUWARSA, self::DIREFUND, self::TIDAK_HADIR];

    private static function loadConfig(): array {
        if (empty(self::$stateConfig)) {
            self::$stateConfig = require __DIR__ . '/../../config/states.php';
        }
        return self::$stateConfig;
    }

    public static function labels(): array {
        $config = self::loadConfig();
        $labels = [];
        foreach ($config as $status => $data) {
            $labels[$status] = $data['label'] ?? $status;
        }
        return $labels;
    }

    public static function label(?string $status): string {
        $config = self::loadConfig();
        return $config[$status]['label'] ?? ($status ?? '-');
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
        $config = self::loadConfig();
        if (!isset($config[$status]['transitions'])) return [];
        return array_keys($config[$status]['transitions']);
    }

    public static function canTransition(?string $from, string $to): bool {
        return in_array($to, self::allowedFrom($from), true);
    }

    public static function getTransitionConfig(?string $from, string $to): ?array {
        $config = self::loadConfig();
        return $config[$from]['transitions'][$to] ?? null;
    }

    public static function deadlineAt(array $booking): ?\DateTimeImmutable {
        if (!empty($booking['payment_deadline_at'])) {
            try {
                return new \DateTimeImmutable($booking['payment_deadline_at']);
            } catch (\Throwable) {}
        }
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
        $labels = self::labels();
        if (!isset($labels[$to])) {
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
            $rawToken = null;
            if ($to === self::LUNAS) {
                $rawToken = $qrToken ?: bin2hex(random_bytes(16));
                $token = hash('sha256', $rawToken);
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

            if ($to === self::LUNAS) {
                $amount = (int)$booking['total_harga'];
                $method = match ($booking['metode_bayar'] ?? '') {
                    'Transfer Bank' => 'TRANSFER_BANK',
                    'QRIS' => 'QRIS',
                    'Bayar di Lokasi' => 'CASH_COUNTER',
                    'Lainnya' => 'LAINNYA',
                    default => 'LAINNYA',
                };

                $conn = $db->getConnection();
                $res = $db->query(
                    "SELECT id FROM payments WHERE booking_id = ? FOR UPDATE",
                    [$bookingId],
                    "i"
                );
                $paymentId = $res ? ($res->fetch_assoc()['id'] ?? null) : null;

                if ($paymentId) {
                    $db->query(
                        "UPDATE payments SET amount_received = ?, status = 'TERVERIFIKASI', verified_by = ?, verified_at = NOW(), provider_ref = ?, method = ?
                         WHERE id = ?",
                        [$amount, $actorId, $qrToken ?: null, $method, $paymentId],
                        "iissi"
                    );
                } else {
                    $db->query(
                        "INSERT INTO payments (booking_id, attempt_no, method, amount, amount_received, status, verified_by, verified_at, provider_ref)
                         VALUES (?, 1, ?, ?, ?, 'TERVERIFIKASI', ?, NOW(), ?)",
                        [$bookingId, $method, $amount, $amount, $actorId, $qrToken ?: null],
                        "isiiis"
                    );
                    $paymentId = $conn->insert_id;
                }

                $entryNo = 'LED-' . date('Ymd') . '-' . str_pad($paymentId, 6, '0', STR_PAD_LEFT);
                $db->query(
                    "INSERT INTO ledger_entries (
                        entry_no, entry_date, cash_account_id, direction, category,
                        amount, source_type, source_id, created_by, note
                    ) VALUES (?, ?, (SELECT id FROM cash_accounts WHERE code = 'KAS_001'), 'IN', 'TIKET', ?, 'PAYMENT', ?, ?, ?)",
                    [
                        $entryNo,
                        date('Y-m-d'),
                        $amount,
                        $paymentId,
                        $actorId,
                        'Pembayaran berhasil diverifikasi untuk booking #' . $bookingId
                    ],
                    "ssiiis"
                );
            } elseif ($to === self::DIREFUND) {
                $amount = (int)$booking['total_harga'];
                $conn = $db->getConnection();
                
                // Ambil payment terkait
                $resPay = $db->query("SELECT id FROM payments WHERE booking_id = ? AND status = 'TERVERIFIKASI' LIMIT 1", [$bookingId], "i");
                $paymentId = $resPay ? ($resPay->fetch_assoc()['id'] ?? null) : null;

                // Update / create entry in refunds
                $resRef = $db->query("SELECT id FROM refunds WHERE booking_id = ? AND status = 'DIAJUKAN' ORDER BY id DESC LIMIT 1", [$bookingId], "i");
                $refundId = $resRef ? ($resRef->fetch_assoc()['id'] ?? null) : null;

                if ($refundId) {
                    $db->query(
                        "UPDATE refunds SET status = 'DIREFUND', approved_by = ?, approved_at = NOW() WHERE id = ?",
                        [$actorId, $refundId],
                        "ii"
                    );
                } else {
                    $db->query(
                        "INSERT INTO refunds (booking_id, payment_id, amount, reason, status, requested_by, approved_by, approved_at)
                         VALUES (?, ?, ?, ?, 'DIREFUND', ?, ?, NOW())",
                        [$bookingId, $paymentId, $amount, $reason ?: 'Refund diproses oleh admin', $actorId, $actorId],
                        "iiisii"
                    );
                    $refundId = $conn->insert_id;
                }

                // Update payment status jika ada
                if ($paymentId) {
                    $db->query("UPDATE payments SET status = 'DIREFUND_PENUH' WHERE id = ?", [$paymentId], "i");
                }

                // Tulis buku kas (Ledger OUT - REFUND)
                $entryNo = 'LED-REF-' . date('Ymd') . '-' . str_pad($refundId, 6, '0', STR_PAD_LEFT);
                $db->query(
                    "INSERT INTO ledger_entries (
                        entry_no, entry_date, cash_account_id, direction, category,
                        amount, source_type, source_id, created_by, note
                    ) VALUES (?, ?, (SELECT id FROM cash_accounts WHERE code = 'KAS_001'), 'OUT', 'REFUND', ?, 'REFUND', ?, ?, ?)",
                    [
                        $entryNo,
                        date('Y-m-d'),
                        $amount,
                        $refundId,
                        $actorId,
                        'Refund dana untuk booking #' . $bookingId . ($reason ? ' - ' . $reason : '')
                    ],
                    "ssiiis"
                );
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
                'qr_token_raw' => $rawToken ?? null,
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
