<?php
// app/Core/Database.php
namespace App\Core;

class Database {
    private static $instance = null;
    private $koneksi;

    private function __construct() {
        // Migrasi portabel: coba koneksi standar (socket OS default) dulu,
        // fallback ke path socket XAMPP macOS bila ada. Eliminasi hardcode tunggal.
        // mode sekolah lama: gagal koneksi = false (bukan exception),
        // biar fallback ke socket XAMPP macOS masih bisa jalan.
        mysqli_report(MYSQLI_REPORT_OFF);
        $this->koneksi = mysqli_connect("localhost", "root", "", "db_ujikom");

        if (!$this->koneksi) {
            $this->koneksi = mysqli_connect("localhost", "root", "", "db_ujikom", null, "/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock");
        }

        if (!$this->koneksi) {
            // Detail internal cukup di log server; pesan ke user generik (DATA-6)
            error_log("Database connect gagal: " . mysqli_connect_error());
            die("Koneksi database gagal. Silakan coba lagi atau hubungi administrator.");
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

    public function begin() {
        return $this->koneksi->begin_transaction();
    }

    public function commit() {
        return $this->koneksi->commit();
    }

    public function rollback() {
        return $this->koneksi->rollback();
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
