<?php
// app/Core/Database.php
namespace App\Core;

class Database {
    private static $instance = null;
    private $koneksi;

    private function __construct() {
        // Menggunakan parameter dari koneksi asli Anda
        $this->koneksi = mysqli_connect("localhost", "root", "", "db_ujikom", null, "/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock");
        
        if (!$this->koneksi) {
            die("Koneksi Database Gagal: " . mysqli_connect_error());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->koneksi;
    }

    // Helper untuk Prepared Statement (Best Practice)
    public function query($sql, $params = [], $types = "") {
        if (empty($params)) {
            return $this->koneksi->query($sql);
        }

        $stmt = $this->koneksi->prepare($sql);
        if (!$stmt) return false;

        if ($params) {
            $stmt->bind_param($types, ...$params);
        }

        $success = $stmt->execute();
        $result = $stmt->get_result();
        
        // Jika kueri adalah SELECT, kembalikan result set. 
        // Jika DML (INSERT/UPDATE/DELETE), kembalikan status eksekusi (true/false)
        return ($result !== false) ? $result : $success;
    }
}
