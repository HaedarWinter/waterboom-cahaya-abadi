<?php
// nyalain session buat ngecek dia udah login apa blm
session_start();
// kalo dia belom lewat halaman login (ga bawa tiket vip bernama $_SESSION['login'])
// bakal kita usir secara paksa ke halaman login
if (!isset($_SESSION['login'])) {
    header("Location: login.php?error=Silakan login terlebih dahulu!");
    exit; // batesin eksekusi file ini
}
// masukin setting database
require 'koneksi.php';

// ─── AMBIL DATA STATISTIK PENGUNJUNG ───
// hitung ada brp baris sih secara keseluruhan di dalem tabel buku_tamu
$total       = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM buku_tamu"))['n'];
// ngecek ada brg yg ngunjungin HR INI doang (dicocokin tanggal input dgn CURDATE)
$hari_ini    = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM buku_tamu WHERE DATE(tanggal) = CURDATE()"))['n'];
// ini ngecek di BULAN INI dan TAHUN INI aja
$bulan_ini   = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM buku_tamu WHERE MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE())"))['n'];

// ─── AMBIL DAFTAR PENGUNJUNG BUAT TABEL ───
// fitur cari nih, cek kalo di bar pencarian ada yg ngetik trus di enter ga (variabel q)
$search      = isset($_GET['q']) ? mysqli_real_escape_string($koneksi, $_GET['q']) : '';
// kalo ada, query difilter (where nama / email / no_hp mirip kek yg diketik)
$where       = $search ? "WHERE nama LIKE '%$search%' OR email LIKE '%$search%' OR no_hp LIKE '%$search%'" : '';
// panggil semua isinya dengan diurutin (ORDER BY) tanggal masuk yg kebalik (DESC) jd yg trbaru diatas
$result      = mysqli_query($koneksi, "SELECT * FROM buku_tamu $where ORDER BY tanggal DESC, id DESC");
// itung yg nongol skrg brpa biji
$total_show  = mysqli_num_rows($result);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin — Cipamingkis</title>
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
        <a href="admin.php" class="nav-item active">
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
            <h1 class="page-heading">Dashboard</h1>
            <span class="breadcrumb">Selamat datang, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></span>
        </div>
        <a href="logout.php" class="header-logout">Keluar</a>
    </header>

    <div class="admin-content">

        <?php if (isset($_GET['pesan'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_GET['pesan']) ?></div>
        <?php endif; ?>

        <!-- STAT CARDS -->
        <div class="stats-grid">
            <div class="stat-card green">
                <div class="stat-info">
                    <div class="stat-value"><?= $total ?></div>
                    <div class="stat-label">Total Pengunjung</div>
                </div>
            </div>
            <div class="stat-card blue">
                <div class="stat-info">
                    <div class="stat-value"><?= $hari_ini ?></div>
                    <div class="stat-label">Pengunjung Hari Ini</div>
                </div>
            </div>
            <div class="stat-card amber">
                <div class="stat-info">
                    <div class="stat-value"><?= $bulan_ini ?></div>
                    <div class="stat-label">Pengunjung Bulan Ini</div>
                </div>
            </div>
        </div>

        <!-- TABLE SECTION -->
        <div class="section-card">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Data Buku Tamu</h2>
                    <p class="section-sub">Menampilkan <?= $total_show ?> dari <?= $total ?> data</p>
                </div>
                <form method="GET" action="" class="search-form">
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Cari nama / email / HP..." class="search-input">
                    <button type="submit" class="btn-search" style="padding: 0 10px; width: auto; border-radius: 4px;">Cari</button>
                    <?php if ($search): ?>
                        <a href="admin.php" class="btn-clear" style="padding: 0 5px; width: auto; border-radius: 4px;">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:46px">No</th>
                            <th>Nama</th>
                            <th>No HP</th>
                            <th>Email</th>
                            <th>Tgl Observasi</th>
                            <th style="width:130px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result && mysqli_num_rows($result) > 0):
                        $no = 1;
                        while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td class="td-num"><?= $no++ ?></td>
                            <td class="td-name">
                                <div class="name-avatar"><?= strtoupper(substr($row['nama'],0,1)) ?></div>
                                <?= htmlspecialchars($row['nama']) ?>
                            </td>
                            <td><?= htmlspecialchars($row['no_hp']) ?></td>
                            <td class="td-email"><?= htmlspecialchars($row['email']) ?></td>
                            <td><span class="date-badge"><?= date('d M Y', strtotime($row['tanggal'])) ?></span></td>
                            <td class="td-actions">
                                <a href="edit.php?id=<?= $row['id'] ?>" class="action-btn edit">Edit</a>
                                <a href="hapus.php?id=<?= $row['id'] ?>" class="action-btn del"
                                   onclick="return confirm('Hapus data <?= htmlspecialchars(addslashes($row['nama'])) ?>?')">Hapus</a>
                            </td>
                        </tr>
                    <?php endwhile;
                    else: ?>
                        <tr>
                            <td colspan="6" class="td-empty">
                                <?= $search ? "Tidak ada data yang cocok." : 'Belum ada data pengunjung.' ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

</body>
</html>
