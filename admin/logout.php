<?php
// panggil init.php untuk autoloader, session, dan database
require_once '../app/init.php';

use App\Models\Audit;

// Catat aktivitas logout sebelum session dihancurkan
Audit::log('LOGOUT', 'admin', $_SESSION['admin_id'] ?? null, 'Admin telah logout.');

// bomb! semua data login (username, status) bakal kehapus dari memory server
session_destroy();

// abis kehapus, usernya dilempar balik ke halaman paling depan (index.php)
header("Location: /ujikom1/?pesan=Berhasil logout. Sampai jumpa!");
exit;
?>
