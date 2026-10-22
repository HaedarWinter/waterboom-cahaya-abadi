<?php
require_once __DIR__ . '/../app/init.php';

use App\Http\Controllers\Admin\NotificationController;

$controller = new NotificationController();
$action = $_GET['action'] ?? 'index';

match($action) {
    'index' => $controller->index(),
    'retry' => $controller->retry(),
    'process' => $controller->processQueue(),
    default => $controller->index()
};