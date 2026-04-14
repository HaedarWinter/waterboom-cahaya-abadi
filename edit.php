<?php
// start session buat proteksi biar cuma admin yg bisa kesini
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}
require 'koneksi.php';

// tangkep data param 'id' dari url, kalo gaada diset aja 0
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// tarik semua data dari tabel berdasarkan id tersebut
$result = mysqli_query($koneksi, "SELECT * FROM buku_tamu WHERE id = $id");
// ekstrak datanya satu-satu masukin variabel array asossiatif $data
$data   = mysqli_fetch_assoc($result);

// validasi kalo id admin ngasal ngetik di url dan emg gda datanya
if (!$data) {
    header("Location: admin.php?pesan=Data tidak ditemukan.");
    exit;
}

// ─── PROSES KETIKA TOMBOL SIMPAN DITEKAN ───
// karena kita pakai satu file ini buat nampilin DAN jg nyimpen data, 
// makanya harus di cek apakah formnya udah dikirim alias methodnya POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // bersihin datanya dulu pake escape_string 
    $nama    = mysqli_real_escape_string($koneksi, $_POST['nama']);
    $no_hp   = mysqli_real_escape_string($koneksi, $_POST['no_hp']);
    $email   = mysqli_real_escape_string($koneksi, $_POST['email']);
    $tanggal = mysqli_real_escape_string($koneksi, $_POST['tanggal']);
    
    // ini sintak sakti buat nipan datanya dgn data yg baru (UPDATE bukan INSERT lagi)
    $ok      = mysqli_query($koneksi,
        "UPDATE buku_tamu SET nama='$nama', no_hp='$no_hp', email='$email', tanggal='$tanggal' WHERE id=$id");
    
    // cek berhasil di tiban / udpate ga
    if ($ok) {
        // berhasil? cus balik bawa bingkisan sukses
        header("Location: admin.php?pesan=Data berhasil diperbarui!");
    } else {
        // gagal? reload halaman ini kasi peringatan
        header("Location: edit.php?id=$id&error=Gagal memperbarui data.");
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Data — Cipamingkis Admin</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin_style.css">
</head>
<body class="admin-body">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div>
            <div class="brand-name">Cipamingkis</div>
            <div class="brand-sub">Admin Panel</div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Menu Utama</div>
        <a href="admin.php" class="nav-item">
            <span>Dashboard</span>
        </a>
        <a href="index.php" class="nav-item" target="_blank">
            <span>Lihat Website</span>
        </a>
    </nav>
    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
        <div class="user-meta">
            <div class="user-name"><?= htmlspecialchars($_SESSION['username']) ?></div>
            <div class="user-role">Administrator</div>
        </div>
        <a href="logout.php" class="logout-btn" title="Logout" style="font-size: 0.8rem;">Logout</a>
    </div>
</aside>

<!-- MAIN -->
<main class="admin-main">

    <header class="admin-header">
        <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">&#9776;</button>
        <div class="header-title">
            <h1 class="page-heading">Edit Data</h1>
            <span class="breadcrumb"><a href="admin.php">Dashboard</a> / Edit Pengunjung</span>
        </div>
        <a href="logout.php" class="header-logout">Keluar</a>
    </header>

    <div class="admin-content">

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        <div class="section-card" style="max-width: 560px;">
            <div class="section-header" style="margin-bottom:1.5rem;">
                <div>
                    <h2 class="section-title">Edit Data Pengunjung</h2>
                    <p class="section-sub">Perbarui informasi pengunjung di bawah ini</p>
                </div>
            </div>

            <form action="edit.php?id=<?= $id ?>" method="post">
                <div class="form-group">
                    <label for="nama">Nama Lengkap</label>
                    <input type="text" id="nama" name="nama"
                           value="<?= htmlspecialchars($data['nama']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="no_hp">Nomor HP</label>
                    <input type="tel" id="no_hp" name="no_hp"
                           value="<?= htmlspecialchars($data['no_hp']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Alamat Email</label>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($data['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="tanggal">Tanggal Observasi</label>
                    <input type="date" id="tanggal" name="tanggal"
                           value="<?= date('Y-m-d', strtotime($data['tanggal'])) ?>" required>
                </div>
                <div style="display:flex; gap:.75rem; margin-top:1.5rem;">
                    <button type="submit" class="btn">Simpan Perubahan</button>
                    <a href="admin.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>

    </div>
</main>

</body>
</html>
