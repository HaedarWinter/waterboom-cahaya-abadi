<?php
// app/Models/Reservasi.php
namespace App\Models;

use App\Core\Database;

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
        // Cek kuota dan penambahan counter dalam 1 transaksi terkunci (FOR UPDATE)
        // supaya dua request paralel tidak bisa sama-sama lolos cek (anti oversell).
        $db->begin();
        try {
            $lock = $db->query("SELECT kuota_max, terisi FROM kuota_harian WHERE tanggal = ? FOR UPDATE", [$data['tanggal']], "s");
            if ($lock === false) {
                $db->rollback();
                return false;
            }
            $row = $lock->fetch_assoc();
            $kuota_default = (int)Setting::get('kuota_default', 200);
            $max    = $row ? (int)$row['kuota_max'] : $kuota_default;
            $terisi = $row ? (int)$row['terisi'] : 0;

            if ($terisi + $total_orang > $max) {
                $db->rollback();
                return null; // Kuota penuh
            }

            // Generate unique code
            do {
                $kode = 'CA-' . strtoupper(bin2hex(random_bytes(3)));
                $exists = self::getByKode($kode);
            } while ($exists);

            $sql = "INSERT INTO reservasi (kode_booking, nama, no_hp, email, tanggal_kunjungan, jenis_tiket, jumlah_dewasa, jumlah_anak, total_harga, catatan, metode_bayar) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $kode, $data['nama'], $data['no_hp'], $data['email'], 
                $data['tanggal'], $data['jenis'], $data['dewasa'], 
                $data['anak'], (int)$data['total_harga'], $data['catatan'], $data['metode']
            ];

            $res = $db->query($sql, $params, "ssssssiiiss");
            
            if ($res) {
                // Update kuota terisi (di dalam transaksi yang sama, barisnya udah di-lock)
                self::tambahKuota($data['tanggal'], $total_orang);
                $db->commit();
                return $kode;
            }

            $db->rollback();
            return false;
        } catch (\Throwable $e) {
            $db->rollback();
            return false;
        }
    }

    public static function getByToken($token) {
        $db = Database::getInstance();
        $result = $db->query("SELECT * FROM reservasi WHERE qr_token = ? LIMIT 1", [$token], "s");
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

    public static function updateStatus($id, $status, $token = null, $catatan_tolak = null) {
        $db = Database::getInstance();
        
        if ($status === 'Ditolak' || $status === 'Dibatalkan') {
            $booking = self::getById($id);
            if ($booking && $booking['status'] !== 'Ditolak' && $booking['status'] !== 'Dibatalkan') {
                $total_orang = $booking['jumlah_dewasa'] + $booking['jumlah_anak'];
                self::kurangiKuota($booking['tanggal_kunjungan'], $total_orang);
            }
        }
        
        $sql = "UPDATE reservasi SET status = ?, qr_token = ?, catatan_tolak = ? WHERE id = ?";
        return $db->query($sql, [$status, $token, $catatan_tolak, $id], "sssi");
    }

    public static function checkIn($id) {
        $db = Database::getInstance();
        $sql = "UPDATE reservasi SET status = 'Selesai', is_checkin = 1, waktu_checkin = NOW() WHERE id = ?";
        return $db->query($sql, [$id], "i");
    }

    public static function autoCancelExpired() {
        $db = Database::getInstance();
        // Cari reservasi Pending yang berumur lebih dari 24 jam
        // Asumsi: 'Bayar di Lokasi' tidak kadaluarsa dalam 24 jam, batas berlakunya di hari H (ini opsional, tapi amannya kita panggil khusus Transfer Bank). Atau kita set semua yang pending 24 jam kadaluarsa? Lebih aman Transfer Bank dulu agar masuk nalar.
        $sql = "SELECT id FROM reservasi 
                WHERE status = 'Pending' 
                  AND metode_bayar = 'Transfer Bank' 
                  AND bukti_bayar IS NULL 
                  AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $result = $db->query($sql);
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                self::updateStatus($row['id'], 'Dibatalkan', null, 'Otomatis Dibatalkan: Melewati batas waktu pembayaran 24 jam');
            }
        }
    }
}
