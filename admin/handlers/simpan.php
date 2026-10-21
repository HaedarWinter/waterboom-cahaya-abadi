<?php
session_start(); // wajib dinyalakan buat nyimpen data yang udah diketik user (flash data)
// file ini tugasnya nerima kiriman form buku tamu dari user (halaman depan)
// kita tangkep datanya pake method POST soalnya kalo pake GET datanya keliatan di url

// panggil dulu koneksinya biar nyambung sama db_ujikom
require '../../config/koneksi.php';
require '../../config/security.php';

// ─── VALIDASI CSRF TOKEN ───
if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    header("Location: /ujikom1/?error=Token keamanan tidak valid. Silakan coba lagi.");
    exit;
}
regenerate_csrf();

// ─── CEK HONEYPOT (ANTI-BOT) ───
if (!check_honeypot('website_url')) {
    // Bot detected! Diam-diam redirect tanpa error yang jelas
    header("Location: /ujikom1/?pesan=Data berhasil disimpan! Terima kasih sudah mengisi buku tamu.");
    exit;
}

// ─── RATE LIMITING ───
if (!check_rate_limit('guestbook')) {
    header("Location: /ujikom1/?error=Terlalu banyak pengiriman. Silakan tunggu beberapa menit sebelum mencoba lagi.");
    exit;
}

// ambil datanya aja ga perlu di escape string karna kita pake prepared statement
$nama    = trim($_POST['nama']);
$no_hp   = trim($_POST['no_hp']);
$email   = trim($_POST['email']);
$tanggal = $_POST['tanggal'];

// kita buat validasi simpel aja, ngecek jangan sampe ada inputan yg kosong
if (empty($nama) || empty($no_hp) || empty($email) || empty($tanggal)) {
    $_SESSION['form_data'] = $_POST; // simpan inputan user ke memori biar ga capek ngetik ulang
    // kalo ada yg bolong, kita lempar balik ke halaman form terus kasih tau errornya
    header("Location: /ujikom1/?error=Semua data harus diisi!");
    exit;
}

// ─── VALIDASI FORMAT EMAIL ATAU TANDA HUBUNG (-) ───
// Kita pastikan isinya email beneran ATAU cuma tanda hubung (-) saja
// Aturan ketat: Email harus diawali huruf/angka
$email_pattern = '/^[a-zA-Z0-9][a-zA-Z0-9._%+-]*@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
if ($email !== '-' && !preg_match($email_pattern, $email)) {
    $_SESSION['form_data'] = $_POST;
    header("Location: /ujikom1/?error=Format email tidak valid (Harus dimulai dengan huruf/angka atau gunakan '-')!");
    exit;
}

//  VALIDASI NOMOR HP (MIN 10 DIGIT) 
// kita pastikan nomor HP yang diinput ga kependekan (minimal 10 angka)
if (strlen($no_hp) < 10) {
    $_SESSION['form_data'] = $_POST; // simpan ke memori
    header("Location: /ujikom1/?error=Nomor HP minimal harus 10 digit!");
    exit; // stop eksekusi, usir balik ke form
}

// ─── VALIDASI TANGGAL — JANGAN BOLEH MASA LALU ─────────────────────────
// ini lapis kedua validasi — jaga-jaga kalo user jail bypass validasi JS di browser

// strtotime() itu fungsi PHP buat ubah string tanggal ('2026-04-15T14:30') jadi angka detik (UNIX timestamp)
$tgl_input  = strtotime($tanggal);

// time() = ambil waktu sekarang saat ini juga (detik)
$tgl_sekarang = time();

// kalo timestamp tanggal yang diisi kurang dari waktu sekarang = berarti sudah lewat = ditolak!
if ($tgl_input < $tgl_sekarang) {
    $_SESSION['form_data'] = $_POST; // simpan memori
    // usir balik ke form dengan pesan error yang jelas
    header("Location: /ujikom1/?error=Waktu reservasi tidak boleh di masa lalu!");
    exit; // wajib exit biar kode bawahnya ga ikut jalan
}

// nah ini query sql nya buat nyeplokin data baru ke tabel buku_tamu pake prepared statement
$stmt = $koneksi->prepare("INSERT INTO buku_tamu (nama, no_hp, email, tanggal) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $nama, $no_hp, $email, $tanggal);
$simpan = $stmt->execute();

// cek statusnya, sukses apa enggak
if ($simpan) {
    unset($_SESSION['form_data']); // hapus memori form karena udah berhasil
    // kalo beres, balik ke index terus kasih tau pengunjung kalo datanya udah mendarat
    header("Location: /ujikom1/?pesan=Data berhasil disimpan! Terima kasih sudah mengisi buku tamu.");
} else {
    $_SESSION['form_data'] = $_POST; // simpan memori jaga-jaga kalau db nya tewas
    // kalo ada trouble di db
    header("Location: /ujikom1/?error=Gagal menyimpan data, coba lagi.");
}

// stop kodenya sampe sini aja, wajib dikasi exit setiap abis mainin header location
exit;
?>
