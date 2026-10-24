<?php
require_once __DIR__ . '/Core/Env.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');
if (in_array(getenv('APP_ENV') ?: 'local', ['demo', 'production'], true)) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
}

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
    // Custom session path untuk menghindari permission issue di macOS
    if (!is_dir(__DIR__ . '/../storage/sessions')) {
        mkdir(__DIR__ . '/../storage/sessions', 0755, true);
    }
    ini_set('session.save_path', __DIR__ . '/../storage/sessions');
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
    $configuredUrl = rtrim((string)(getenv('APP_URL') ?: ''), '/');
    if ($configuredUrl !== '') {
        return $configuredUrl . '/' . ltrim($path, '/');
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Trik paling aman: Dapatkan root path project dari __DIR__
    // app/init.php -> Naik satu level ke root project
    $project_root_abs = str_replace('\\', '/', dirname(__DIR__));
    $doc_root_abs = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    
    // Hilangkan document root dari path absolute project untuk dapat folder relative (misal /ujikom1)
    $relative_folder = str_replace($doc_root_abs, '', $project_root_abs);
    
    return $protocol . $host . $relative_folder . '/' . ltrim($path, '/');
}

function asset_url(string $path): string {
    $assetPath = ltrim($path, '/');
    $filePath = dirname(__DIR__) . '/public/' . $assetPath;
    $url = base_url($assetPath);

    if (is_file($filePath)) {
        $url .= '?v=' . (string)filemtime($filePath);
    }

    return $url;
}

function app_relative_request_path(): string {
    return \App\Core\RequestPath::relativeToBase(
        $_SERVER['REQUEST_URI'] ?? '/',
        (string)parse_url(base_url(), PHP_URL_PATH)
    );
}

// Fitur Lazy Cron: Auto-Cancel Reservasi kedaluwarsa secara otomatis
\App\Models\Reservasi::autoCancelExpired();

// Centralized middleware: enforce RBAC for admin routes
if (str_starts_with(app_relative_request_path(), '/admin/')) {
    require_once __DIR__ . '/Http/Middleware/Bootstrap.php';
}
