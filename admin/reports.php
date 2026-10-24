<?php
require_once __DIR__ . '/../app/init.php';

use App\Http\Controllers\Admin\ReportController;

$controller = new ReportController();
$report = $_GET['report'] ?? 'cash_flow';

match($report) {
    'cash_flow' => $controller->cashFlow(),
    'revenue' => $controller->revenue(),
    'expenses' => $controller->expenses(),
    'balance' => $controller->balance(),
    default => $controller->cashFlow()
};
