<?php
require_once __DIR__ . '/../app/init.php';

use App\Http\Controllers\Admin\NotificationController;

$controller = new NotificationController();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $method === 'POST' ? ($_POST['action'] ?? '') : ($_GET['action'] ?? 'index');

if ($method !== 'POST' && $action !== 'index') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

if ($method === 'POST' && !in_array($action, ['retry', 'process'], true)) {
    http_response_code(400);
    exit;
}

match($action) {
    'index' => $controller->index(),
    'retry' => $controller->retry(),
    'process' => $controller->processQueue(),
    default => $controller->index()
};
