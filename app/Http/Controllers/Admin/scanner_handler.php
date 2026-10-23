<?php
require_once '../../app/init.php';
use App\Models\Reservasi;
use App\Services\BookingStateService;

header('Content-Type: application/json');

// Pastikan sesi aktif dan permintaan membawa token CSRF.
require_api_session(\App\Core\Database::getInstance()->getConnection());

if (!has_canonical_permission('checkin.scan')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF tidak valid.']);
    exit;
}

$action = $_POST['action'] ?? '';
$token = $_POST['token'] ?? '';
$type = $_POST['type'] ?? 'token'; // 'token' or 'kode'

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Token tidak boleh kosong']);
    exit;
}

if ($action === 'verify') {
    $data = ($type === 'kode') 
           ? Reservasi::getByKode($token) 
           : Reservasi::getByTokenHash($token);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Tiket tidak ditemukan']);
        exit;
    }
    
    // Format date for better display
    $data['tanggal_kunjungan_raw'] = $data['tanggal_kunjungan'];
    $data['tanggal_kunjungan'] = date('d M Y', strtotime($data['tanggal_kunjungan']));
    if ($data['waktu_checkin']) {
        $data['waktu_checkin'] = date('H:i, d M Y', strtotime($data['waktu_checkin']));
    }

    echo json_encode(['success' => true, 'data' => $data]);
    exit;

} else if ($action === 'checkin') {
    $data = Reservasi::getByTokenHash($token) ?: Reservasi::getByKode($token);

    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Tiket tidak ditemukan']);
        exit;
    }

    if ($data['is_checkin']) {
        echo json_encode(['success' => false, 'message' => 'Tiket sudah pernah di-checkin pada ' . $data['waktu_checkin']]);
        exit;
    }

    if ($data['status'] !== BookingStateService::LUNAS) {
        echo json_encode(['success' => false, 'message' => 'Tiket harus berstatus Lunas (Bukan: ' . BookingStateService::label($data['status']) . ')']);
        exit;
    }

    $checkin = BookingStateService::checkIn((int)$data['id'], (int)$_SESSION['admin_id'], $_SESSION['role'] ?? null);
    if ($checkin['success']) {
        // Catat log aktivitas menggunakan Model Standar
        \App\Models\Audit::log(
            'CHECKIN', 
            'reservasi', 
            $data['id'], 
            "Memvalidasi check-in reservasi #{$data['id']} ({$data['kode_booking']})"
        );

        echo json_encode(['success' => true, 'message' => 'Check-in berhasil']);
    } else {
        echo json_encode(['success' => false, 'message' => $checkin['message']]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Aksi tidak valid']);
