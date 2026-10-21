<?php
// Inisialisasi session dengan parameter keamanan
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']), // Hanya kirim via HTTPS jika tersedia
        'httponly' => true,                 // Mencegah akses cookie via JavaScript (Anti-XSS)
        'samesite' => 'Lax'                // Proteksi tambahan terhadap CSRF
    ]);
    session_start();
}
/**
 * app/init.php
 * File inisialisasi aplikasi: Autoloader, Config, dan Helpers
 */

spl_autoload_register(function($class) {
    // Ubah Namespace ke Path File (misal App\Core\Database -> app/Core/Database.php)
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Load Config & Helpers asalnya
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/settings_helper.php';

// Inisialisasi Database Global
use App\Core\Database;
$db = Database::getInstance();
$koneksi = $db->getConnection(); // Tetap sedia untuk kompatibilitas kode lama

/**
 * Global Helper: e()
 * Singkatan dari htmlspecialchars untuk mempermudah output encoding (Anti-XSS)
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Global Helper: base_url()
 * Menghasilkan URL lengkap secara dinamis (mendeteksi hots, protocol, dan folder)
 * 
 * @param string $path Path tambahan (opsional)
 * @return string URL Lengkap
 */
function base_url($path = '') {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    
    // Trik paling aman: Dapatkan root path project dari __DIR__
    // app/init.php -> Naik satu level ke root project
    $project_root_abs = str_replace('\\', '/', dirname(__DIR__));
    $doc_root_abs = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    
    // Hilangkan document root dari path absolute project untuk dapat folder relative (misal /ujikom1)
    $relative_folder = str_replace($doc_root_abs, '', $project_root_abs);
    
    return $protocol . $host . $relative_folder . '/' . ltrim($path, '/');
}

// Fitur Lazy Cron: Auto-Cancel Reservasi kedaluwarsa secara otomatis
\App\Models\Reservasi::autoCancelExpired();
