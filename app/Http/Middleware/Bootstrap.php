<?php
declare(strict_types=1);

use App\Http\RouteRegistry;
use App\Http\Middleware\RequirePermission;

$projectRoot = dirname(__DIR__, 3);
$routes = require $projectRoot . '/config/routes_rbac.php';
foreach ($routes as $path => $permission) {
    RouteRegistry::register($path, $permission);
}

$currentPath = trim(app_relative_request_path(), '/');
if ($currentPath === 'admin') {
    $currentPath = 'admin/index.php';
}

if (str_starts_with($currentPath, 'admin/')) {
    $required = RouteRegistry::getRequired($currentPath);

    if ($required === null && str_ends_with($currentPath, '.php')) {
        http_response_code(403);
        exit('Akses ditolak.');
    }

    if ($required === '@authenticated' && !isset($_SESSION['admin_id'])) {
        http_response_code(401);
        exit('Sesi admin tidak aktif.');
    }

    if ($required !== null && $required !== '@authenticated' && !has_canonical_permission($required)) {
        http_response_code(403);
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Akses ditolak: izin tidak cukup.']);
        } else {
            header('Location: /ujikom1/admin/?error=Akses ditolak: izin tidak cukup.');
        }
        exit;
    }

    if (getenv('APP_ENV') === 'demo'
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        && $currentPath !== 'admin/logout.php') {
        http_response_code(403);
        exit('Mode demo hanya dapat digunakan untuk melihat halaman.');
    }
}
