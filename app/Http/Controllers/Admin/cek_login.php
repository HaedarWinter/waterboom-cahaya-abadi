<?php
// ini file backend khusus buat ngecek bener ga nih si admin yang login
// display_errors sengaja mati — detail error hanya di log server (DATA-6)

// panggil init.php untuk autoloader, session, dan database
require_once __DIR__ . '/../../../../app/init.php';

use App\Models\Audit;

// penjaga gawang: kalo misalnya ga dikirim lewat method POST dari form (misal orang iseng nembak url), diusir balik ke halaman login
if (!isset($_POST['username']) || !isset($_POST['password'])) {
    header("Location: /ujikom1/login.php");
    exit; // usir
}

// ─── BRUTE FORCE PROTECTION ───
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$login_username = trim($_POST['username'] ?? '');
$fifteen_mins_ago = date('Y-m-d H:i:s', strtotime('-15 minutes'));

// ─── BRUTE FORCE PROTECTION ───
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$login_username = trim($_POST['username'] ?? '');
$fifteen_mins_ago = date('Y-m-d H:i:s', strtotime('-15 minutes'));

// Cek jumlah percobaan gagal dalam 15 menit terakhir
$stmt_check = \App\Core\Database::getInstance()->getConnection()->prepare("SELECT COUNT(*) as attempts FROM login_attempts WHERE (ip_address = ? OR username = ?) AND attempt_time > ?");
$stmt_check->bind_param("sss", $ip, $login_username, $fifteen_mins_ago);
$stmt_check->execute();
$res = $stmt_check->get_result();
$attempts = $res ? $res->fetch_assoc()['attempts'] : 0;

if ($attempts >= 5) {
    Audit::log('LOGIN_BLOCKED', null, null, "Percobaan login diblokir karena terlalu banyak kegagalan untuk: $login_username");
    header("Location: /ujikom1/login.php?error=Terlalu banyak percobaan gagal. Silakan coba lagi dalam 15 menit.");
    exit;
}

// ─── VALIDASI CSRF TOKEN ───
// TEMPORARY: Disabled untuk compatibility dengan session storage migration
// TODO: Re-enable setelah session path stabil
// if (!validate_csrf($_POST['csrf_token'] ?? '')) {
//     header("Location: /ujikom1/login.php?error=Token keamanan tidak valid. Silakan coba lagi.");
//     exit;
// }
// regenerate_csrf();

// tangkep data yang ditik di form
// karna kita pake prepared statement gak usah pake escape string lagi
$username = trim($_POST['username']);
$password = $_POST['password'];

// query sql buat nyari username nya di tabel admin pake prepared statement + LIMIT 1 buat optimalisasi
$stmt = \App\Core\Database::getInstance()->getConnection()->prepare("SELECT * FROM admin WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

// cek kalo misal kodenya bermasalah pas nyari ke db, dimatiin aja layarnya trus kasih tau apanya yang salah
    if (!$result) {
        die("Error di query nya boss: " . \App\Core\Database::getInstance()->getConnection()->error);
    }

    // kalo usernamenya dapet alias ada 1 data yang match di db
    if ($result->num_rows == 1) {
        // kita pretelin baris datanya dari database masukin ke variabel $row
        $row = $result->fetch_assoc();
        // ambil sandinya
        $db_pass = $row['password'];

        // 1. Cek pake teknik kekinian: verify hash password buat tingkat keamanan tinggi (hashing)
        // Fallback plaintext SENGAJA dihapus (AUTH-1): kolom password wajib hash bcrypt.
        if (password_verify($password, $db_pass)) {
        // Cek status aktif
        if ($row['status'] === 'Nonaktif') {
            header("Location: /ujikom1/login.php?error=Akun Anda telah dinonaktifkan. Silakan hubungi Super Admin.");
            exit;
        }
        // BERHASIL: Bersihkan percobaan login yang gagal
        $stmt_clear = \App\Core\Database::getInstance()->getConnection()->prepare("DELETE FROM login_attempts WHERE ip_address = ? OR username = ?");
        $stmt_clear->bind_param("ss", $ip, $login_username);
        $stmt_clear->execute();

        // Sesuaikan Session (Regenerate ID untuk keamanan)
        session_regenerate_id(true);

        $_SESSION['login']         = true;
        $_SESSION['admin_id']      = $row['id'];
        $_SESSION['username']      = $row['username'];
        $_SESSION['email']         = $row['email'];
        $_SESSION['role']          = $row['role'];
        $_SESSION['status']        = $row['status'];
        $_SESSION['permissions']   = json_decode($row['permissions'], true);
        $_SESSION['last_activity'] = time();

        Audit::log('LOGIN', 'admin', $row['id'], 'Admin berhasil login.');

        header("Location: /ujikom1/admin/?pesan=Login berhasil! Selamat datang, " . htmlspecialchars($username) . ".");
        exit;
    }
}

// nah skenario terakhir kalo misal passowrd ato usernamenya kaga ada / salah
// simpan form yang tadi diketik biar masuk di variable $old login.php
$_SESSION['login_data'] = $_POST;

// ya dibalikin lagi ke login ditambahin peringatan errornya
// GAGAL: Catat percobaan login
$stmt_fail = \App\Core\Database::getInstance()->getConnection()->prepare("INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)");
$stmt_fail->bind_param("ss", $ip, $login_username);
$stmt_fail->execute();

Audit::log('LOGIN_FAIL', null, null, "Percobaan login gagal untuk username: $login_username");
header("Location: /ujikom1/login.php?error=Username atau password salah!");
exit;
?>
