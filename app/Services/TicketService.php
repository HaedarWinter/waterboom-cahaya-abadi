<?php
// app/Services/TicketService.php
namespace App\Services;

require_once __DIR__ . '/../Libraries/fpdf/fpdf.php';

use FPDF;
use App\Models\Reservasi;
use App\Models\Setting;

class TicketService {
    /**
     * Generate PDF Ticket for a booking
     * 
     * @param int $id Booking ID
     * @return string|bool Path to generated PDF or false on failure
     */
    public static function generate($id, $rawToken = null) {
        $booking = Reservasi::getById($id);
        if (!$booking) return false;

        $kode = $booking['kode_booking'];
        $qrToken = $rawToken ?: $booking['qr_token'];
        if (!$qrToken) return false;

        // QR Code URL (GoQR API)
        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?data=" . urlencode($qrToken) . "&size=300x300&color=0e7c6b";

        // Setup PDF
        // Create a custom class to handle Header/Footer if needed, or just use FPDF directly
        $pdf = new FPDF('P', 'mm', array(100, 150)); // Custom size like a physical ticket
        $pdf->AddPage();
        $pdf->SetMargins(10, 10, 10);

        // Header Background
        $pdf->SetFillColor(14, 124, 107); // #0e7c6b
        $pdf->Rect(0, 0, 100, 30, 'F');

        // Logo / Title
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, 'Cahaya Abadi', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 7);
        $pdf->Cell(0, -2, 'WATERBOOM & PARK', 0, 1, 'C');

        // Body
        $pdf->SetTextColor(31, 41, 55);
        $pdf->Ln(15);

        // QR Code
        // Attempt to place QR Code. If URL fails, we use a placeholder or log error.
        try {
            $pdf->Image($qrUrl, 25, 35, 50, 50, 'PNG');
        } catch (\Exception $e) {
            // Fallback: just text or log
            $pdf->SetXY(25, 35);
            $pdf->Cell(50, 50, '[QR CODE ERROR]', 1, 0, 'C');
        }

        // Booking Code
        $pdf->SetY(90);
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetTextColor(14, 124, 107);
        $pdf->Cell(0, 10, $kode, 0, 1, 'C');

        $pdf->SetFont('Arial', 'B', 7);
        $pdf->SetTextColor(156, 163, 175);
        $pdf->Cell(0, -2, 'KODE BOOKING', 0, 1, 'C');

        $pdf->Ln(8);

        // Info List
        $pdf->SetTextColor(75, 85, 99);
        $pdf->SetFont('Arial', '', 8);
        
        $info = [
            'Nama' => $booking['nama'],
            'Tanggal' => date('d M Y', strtotime($booking['tanggal_kunjungan'])),
            'Tiket' => $booking['jenis_tiket'],
            'Jumlah' => ($booking['jumlah_dewasa'] + $booking['jumlah_anak']) . ' Orang'
        ];

        foreach ($info as $label => $value) {
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(30, 6, $label, 0, 0);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(0, 6, ': ' . $value, 0, 1);
        }

        // Horizontal Line
        $pdf->SetDrawColor(229, 231, 235);
        $pdf->Line(10, $pdf->GetY() + 2, 90, $pdf->GetY() + 2);
        $pdf->Ln(5);

        // Footer
        $pdf->SetFont('Arial', 'I', 6);
        $pdf->SetTextColor(156, 163, 175);
        $pdf->MultiCell(0, 3, "Harap tunjukkan QR Code ini kepada petugas di pintu masuk.\nBerlaku hanya pada tanggal yang tertera.", 0, 'C');

        // Ensure directory exists
        $dir = __DIR__ . '/../../assets/tickets';
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $filename = 'Ticket_' . $kode . '.pdf';
        $path = $dir . '/' . $filename;
        
        // Output to file
        $pdf->Output('F', $path);

        return $path;
    }
}
