<?php
require_once __DIR__ . '/../app/init.php';

use App\Http\Controllers\Admin\ClosingController;

$controller = new ClosingController();
$action = $_GET['view'] ?? 'index';

match($action) {
    'index' => $controller->index(),
    'form' => $controller->perform(),
    'reopen' => $controller->reopen(),
    default => $controller->index()
};