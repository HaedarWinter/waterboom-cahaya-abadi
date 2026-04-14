<?php
// ini file backend khusus buat ngecek bener ga nih si admin yang login
// sementara nyalain error reporting biar kalo ada yang error pas develop langsung keliatan
error_reporting(E_ALL);
ini_set('display_errors', 1);

// panggil session karena abis login kita mau nulis data sesi biar adminnya awet (ga minta login mulu)
session_start();
require 'koneksi.php';

// penjaga gawang: kalo misalnya ga dikirim lewat method POST dari form (misal orang iseng nembak url), diusir balik ke halaman login
if (!isset($_POST['username']) || !isset($_POST['password'])) {
    header("Location: login.php");
    exit; // usir
}

// tangkep data yang ditik di form
// buat username kita saring pake escape string biar aman dari sql injection
$username = mysqli_real_escape_string($koneksi, $_POST['username']);
// tapi buat password gausah di saring, takutnya kalo di saring malah merusak password aslinya yg emang pake simbol
$password = $_POST['password'];

// query sql buat nyari username nya di tabel admin
$query = "SELECT * FROM admin WHERE username='$username'";
$result = mysqli_query($koneksi, $query); // mari kita cari

// cek kalo misal kodenya bermasalah pas nyari ke db, dimatiin aja layarnya trus kasih tau apanya yang salah
if (!$result) {
    die("Error di query nya boss: " . mysqli_error($koneksi));
}

// kalo usernamenya dapet alias ada 1 data yang match di db
if (mysqli_num_rows($result) == 1) {
    // kita pretelin baris datanya dari database masukin ke variabel $row
    $row = mysqli_fetch_assoc($result);
    // ambil sandinya
    $db_pass = $row['password'];

    // 1. Cek pake teknik kekinian: verify hash password buat tingkat keamanan tinggi (hashing)
    if (password_verify($password, $db_pass)) {
        // kalo lolos trus cocok masuk sini nih, bikin 'karcis' tanda dia sah jadi admin
        $_SESSION['login'] = true;
        $_SESSION['username'] = $username;
        // cuss arahin ke dashboard
        header("Location: admin.php");
        exit;
    } 
    // 2. Ini kode jaga-jaga (fallback) kalo misal data perdana di db masi text tulisan biasa kayak "admin123" belom dirahasiain
    else if ($password === $db_pass) {
        $new_hash = password_hash($password, PASSWORD_DEFAULT);
        // sandi dia yang tulisan biasa langsung ditiban ke kode rahasia yg amann
        mysqli_query($koneksi, "UPDATE admin SET password='$new_hash' WHERE username='$username'");
        
        // langsung kasi akses juga
        $_SESSION['login'] = true;
        $_SESSION['username'] = $username;
        header("Location: admin.php");
        exit; // udahan
    }
}

// nah skenario terakhir kalo misal passowrd ato usernamenya kaga ada / salah
// ya dibalikin lagi ke login ditambahin peringatan errornya
header("Location: login.php?error=Username atau password salah!");
exit; // bubar
?>
