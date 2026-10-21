<?php
require_once '../../app/init.php';
use App\Models\Reservasi;

header('Content-Type: application/json');

// Pastikan session valid
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
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
           : Reservasi::getByToken($token);
    
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
    // Cari data dulu buat pastiin exist
    $data = Reservasi::getByToken($token) ?: Reservasi::getByKode($token);

    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Tiket tidak ditemukan']);
        exit;
    }

    if ($data['is_checkin']) {
        echo json_encode(['success' => false, 'message' => 'Tiket sudah pernah di-checkin pada ' . $data['waktu_checkin']]);
        exit;
    }

    if ($data['status'] !== 'Dikonfirmasi') {
        echo json_encode(['success' => false, 'message' => 'Tiket harus dalam status Dikonfirmasi (Bukan: ' . $data['status'] . ')']);
        exit;
    }

    if (Reservasi::checkIn($data['id'])) {
        // Catat log aktivitas menggunakan Model Standar
        \App\Models\Audit::log(
            'CHECKIN', 
            'reservasi', 
            $data['id'], 
            "Memvalidasi check-in reservasi #{$data['id']} ({$data['kode_booking']})"
        );

        echo json_encode(['success' => true, 'message' => 'Check-in berhasil']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupdate database']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Aksi tidak valid']);
