<?php
// admin/api/notifications.php
require_once '../../app/init.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = \App\Core\Database::getInstance();

// Hitung total pending
$count = $db->query("SELECT COUNT(*) as n FROM reservasi WHERE status = 'MENUNGGU_VERIFIKASI'")->fetch_assoc()['n'] ?? 0;

// Ambil 5 terbaru
$recent = $db->query("SELECT id, kode_booking, nama, created_at FROM reservasi WHERE status = 'MENUNGGU_VERIFIKASI' ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'count' => (int)$count,
    'recent' => $recent
]);
