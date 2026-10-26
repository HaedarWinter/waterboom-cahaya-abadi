<?php
// app/Core/Database.php
namespace App\Core;

class Database {
    private static $instance = null;
    private $koneksi;

    private function __construct() {
        mysqli_report(MYSQLI_REPORT_OFF);

        $environment = getenv('APP_ENV') ?: 'local';
        $host = getenv('DB_HOST') ?: '';
        $database = getenv('DB_DATABASE') ?: '';
        $user = getenv('DB_USER') ?: '';
        $password = getenv('DB_PASSWORD') ?: '';
        $configuredPort = getenv('DB_PORT');
        $port = (int)($configuredPort !== false && $configuredPort !== '' ? $configuredPort : 3306);

        if ($environment === 'test' && ($configuredPort === false || $configuredPort === '')) {
            error_log('Database test: DB_PORT wajib diisi untuk mode test.');
            die('Konfigurasi database test tidak lengkap.');
        }

        if ($host === '' || $database === '' || $user === '') {
            $fallback = $this->loadFromFile();
            if ($fallback) {
                $host = $fallback['host'] ?? $host;
                $database = $fallback['database'] ?? $database;
                $user = $fallback['user'] ?? $user;
                $password = $fallback['password'] ?? $password;
                if (isset($fallback['port'])) {
                    $port = (int)$fallback['port'];
                }
            }
        }

        if ($host === '' || $database === '' || $user === '' || $port < 1 || $port > 65535) {
            error_log('Database configuration is incomplete.');
            die('Konfigurasi database belum lengkap.');
        }
        if (in_array($environment, ['demo', 'production'], true) && $password === '') {
            error_log('Database password is required outside local/test environments.');
            die('Konfigurasi database belum lengkap.');
        }

        $this->koneksi = mysqli_connect($host, $user, $password, $database, $port);
        if (!$this->koneksi) {
            error_log('Database connection failed: ' . mysqli_connect_error());
            die('Koneksi database gagal. Silakan coba lagi atau hubungi administrator.');
        }
        $charset = getenv('DB_CHARSET') ?: 'utf8mb4';
        if (!in_array($charset, ['utf8mb4', 'utf8'], true) || !$this->koneksi->set_charset($charset)) {
            error_log('Database charset configuration failed.');
            die('Konfigurasi database tidak valid.');
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

    private function loadFromFile(): ?array {
        $filePath = dirname(__DIR__, 2) . '/config/database.php';
        if (!is_readable($filePath)) {
            return null;
        }
        return include $filePath;
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
