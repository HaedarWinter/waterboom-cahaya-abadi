<?php
// panggil init.php untuk autoloader, session, dan database
require_once '../app/init.php';

use App\Models\Audit;

// SEC-2: logout hanya menerima POST bertoken CSRF.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    die('Logout harus dikirim lewat form.');
}

if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('Permintaan logout tidak valid. Muat ulang halaman lalu coba lagi.');
}

// Catat aktivitas logout sebelum session dihancurkan
Audit::log('LOGOUT', 'admin', $_SESSION['admin_id'] ?? null, 'Admin telah logout.');

// bomb! semua data login (username, status) bakal kehapus dari memory server
session_destroy();

// abis kehapus, usernya dilempar balik ke halaman paling depan (index.php)
header("Location: ../?pesan=Berhasil logout. Sampai jumpa!");
exit;
