<?php
require_once __DIR__ . '/../app/init.php';

use App\Http\Controllers\Admin\ExpenseController;

$controller = new ExpenseController();
$action = $_GET['view'] ?? 'index';

match($action) {
    'index' => $controller->index(),
    'create' => $controller->create(),
    'show' => $controller->show(),
    'post' => $controller->post(),
    'void' => $controller->void(),
    default => $controller->index()
};