<?php
// Set zona waktu ke Indonesia Barat (WIB)
date_default_timezone_set('Asia/Jakarta');

// file ini buat ngonekin php ke database mysql
// pastikan xampp udah nyala (apache sama mysql)
//
// SATU sumber kebenaran: parameter koneksi hanya ditulis di App\Core\Database.
// File lama yang masih require config/koneksi.php otomatis dapat koneksi yang sama.
require_once __DIR__ . '/../app/Core/Database.php';

// validasi koneksi, kalo gagal langsung munculin pesan error biar gampang debubuggingnya
// (pesan detail internal di-log ke error_log, bukan ke layar — DATA-6)
$koneksi = \App\Core\Database::getInstance()->getConnection();

if (!$koneksi) {
    error_log("koneksi.php gagal memperoleh koneksi database.");
    die("Koneksi ke database gagal. Silakan coba lagi nanti.");
}
?>
