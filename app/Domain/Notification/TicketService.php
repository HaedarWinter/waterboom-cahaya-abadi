<?php
declare(strict_types=1);

namespace App\Domain\Notification;

use App\Core\Database;
use App\Core\Clock;
use App\Support\SignedUrl;

class TicketService {
    private $conn;

    public function __construct($connection = null) {
        $this->conn = $connection ?? Database::getInstance()->getConnection();
    }

    public function generate(string $bookingCode, string $rawToken): string {
        // Verify booking is LUNAS and get QR token
        $sql = "SELECT id, kode_booking, nama, no_hp, visit_date, total_harga, qr_token 
                FROM reservasi WHERE kode_booking = ? AND status = 'LUNAS'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('s', $bookingCode);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();

        if (!$booking) throw new \DomainException('Booking not found or not LUNAS');

        // Verify token matches
        if (!hash_equals($booking['qr_token'], hash('sha256', $rawToken))) {
            throw new \DomainException('Invalid ticket token');
        }

        // Create signed URL for PDF download
        $signedUrl = SignedUrl::sign([
            'booking_id' => $booking['id'],
            'action' => 'download_ticket',
        ], '+24 hours');

        return $signedUrl;
    }

    public function downloadPDF(string $signedUrl): string {
        $payload = SignedUrl::verify($signedUrl);
        if (!$payload || ($payload['action'] ?? '') !== 'download_ticket') {
            throw new \DomainException('Invalid or expired ticket URL');
        }

        $bookingId = (int)$payload['booking_id'];

        $sql = "SELECT r.*, GROUP_CONCAT(CONCAT(bi.qty, 'x ', tt.name, ' @ Rp ', bi.unit_price) SEPARATOR '\n') as items
                FROM reservasi r
                LEFT JOIN booking_items bi ON bi.booking_id = r.id
                LEFT JOIN ticket_types tt ON bi.ticket_type_id = tt.id
                WHERE r.id = ?
                GROUP BY r.id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();

        if (!$booking) throw new \DomainException('Booking not found');

        return $this->renderPDF($booking);
    }

    private function renderPDF(array $booking): string {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Tiket - ' . e($booking['kode_booking']) . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .ticket { border: 2px solid #0a84c6; border-radius: 10px; padding: 20px; max-width: 400px; }
                .header { text-align: center; border-bottom: 2px solid #0a84c6; padding-bottom: 15px; margin-bottom: 15px; }
                .logo { font-size: 24px; font-weight: bold; color: #0a84c6; }
                .detail { margin: 10px 0; display: flex; justify-content: space-between; }
                .label { color: #666; }
                .value { font-weight: bold; }
                .qr-placeholder { text-align: center; margin: 20px 0; padding: 20px; background: #f5f5f5; border-radius: 5px; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="ticket">
                <div class="header">
                    <div class="logo">CAHAYA ABADI</div>
                    <div>Waterpark & Resort</div>
                </div>
                <div class="detail"><span class="label">Kode Booking:</span><span class="value">' . e($booking['kode_booking']) . '</span></div>
                <div class="detail"><span class="label">Nama:</span><span class="value">' . e($booking['nama']) . '</span></div>
                <div class="detail"><span class="label">HP:</span><span class="value">' . e($booking['no_hp']) . '</span></div>
                <div class="detail"><span class="label">Tanggal Kunjungan:</span><span class="value">' . e(date('d F Y', strtotime($booking['visit_date']))) . '</span></div>
                <div class="detail"><span class="label">Total Harga:</span><span class="value">Rp ' . number_format($booking['total_harga'], 0, ',', '.') . '</span></div>
                <div class="detail"><span class="label">Status:</span><span class="value" style="color: #1f9d55;">LUNAS</span></div>
                <div class="qr-placeholder">
                    <div>📱 QR CODE</div>
                    <div style="font-size: 11px; color: #999; margin-top: 5px;">Token: ' . substr(e($booking['qr_token']), 0, 16) . '...</div>
                </div>
                <div class="footer">
                    <p>Tiket ini valid untuk 1x kunjungan pada tanggal yang tercantum.</p>
                    <p>Tunjukkan QR code ini di gerbang masuk.</p>
                    <p>Cahaya Abadi Waterpark - ' . date('Y') . '</p>
                </div>
            </div>
        </body>
        </html>';

        // In production: use Dompdf or similar to generate actual PDF
        // For now return HTML (browser can print to PDF)
        return $html;
    }
}

function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}