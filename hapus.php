<?php
// file ini khusus dieksekusi kalo admin ngeklik tombol hapus di dashboard
// ntar panggilannya bawa id parameter di linknya (contoh: hapus.php?id=3)

// nyalain session dulu soalnya kita mau ngecek login admin
session_start();

// gerbang keamanan nih, cek kalo belum login tendang lagi ke halaman awal
// biar orang iseng gabisa asal hapus data lewat url
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit; // berentiin script
}

// panggil db
require 'koneksi.php';

// kita tangkep id yang dikirim di URL pake method GET
// (int) ini casting, biar maksain tipe datanya jadi angka, buat numpel lubang keamanan SQL Injection
$id = (int)$_GET['id'];

// ini syntax SQL buat ngehapus data (Delete di dalam CRUD)
// logikanya: hapus dari tabel buku_tamu yang ID-nya sama kayak yang kita tangkep tadi
$query = "DELETE FROM buku_tamu WHERE id='$id'";
$hapus = mysqli_query($koneksi, $query); // eksekusi deh

// ngecek jalannya sukses apa enggak
if ($hapus) {
    // balik ke dashboard admin bawa pesan keberhasilan
    header("Location: admin.php?pesan=Data berhasil dihapus!");
} else {
    // balik ke dashboard admin bawa pesan zonk
    header("Location: admin.php?pesan=Gagal menghapus data.");
}
exit; // udahan bro
?>
