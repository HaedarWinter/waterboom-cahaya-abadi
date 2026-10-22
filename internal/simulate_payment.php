<?php
declare(strict_types=1);

header('Content-Type: application/json');

if (getenv('APP_ENV') !== 'demo') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Endpoint hanya tersedia di mode demo']);
    exit;
}

require_once __DIR__ . '/../app/init.php';

use App\Models\Reservasi;
use App\Services\BookingStateService;
use App\Domain\Payment\PaymentRepository;
use App\Domain\Finance\LedgerService;

$kode = trim($_POST['kode'] ?? '');

if (!$kode) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Kode booking diperlukan']);
    exit;
}

$booking = Reservasi::getByKode($kode);

if (!$booking) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Booking tidak ditemukan']);
    exit;
}

if ($booking['status'] !== BookingStateService::MENUNGGU_BAYAR) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Status booking harus MENUNGGU_BAYAR']);
    exit;
}

$db = \App\Core\Database::getInstance();
$conn = $db->getConnection();

try {
    $conn->begin_transaction();

    $paymentRepo = new PaymentRepository($conn);
    $ledgerService = new LedgerService($conn);

    $amount = (int)$booking['total_harga'];
    $paymentId = $paymentRepo->insert([
        'booking_id' => $booking['id'],
        'attempt_no' => 1,
        'method' => 'DEMO_TRANSFER',
        'amount' => $amount,
        'amount_received' => $amount,
        'status' => 'TERVERIFIKASI',
        'verified_by' => 1,
        'verified_at' => date('Y-m-d H:i:s'),
    ]);

    $ledgerService->recordPayment($paymentId, $booking['id'], $amount, 1, 'Demo simulated payment');

    BookingStateService::transition($booking['id'], BookingStateService::LUNAS, 1, 'Demo: auto-verified');

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Pembayaran disimulasikan & terverifikasi']);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
