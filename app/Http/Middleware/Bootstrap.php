<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/init.php';

use App\Http\RouteRegistry;
use App\Http\Middleware\RequirePermission;

$routes = require __DIR__ . '/../../config/routes_rbac.php';
foreach ($routes as $path => $permission) {
    RouteRegistry::register($path, $permission);
}

$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$currentPath = ltrim($currentPath, '/');

if (str_starts_with($currentPath, 'admin/')) {
    $relativePath = $currentPath;
    $required = RouteRegistry::getRequired($relativePath);
    
    if ($required !== null && !has_canonical_permission($required)) {
        http_response_code(403);
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Akses ditolak: izin tidak cukup.']);
        } else {
            header('Location: /ujikom1/admin/?error=Akses ditolak: izin tidak cukup.');
        }
        exit;
    }
}