<?php
// file ini tugasnya nerima kiriman form buku tamu dari user (halaman depan)
// kita tangkep datanya pake method POST soalnya kalo pake GET datanya keliatan di url

// panggil dulu koneksinya biar nyambung sama db_ujikom
require 'koneksi.php';

// ambil datanya terus di filter (escape string)
// ini tuh buat keamanan biar hacker gabisa nyisipin kode aneh-aneh (sql injection) ke db kita
$nama    = mysqli_real_escape_string($koneksi, $_POST['nama']);
$no_hp   = mysqli_real_escape_string($koneksi, $_POST['no_hp']);
$email   = mysqli_real_escape_string($koneksi, $_POST['email']);
$tanggal = mysqli_real_escape_string($koneksi, $_POST['tanggal']);

// kita buat validasi simpel aja, ngecek jangan sampe ada inputan yg kosong
if (empty($nama) || empty($no_hp) || empty($email) || empty($tanggal)) {
    // kalo ada yg bolong, kita lempar balik ke halaman form terus kasih tau errornya
    header("Location: index.php?error=Semua data harus diisi!#bukutamu");
    exit;
}

// nah ini query sql nya buat nyeplokin data baru ke tabel buku_tamu
$query = "INSERT INTO buku_tamu (nama, no_hp, email, tanggal) VALUES ('$nama', '$no_hp', '$email', '$tanggal')";

// ayo kita execute perintanya ke sql
$simpan = mysqli_query($koneksi, $query);

// cek statusnya, sukses apa enggak
if ($simpan) {
    // kalo beres, balik ke index terus kasih tau pengunjung kalo datanya udah mendarat
    header("Location: index.php?pesan=Data berhasil disimpan! Terima kasih sudah mengisi buku tamu.#bukutamu");
} else {
    // kalo ada trouble di db
    header("Location: index.php?error=Gagal menyimpan data, coba lagi.#bukutamu");
}

// stop kodenya sampe sini aja, wajib dikasi exit setiap abis mainin header location
exit;
?>
