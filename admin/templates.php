<?php
require_once __DIR__ . '/../app/init.php';

use App\Http\Controllers\Admin\TemplateController;

$controller = new TemplateController();
$action = $_GET['action'] ?? 'index';

match($action) {
    'index' => $controller->index(),
    'edit' => $controller->edit(),
    default => $controller->index()
};
