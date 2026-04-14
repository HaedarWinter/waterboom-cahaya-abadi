<?php
// file ini buat ngonekin php ke database mysql
// pastikan xampp udah nyala (apache sama mysql)
// parameter: nama server, username db, password db, nama database
$koneksi = mysqli_connect("localhost", "root", "", "db_ujikom");

// validasi koneksi, kalo gagal langsung munculin pesan error biar gampang debunggingnya
if (!$koneksi) {
    die("Koneksi ke database gagal woi: " . mysqli_connect_error());
}
?>
