<?php
// app/Core/Database.php
namespace App\Core;

class Database {
    private static $instance = null;
    private $koneksi;

    private function __construct() {
        mysqli_report(MYSQLI_REPORT_OFF);

        if (getenv('APP_ENV') === 'test') {
            // Mode tes: satu konfigurasi eksplisit saja (env). Tanpa fallback,
            // supaya tes tidak bisa jatuh ke server DB dev (lihat docs/audit/test-setup.md).
            $dbName = getenv('DB_DATABASE') ?: '';
            $host = getenv('DB_HOST') ?: '';
            $port = (int)(getenv('DB_PORT') ?: 0);
            $user = getenv('DB_USER') ?: '';
            $pass = getenv('DB_PASSWORD') ?: '';

            if ($dbName === '' || $host === '' || $port === 0) {
                error_log("Database test: DB_HOST/DB_PORT/DB_DATABASE wajib diisi untuk mode test.");
                die("Konfigurasi database test tidak lengkap.");
            }

            $this->koneksi = mysqli_connect($host, $user, $pass, $dbName, $port);

            if (!$this->koneksi) {
                error_log("Database test connect gagal: " . mysqli_connect_error());
                die("Koneksi database test gagal.");
            }
            return;
        }

        // Mode web/dev: koneksi standar dengan socket XAMPP macOS
        $this->koneksi = mysqli_connect("localhost", "root", "", 'db_ujikom', 3306, "/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock");

        if (!$this->koneksi) {
            $this->koneksi = mysqli_connect("localhost", "root", "", 'db_ujikom');
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
