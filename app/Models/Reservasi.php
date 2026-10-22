<?php
// app/Models/Reservasi.php
namespace App\Models;

use App\Core\Database;
use App\Services\BookingStateService;

class Reservasi {
    public static function getByKode($kode) {
        $db = Database::getInstance();
        $result = $db->query("SELECT * FROM reservasi WHERE kode_booking = ? LIMIT 1", [$kode], "s");
        return $result ? $result->fetch_assoc() : null;
    }

    public static function getById($id) {
        $db = Database::getInstance();
        $result = $db->query("SELECT * FROM reservasi WHERE id = ? LIMIT 1", [$id], "i");
        return $result ? $result->fetch_assoc() : null;
    }

    public static function cekKuota($tanggal) {
        $db = Database::getInstance();
        $kuota_default = Setting::get('kuota_default', 200);
        
        $result = $db->query("SELECT * FROM kuota_harian WHERE tanggal = ?", [$tanggal], "s");
        $row = $result->fetch_assoc();
        
        $max = $row ? $row['kuota_max'] : $kuota_default;
        $terisi = $row ? $row['terisi'] : 0;
        
        return [
            'max' => (int)$max,
            'terisi' => (int)$terisi,
            'sisa' => (int)($max - $terisi)
        ];
    }

    public static function create($data) {
        $db = Database::getInstance();
        $total_orang = $data['dewasa'] + $data['anak'];
        
        // ─── ATOMIK (RACE-2): transaksi + row lock ───
        $db->begin();
        try {
            $kuota_default = (int)Setting::get('kuota_default', 200);
            $ensure = $db->query(
                "INSERT INTO kuota_harian (tanggal, kuota_max, terisi) VALUES (?, ?, 0)
                 ON DUPLICATE KEY UPDATE tanggal = VALUES(tanggal)",
                [$data['tanggal'], $kuota_default],
                "si"
            );
            if ($ensure === false) {
                $db->rollback();
                return false;
            }

            $lock = $db->query("SELECT kuota_max, terisi FROM kuota_harian WHERE tanggal = ? FOR UPDATE", [$data['tanggal']], "s");
            if ($lock === false) {
                $db->rollback();
                return false;
            }
            $row = $lock->fetch_assoc();
            $max    = $row ? (int)$row['kuota_max'] : $kuota_default;
            $terisi = $row ? (int)$row['terisi'] : 0;

            if ($terisi + $total_orang > $max) {
                $db->rollback();
                return null;
            }

            do {
                $kode = 'CA-' . strtoupper(bin2hex(random_bytes(3)));
                $exists = self::getByKode($kode);
            } while ($exists);

            $minutes = max(1, (int)Setting::get('payment_deadline_minutes', 120));
            $paymentDeadline = (new \DateTimeImmutable())->modify("+{$minutes} minutes")->format('Y-m-d H:i:s');
            $channel = $data['metode'] === 'Bayar di Lokasi' ? 'COUNTER' : 'ONLINE';
            $visitDate = $data['tanggal'];

            $hari = date('N', strtotime($data['tanggal']));
            $jenis = ($hari >= 6) ? 'Weekend' : 'Weekday';

            $ticketTypes = [
                'dewasa' => ($jenis === 'Weekend') ? 'ADULT_WEEKEND' : 'ADULT_WEEKDAY',
                'anak' => ($jenis === 'Weekend') ? 'CHILD_WEEKEND' : 'CHILD_WEEKDAY',
            ];

            $items = [];
            $totalHarga = 0;

            if ($data['dewasa'] > 0) {
                $priceRow = $db->query(
                    "SELECT price FROM ticket_prices WHERE ticket_type_id = (SELECT id FROM ticket_types WHERE code = ?) 
                     AND effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?) 
                     ORDER BY effective_from DESC LIMIT 1",
                    [$ticketTypes['dewasa'], $visitDate, $visitDate],
                    "ss"
                );
                $unitPrice = $priceRow && ($r = $priceRow->fetch_assoc()) ? (int)$r['price'] 
                    : (int)Setting::get('harga_weekend', 25000);
                if ($jenis === 'Weekday') {
                    $unitPrice = $priceRow && ($r = $priceRow->fetch_assoc()) ? (int)$r['price'] 
                        : (int)Setting::get('harga_weekday', 20000);
                }
                $subtotal = $unitPrice * $data['dewasa'];
                $items[] = ['ticket_type' => $ticketTypes['dewasa'], 'qty' => $data['dewasa'], 'unit_price' => $unitPrice, 'subtotal' => $subtotal];
                $totalHarga += $subtotal;
            }

            if ($data['anak'] > 0) {
                $priceRow = $db->query(
                    "SELECT price FROM ticket_prices WHERE ticket_type_id = (SELECT id FROM ticket_types WHERE code = ?) 
                     AND effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?) 
                     ORDER BY effective_from DESC LIMIT 1",
                    [$ticketTypes['anak'], $visitDate, $visitDate],
                    "ss"
                );
                $unitPrice = $priceRow && ($r = $priceRow->fetch_assoc()) ? (int)$r['price'] 
                    : (int)Setting::get('harga_weekend', 25000);
                if ($jenis === 'Weekday') {
                    $unitPrice = $priceRow && ($r = $priceRow->fetch_assoc()) ? (int)$r['price'] 
                        : (int)Setting::get('harga_weekday', 20000);
                }
                $subtotal = $unitPrice * $data['anak'];
                $items[] = ['ticket_type' => $ticketTypes['anak'], 'qty' => $data['anak'], 'unit_price' => $unitPrice, 'subtotal' => $subtotal];
                $totalHarga += $subtotal;
            }

            $res = $db->query(
                "INSERT INTO reservasi 
                (kode_booking, nama, no_hp, email, tanggal_kunjungan, visit_date, jenis_tiket, jumlah_dewasa, jumlah_anak, total_harga, catatan, metode_bayar, channel, payment_deadline_at, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'MENUNGGU_BAYAR')",
                [
                    $kode, $data['nama'], $data['no_hp'], $data['email'],
                    $data['tanggal'], $visitDate, $jenis, $data['dewasa'],
                    $data['anak'], $totalHarga, $data['catatan'], $data['metode'], $channel, $paymentDeadline
                ],
                "sssssssiissiiss"
            );

            if ($res) {
                $bookingId = $db->getConnection()->insert_id;
                $bookingId = (int)$bookingId;
                
                foreach ($items as $item) {
                    $typeId = $db->query("SELECT id FROM ticket_types WHERE code = ?", [$item['ticket_type']], "s");
                    $typeRow = $typeId ? $typeId->fetch_assoc() : null;
                    if ($typeRow) {
                        $db->query(
                            "INSERT INTO booking_items (booking_id, ticket_type_id, qty, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)",
                            [$bookingId, (int)$typeRow['id'], $item['qty'], $item['unit_price'], $item['subtotal']],
                            "iiiii"
                        );
                    }
                }

                self::tambahKuota($data['tanggal'], $total_orang);
                $db->commit();
                return $kode;
            }

            $db->rollback();
            return false;
        } catch (\Throwable $e) {
            $db->rollback();
            error_log('Reservasi::create: ' . $e->getMessage());
            return false;
        }
    }

    public static function getByToken($token) {
        $db = Database::getInstance();
        $hash = hash('sha256', $token);
        $result = $db->query("SELECT * FROM reservasi WHERE qr_token = ? LIMIT 1", [$hash], "s");
        return $result ? $result->fetch_assoc() : null;
    }
    
    public static function getByTokenHash($hash) {
        $db = Database::getInstance();
        $result = $db->query("SELECT * FROM reservasi WHERE qr_token = ? LIMIT 1", [$hash], "s");
        return $result ? $result->fetch_assoc() : null;
    }
    
    public static function getByKodeAndHpLast4($kode, $hp_last4) {
        $db = Database::getInstance();
        $result = $db->query("
            SELECT * FROM reservasi 
            WHERE kode_booking = ? AND no_hp LIKE CONCAT('%', SUBSTRING(?, -4), '%')
            LIMIT 1", 
            [$kode, $hp_last4],
            "si"
        );
        return $result ? $result->fetch_assoc() : null;
    }

    public static function tambahKuota($tanggal, $jumlah) {
        $db = Database::getInstance();
        $kuota_default = (int)Setting::get('kuota_default', 200);
        $sql = "INSERT INTO kuota_harian (tanggal, kuota_max, terisi) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE terisi = terisi + ?";
        return $db->query($sql, [$tanggal, $kuota_default, $jumlah, $jumlah], "siii");
    }

    public static function kurangiKuota($tanggal, $jumlah) {
        $db = Database::getInstance();
        $sql = "UPDATE kuota_harian SET terisi = GREATEST(0, terisi - ?) WHERE tanggal = ?";
        return $db->query($sql, [$jumlah, $tanggal], "is");
    }

    public static function updateStatus($id, $status, $token = null, $catatan_tolak = null, $reason = null) {
        $actorId = $_SESSION['admin_id'] ?? null;
        $actorRole = $_SESSION['role'] ?? 'SYSTEM';
        $out = BookingStateService::transition(
            (int)$id,
            $status,
            $actorId !== null ? (int)$actorId : null,
            $actorRole,
            $reason ?? $catatan_tolak,
            $token
        );
        return $out['success'];
    }

    public static function checkIn($id, $actorId = null, $actorRole = null) {
        $out = BookingStateService::checkIn((int)$id, $actorId, $actorRole);
        return $out['success'];
    }

    public static function autoCancelExpired() {
        return BookingStateService::expireOverdue();
    }
}
