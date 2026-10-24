<?php
declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    return;
}

require_once dirname(__DIR__) . '/app/init.php';
require dirname(__DIR__) . '/app/Http/Controllers/Admin/review_handler.php';
