require_once '../app/init.php';
global $koneksi;
check_session_timeout();
enforce_active_session();

// ─── PROTEKSI PERMISSION ───
if (!has_canonical_permission('guestbook.moderate')) {
     header("Location: " . base_url('admin/?error=Anda tidak memiliki izin untuk mengedit data pengunjung.'));
     exit;
 }

// tangkep data param 'id' dari url, kalo gaada diset aja 0
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// tarik semua data dari tabel berdasarkan id tersebut, pakai LIMIT 1 buat optimalisasi
$stmt_get = $koneksi->prepare("SELECT * FROM buku_tamu WHERE id = ? LIMIT 1");
$stmt_get->bind_param("i", $id);
$stmt_get->execute();
// ekstrak datanya satu-satu masukin variabel array asossiatif $data
$data   = $stmt_get->get_result()->fetch_assoc();

// validasi kalo id admin ngasal ngetik di url dan emg gda datanya
 if (!$data) {
     header("Location: " . base_url('admin/?pesan=Data tidak ditemukan.'));
     exit;
 }

//  PROSES KETIKA TOMBOL SIMPAN DITEKAN 
// karena kita pakai satu file ini buat nampilin DAN jg nyimpen data,
// makanya harus di cek apakah formnya udah dikirim alias methodnya POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi CSRF token
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        header("Location: edit.php?id=$id&error=Token keamanan tidak valid. Silakan coba lagi.");
        exit;
    }
    regenerate_csrf();

    // karna pake prepared statement ga perlu escape string lagi
    $nama    = trim($_POST['nama']);
    $no_hp   = trim($_POST['no_hp']);
    $email   = trim($_POST['email']);
    $tanggal = $_POST['tanggal'];

    // ─── VALIDASI FORMAT EMAIL ATAU TANDA HUBUNG (-) ───
    // Aturan ketat: Email harus diawali huruf/angka
    $email_pattern = '/^[a-zA-Z0-9][a-zA-Z0-9._%+-]*@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
    if ($email !== '-' && !preg_match($email_pattern, $email)) {
        header("Location: edit.php?id=$id&error=Format email tidak valid (Harus dimulai dengan huruf/angka atau gunakan '-')!");
        exit;
    }

    //  CEK APAKAH ADA PERUBAHAN DATA 
    // bandingin data yang dikirim dari form dgn data asli di database
    // format datetime-local itu YYYY-MM-DDTHH:MM, kita samakan formatnya buat pembanding
    $tgl_db = date('Y-m-d\TH:i', strtotime($data['tanggal']));
    $tidak_berubah = (
        $nama    === $data['nama']  &&
        $no_hp   === $data['no_hp'] &&
        $email   === $data['email'] &&
        $tanggal === $tgl_db
    );

    // kalo ternyata ga ada yg berubah, kasih tau admin via modal info
    if ($tidak_berubah) {
        header("Location: edit.php?id=$id&info=Tidak ada perubahan data yang dilakukan.");
        exit;
    }

    // ini sintak sakti buat nipan datanya dgn data yg baru pake prepared statement
    $stmt_update = $koneksi->prepare("UPDATE buku_tamu SET nama = ?, no_hp = ?, email = ?, tanggal = ? WHERE id = ?");
    $stmt_update->bind_param("ssssi", $nama, $no_hp, $email, $tanggal, $id);
    $ok = $stmt_update->execute();

    // cek berhasil di tiban / udpate ga
     if ($ok) {
         // berhasil? cus balik bawa bingkisan sukses
         header("Location: " . base_url('admin/?pesan=Data berhasil diperbarui!'));
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
    <link rel="stylesheet" href="<?= e(asset_url('assets/dist/css/app.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/admin_style.css')) ?>">
    <title>Edit Data — Cahaya Abadi Admin</title>
                <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="admin-body">

<?php include 'includes/sidebar.php'; ?>

<!-- MAIN -->
<main class="admin-main">

    <header class="admin-header">
        <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">&#9776;</button>
        <div class="header-title">
            <h1 class="page-heading">Edit Data</h1>
            <span class="breadcrumb"><a href="<?= base_url('admin/') ?>">Dashboard</a> / Edit Pengunjung</span>
        </div>
        <!-- tombol keluar header -->
        <a href="logout.php?token=<?= e(csrf_token()) ?>" class="header-logout swal-logout">
            <i data-lucide="log-out"></i>
            <span>Keluar</span>
        </a>
    </header>

    <div class="admin-content">

        <div class="section-card">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Edit Data Pengunjung</h2>
                    <p class="section-sub">Perbarui informasi pengunjung di bawah ini</p>
                </div>
            </div>

            <form action="edit.php?id=<?= $id ?>" method="post" style="padding:1.5rem 1.5rem;">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="nama">
                        <i data-lucide="user" class="input-icon-inline"></i>
                        Nama Lengkap
                    </label>
                    <input type="text" id="nama" name="nama"
                           value="<?= htmlspecialchars($data['nama']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="no_hp">
                        <i data-lucide="phone" class="input-icon-inline"></i>
                        Nomor HP
                    </label>
                    <input type="tel" id="no_hp" name="no_hp"
                           value="<?= htmlspecialchars($data['no_hp']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">
                        <i data-lucide="mail" class="input-icon-inline"></i>
                        Alamat Email
                    </label>
                    <input type="text" id="email" name="email"
                           pattern="[a-zA-Z0-9][a-zA-Z0-9._%+-]*@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}|-"
                           title="Email harus dimulai dengan huruf/angka, atau gunakan '-' jika tidak memiliki email"
                           inputmode="email"
                           value="<?= htmlspecialchars($data['email']) ?>" required>
                    <small style="font-size: 11px; color: #8a8578; margin-top: 4px; display: block;">* Isi dengan tanda hubung (<b>-</b>) jika tidak memiliki email.</small>
                </div>
                <div class="form-group">
                    <label for="tanggal">
                        <i data-lucide="calendar" class="input-icon-inline"></i>
                        Tanggal & Waktu Reservasi
                    </label>
                    <input type="datetime-local" id="tanggal" name="tanggal"
                           min="<?= date('Y-m-d\TH:i') ?>" required>
                </div>
                <div style="display:flex; gap:.5rem; padding-top:1rem; border-top:1px solid #eae7e0; margin-top:1.25rem;">
                    <button type="submit" class="btn">
                        <i data-lucide="save"></i>
                        <span>Simpan Perubahan</span>
                    </button>
                    <a href="<?= base_url('admin/') ?>" class="btn btn-secondary">
                         <i data-lucide="x-circle"></i>
                         <span>Batal</span>
                     </a>
                </div>
            </form>
        </div>

    </div>
</main>

<script>
//  SWEETALERT2 HANDLER — EDIT PAGE 

const params = new URLSearchParams(window.location.search);

// kalo ada param ?error= (gagal update), tampilkan modal error
if (params.has('error')) {
    Swal.fire({
        icon: 'error',
        title: 'Gagal!',
        text: params.get('error'),
        confirmButtonColor: '#0e7c6b',
        confirmButtonText: 'Oke'
    }).then(() => {
        const url = new URL(window.location.href);
        url.searchParams.delete('error');
        window.history.replaceState({}, '', url);
    });
}

// kalo ada param ?info= artinya tidak ada perubahan data
if (params.has('info')) {
    Swal.fire({
        icon: 'info',
        title: 'Tidak Ada Perubahan',
        text: params.get('info'),
        confirmButtonColor: '#0e7c6b',
        confirmButtonText: 'Oke'
    }).then(() => {
        const url = new URL(window.location.href);
        url.searchParams.delete('info');
        window.history.replaceState({}, '', url);
    });
}

//  KONFIRMASI LOGOUT di halaman edit 
document.querySelectorAll('.swal-logout').forEach(function(el) {
    el.addEventListener('click', function(e) {
        e.preventDefault();
        var tujuan = this.getAttribute('href');
        Swal.fire({
            icon: 'question',
            title: 'Yakin ingin logout?',
            text: 'Sesi admin kamu akan diakhiri.',
            showCancelButton: true,
            confirmButtonColor: '#0e7c6b',
            cancelButtonColor: '#e4e4e7',
            confirmButtonText: 'Ya, Logout',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (result.isConfirmed) window.location.href = tujuan;
        });
    });
});

// Inisialisasi Lucide Icons
lucide.createIcons();
</script>

</body>
</html>
