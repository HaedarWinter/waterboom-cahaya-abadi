<?php
/**
 * Handler: Reservasi (simpan, update status, hapus)
 * Refactored: Menggunakan Layer Models (MVC-lite)
 */
require_once __DIR__ . '/../../app/init.php';

use App\Models\Reservasi;
use App\Models\Setting;
use App\Models\Audit;
use App\Models\WhatsApp;
use App\Services\BookingStateService;

$action = $_POST['action'] ?? '';

// ── CEK KUOTA (AJAX) ──
if ($action === 'cek_kuota') {
    header('Content-Type: application/json');
    $tanggal = $_POST['tanggal'] ?? '';
    if (empty($tanggal)) {
        echo json_encode(['success' => false]);
        exit;
    }
    $kuota = Reservasi::cekKuota($tanggal);
    echo json_encode(['success' => true, 'max' => $kuota['max'], 'terisi' => $kuota['terisi'], 'sisa' => $kuota['sisa']]);
    exit;
}

// ── SIMPAN RESERVASI BARU ──
if ($action === 'simpan') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        header("Location: /ujikom1/reservasi.php?error=Token keamanan tidak valid.");
        exit;
    }
    regenerate_csrf();

    if (!check_honeypot('website_url') || !check_rate_limit('reservasi')) {
        header("Location: /ujikom1/reservasi.php?error=Deteksi spam atau terlalu banyak percobaan.");
        exit;
    }

    $data = [
        'nama'    => trim($_POST['nama'] ?? ''),
        'no_hp'   => trim($_POST['no_hp'] ?? ''),
        'email'   => trim($_POST['email'] ?? ''),
        'tanggal' => $_POST['tanggal_kunjungan'] ?? '',
        'jenis'   => $_POST['jenis_tiket'] ?? '',
        'dewasa'  => max(1, (int)($_POST['jumlah_dewasa'] ?? 1)),
        'anak'    => max(0, (int)($_POST['jumlah_anak'] ?? 0)),
        'metode'  => $_POST['metode_bayar'] ?? 'Bayar di Lokasi',
        'catatan' => trim($_POST['catatan'] ?? '')
    ];

    // Validasi & Hitung Harga
    // Validasi strict jenis tiket dari tanggal kunjungan untuk mencegah kecurangan
    $hari = date('N', strtotime($data['tanggal']));
    $data['jenis'] = ($hari >= 6) ? 'Weekend' : 'Weekday';
    
    $harga_per_orang = ($data['jenis'] === 'Weekend') ? (int)Setting::get('harga_weekend', 25000) : (int)Setting::get('harga_weekday', 20000);
    $data['total_harga'] = $harga_per_orang * ($data['dewasa'] + $data['anak']);

    // Cek Kuota
    $kuota = Reservasi::cekKuota($data['tanggal']);
    if (($data['dewasa'] + $data['anak']) > $kuota['sisa']) {
        $_SESSION['form_reservasi'] = $_POST;
        header("Location: /ujikom1/reservasi.php?error=Kuota penuh!");
        exit;
    }

    $kode = Reservasi::create($data);
    if ($kode) {
        unset($_SESSION['form_reservasi']);
        
        // WhatsApp Notification: Booking Received
        $msg = "Halo *{$data['nama']}*,\n\nReservasi Anda berhasil diterima! \nKode Booking: *{$kode}*\n\nSilakan lakukan pembayaran sebesar *Rp " . format_harga($data['total_harga']) . "* melalui metode {$data['metode']}.\n\nCek status pesanan Anda di sini:\n" . base_url("cek_booking.php?kode={$kode}");
        WhatsApp::send($data['no_hp'], $msg);

        header("Location: /ujikom1/reservasi.php?sukses=1&kode=$kode");
    } elseif ($kode === null) {
        // nil = kuota penuh saat commit (atomic check di model)
        $_SESSION['form_reservasi'] = $_POST;
        header("Location: /ujikom1/reservasi.php?error=Kuota penuh!");
    } else {
        header("Location: /ujikom1/reservasi.php?error=Gagal menyimpan reservasi.");
    }
    exit;
}

// Handler Admin lainnya (Update Status, Verifikasi, Tolak, Hapus) menggunakan model yang sama
if (in_array($action, ['update_status', 'verifikasi_bayar', 'tolak_bayar', 'hapus'])) {
    if (!isset($_SESSION['login'])) exit(header("Location: /ujikom1/login.php"));
    check_session_timeout();
    if (!has_permission('reservasi', 'manage')) exit(header("Location: /ujikom1/admin/reservasi.php?error=Akses ditolak."));
    if (!validate_csrf($_POST['csrf_token'] ?? '')) exit(header("Location: /ujikom1/admin/reservasi.php?error=Token tidak valid."));
    regenerate_csrf();

    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'update_status') {
        $status = (string)($_POST['status'] ?? '');
        $allowed = [
            BookingStateService::DIBATALKAN,
            BookingStateService::TIDAK_HADIR,
        ];
        if (!in_array($status, $allowed, true)) {
            exit(header("Location: /ujikom1/admin/reservasi.php?error=Status tujuan tidak diizinkan."));
        }

        $reason = trim($_POST['alasan'] ?? '') ?: null;
        $out = BookingStateService::transition(
            $id,
            $status,
            (int)$_SESSION['admin_id'],
            $_SESSION['role'] ?? null,
            $reason
        );
        if (!$out['success']) exit(header("Location: /ujikom1/admin/reservasi.php?error=" . urlencode($out['message'])));

        Audit::log('UPDATE_STATUS', 'reservasi', $id, "Status diubah ke {$status}. " . ($reason ?? ''));
        header("Location: /ujikom1/admin/reservasi.php?pesan=Status reservasi diperbarui.");
    } elseif ($action === 'verifikasi_bayar') {
        $token = bin2hex(random_bytes(16));
        $out = BookingStateService::transition($id, BookingStateService::LUNAS, (int)$_SESSION['admin_id'], $_SESSION['role'] ?? null, 'Pembayaran diverifikasi.', $token);
        if (!$out['success']) exit(header("Location: /ujikom1/admin/reservasi.php?error=" . urlencode($out['message'])));
        Audit::log('VERIFY_PAYMENT', 'reservasi', $id, 'Pembayaran berhasil diverifikasi.');

        // ── GENERATE & SEND PDF TICKET ──
        $booking = Reservasi::getById($id);
        if ($booking) {
            try {
                // Generate PDF Ticket
                $pdfPath = \App\Services\TicketService::generate($id);
                
                if ($pdfPath && file_exists($pdfPath)) {
                    // WhatsApp Notification with PDF Attachment + Fallback Link
                    $msg = "Halo *{$booking['nama']}*,\n\nReservasi Anda *TELAH DIKONFIRMASI*! ✅\nKode Booking: *{$booking['kode_booking']}*\n\nBerikut adalah Tiket Digital Anda. Silakan simpan dan tunjukkan kepada petugas di pintu masuk.\n\nJika file tidak muncul, Anda bisa melihat tiket di sini:\n" . base_url("cek_booking.php?kode={$booking['kode_booking']}");
                    \App\Models\WhatsApp::sendFile($booking['no_hp'], $pdfPath, $msg);
                } else {
                    throw new \Exception("Gagal generate file PDF.");
                }
            } catch (\Exception $e) {
                // Log the error
                \App\Models\Audit::log('TICKET_ERROR', 'reservasi', $id, "Gagal kirim tiket PDF: " . $e->getMessage());
                
                // Fallback to text message if PDF generation fails
                $msg = "Halo *{$booking['nama']}*,\n\nReservasi Anda *TELAH DIKONFIRMASI*! ✅\nKode Booking: *{$booking['kode_booking']}*\n\nSilakan tunjukkan QR Code Anda di pintu masuk:\n" . base_url("cek_booking.php?kode={$booking['kode_booking']}");
                \App\Models\WhatsApp::send($booking['no_hp'], $msg);
            }
        }

        header("Location: /ujikom1/admin/reservasi.php?pesan=Pembayaran terverifikasi & Tiket telah dikirim!");
    } elseif ($action === 'tolak_bayar') {
        $alasan = trim($_POST['alasan'] ?? 'Bukti tidak jelas');
        $out = BookingStateService::transition($id, BookingStateService::DITOLAK, (int)$_SESSION['admin_id'], $_SESSION['role'] ?? null, $alasan);
        if (!$out['success']) exit(header("Location: /ujikom1/admin/reservasi.php?error=" . urlencode($out['message'])));
        Audit::log('REJECT_PAYMENT', 'reservasi', $id, "Pembayaran ditolak. Alasan: $alasan");

        // WhatsApp Notification: Rejected
        $booking = Reservasi::getById($id);
        if ($booking) {
            $msg = "Halo *{$booking['nama']}*,\n\nMohon maaf, pembayaran untuk kode booking *{$booking['kode_booking']}* kami tolak karena: *{$alasan}*.\n\nSilakan upload ulang bukti pembayaran yang valid melalui link ini:\n" . base_url("cek_booking.php?kode={$booking['kode_booking']}");
            WhatsApp::send($booking['no_hp'], $msg);
        }

        header("Location: /ujikom1/admin/reservasi.php?pesan=Pembayaran ditolak.");
    } elseif ($action === 'hapus') {
        exit(header("Location: /ujikom1/admin/reservasi.php?error=Reservasi tidak boleh dihapus. Gunakan pembatalan agar riwayat tetap utuh."));
    }
    exit;
}

header("Location: /ujikom1/");
exit;
