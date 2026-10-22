<?php
/**
 * Legacy wrapper for characterization tests.
 * Runs a single legacy request in isolation, capturing output.
 *
 * USAGE: php legacy_wrapper.php html '{"bulan":"01","tahun":"2025"}'
 *        php legacy_wrapper.php csv  '{"action":"export_csv","kategori":"BIAYA_OPERASIONAL"}'
 *
 * Environment: APP_ENV=test DB_DATABASE=db_ujikom_test must be set before running.
 */
declare(strict_types=1);

// Suppress warnings and deprecated errors for clean capture
ini_set('display_errors', 'stderr');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

putenv('APP_ENV=test');
putenv('DB_DATABASE=db_ujikom_test');
$_SERVER['APP_ENV'] = 'test';
$_SERVER['DB_DATABASE'] = 'db_ujikom_test';
$_ENV['APP_ENV'] = 'test';
$_ENV['DB_DATABASE'] = 'db_ujikom_test';
$_SERVER['HTTP_HOST'] = 'localhost';

require __DIR__ . '/../app/init.php';

ob_start();
function capture_shutdown_output(): void {
    $output = ob_get_clean();
    if ($output !== false) {
        echo $output;
    }
}
register_shutdown_function('capture_shutdown_output');

$mode = $argv[1] ?? 'html';
$paramsJson = $argv[2] ?? '{}';
$params = json_decode($paramsJson, true);

if ($mode === 'html') {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = $params;
} else {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $params;
}

// Setup test session with finance permission
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = [
    'admin_id'    => 1,
    'username'    => 'test',
    'role'        => 'Finance',
    'permissions' => [
        'finance' => ['view' => true],
    ],
    'last_activity' => time(),
];

// Generate CSRF token for POST
if ($mode === 'csv') {
    require_once __DIR__ . '/../config/security.php';
    $_POST['csrf_token'] = csrf_token();
}

// Change to directory of target file for relative includes
$target = $mode === 'html'
    ? __DIR__ . '/../admin/buku_kas.php'
    : __DIR__ . '/../admin/handlers/buku_kas_handler.php';
chdir(dirname($target));

// Capture output (legacy scripts echo/exit directly)
ob_start();
require $target;
$output = ob_get_clean();

echo $output;