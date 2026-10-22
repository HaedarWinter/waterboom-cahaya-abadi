<?php
require_once __DIR__ . '/../app/init.php';

use App\Domain\Notification\TicketService;

$ticketService = new TicketService();
$signedUrl = $_GET['url'] ?? '';

if (!$signedUrl) {
    http_response_code(400);
    echo "Invalid ticket URL.";
    exit;
}

try {
    $html = $ticketService->downloadPDF($signedUrl);
    
    // Set headers for HTML (can print to PDF)
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: inline; filename="ticket.html"');
    echo $html;
} catch (\Throwable $e) {
    http_response_code(400);
    echo "Error: " . e($e->getMessage());
}