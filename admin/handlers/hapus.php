<?php
// file ini khusus dieksekusi kalo admin ngeklik tombol hapus di dashboard
// ntar panggilannya bawa id parameter di linknya (contoh: hapus.php?id=3)

// nyalain session dulu soalnya kita mau ngecek login admin
session_start();

// gerbang keamanan nih, cek kalo belum login tendang lagi ke halaman awal
// biar orang iseng gabisa asal hapus data lewat url
if (!isset($_SESSION['login'])) {
    header("Location: /ujikom1/login.php");
    exit;
}

require '../../config/koneksi.php';
require '../../config/security.php';
check_session_timeout();

// ─── HANYA POST (anti link hapus cepak nempel di email/aman CSRF) ───
// GET tidak boleh mengubah data — patokan standar untuk aksi destruktif.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /ujikom1/admin/?error=Metode tidak diizinkan.");
    exit;
}

// ─── PROTEKSI PERMISSION ───
if (!has_permission('content', 'delete')) {
    header("Location: /ujikom1/admin/?error=Anda tidak memiliki izin untuk menghapus data pengunjung.");
    exit;
}

// ─── PROTEKSI CSRF (token satu kali pakai, diset saat render form) ───
if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    header("Location: /ujikom1/admin/?error=Token keamanan tidak valid. Silakan coba lagi.");
    exit;
}

// kita tangkep id yang dikirim lewat POST pake method POST
// (int) ini casting, biar maksain tipe datanya jadi angka, buat numpel lubang keamanan SQL Injection
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    header("Location: /ujikom1/admin/?error=ID tidak valid.");
    exit;
}

// ini syntax SQL buat ngehapus data (Delete di dalam CRUD) pake prepared statement
$stmt = $koneksi->prepare("DELETE FROM buku_tamu WHERE id = ?");
$stmt->bind_param("i", $id);
$hapus = $stmt->execute(); // eksekusi deh

// ngecek jalannya sukses apa enggak
if ($hapus) {
    // balik ke dashboard admin bawa pesan keberhasilan → trigger SweetAlert success
    header("Location: /ujikom1/admin/?pesan=Data berhasil dihapus!");
} else {
    // balik ke dashboard admin bawa pesan gagal → trigger SweetAlert error
    // pake ?error= bukan ?pesan= biar SweetAlert-nya beda (merah, bukan hijau)
    header("Location: /ujikom1/admin/?error=Gagal menghapus data. Coba lagi.");
}
exit; // udahan bro
?>
