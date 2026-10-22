<?php
declare(strict_types=1);

/**
 * Endpoint: /cron/expire?token=...
 * Trigger kedaluwarsa booking yang melewati deadline pembayaran.
 * Idempoten: aman dijalankan berkali-kali paralel.
 * 
 * FLOW-4: Tiga pemicu:
 * (a) lazy expiry ringan di request (tidak di sini)
 * (b) endpoint ini untuk cron eksternal / layanan pemicu
 * (c) pengecekan saat booking dibuka (tidak di sini)
 */

require_once 'app/init.php';

use App\Services\BookingStateService;

header('Content-Type: application/json');

$token = $_GET['token'] ?? '';
$limit = (int)($_GET['limit'] ?? 50);

if (empty($token)) {
    http_response_code(400);
    echo json_encode(['error' => 'Token diperlukan.']);
    exit;
}

$expectedToken = hash('sha256', $_ENV['CRON_SECRET'] ?? 'change-me-in-production');
$providedToken = hash('sha256', $token);

if (!hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token tidak valid.']);
    exit;
}

if ($limit < 1 || $limit > 1000) {
    $limit = 50;
}

$count = BookingStateService::expireOverdue($limit);

echo json_encode([
    'success' => true,
    'expired' => $count,
    'timestamp' => date('Y-m-d H:i:s'),
]);
