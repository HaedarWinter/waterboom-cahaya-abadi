<?php
/**
 * Halaman Kelola Galeri — Upload, Edit, Hapus Foto
 */
require_once '../app/init.php';
enforce_active_session($koneksi);

// Cek hak akses: gallery.view
if (!has_permission('gallery', 'view')) {
    header("Location: /ujikom1/admin/?error=Anda tidak memiliki hak akses untuk melihat galeri.");
    exit;
}

// Ambil semua data galeri
$result = $koneksi->query("SELECT * FROM galeri ORDER BY urutan ASC, id DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Galeri — Cahaya Abadi</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin_style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="admin-body"><?php include 'includes/sidebar.php'; ?>

<!-- MAIN -->
<main class="admin-main">
    <header class="admin-header">
        <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">&#9776;</button>
        <div class="header-title">
            <h1 class="page-heading">Kelola Galeri</h1>
            <span class="breadcrumb"><a href="/ujikom1/admin/">Dashboard</a> / Kelola Galeri</span>
        </div>
        <a href="logout.php" class="header-logout swal-logout">
            <i data-lucide="log-out"></i>
            <span>Keluar</span>
        </a>
    </header>

    <div class="admin-content">

        <!-- FORM UPLOAD GAMBAR BARU -->
        <div class="section-card">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Upload Gambar Baru</h2>
                    <p class="section-sub">Format: JPG, PNG, WEBP — Maks. 2MB</p>
                </div>
            </div>
            <form action="handlers/galeri_handler.php" method="post" enctype="multipart/form-data" style="padding:1.5rem;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items:end;">
                    <div class="form-group" style="margin-bottom:0">
                        <label for="judul">Judul Gambar</label>
                        <input type="text" id="judul" name="judul" placeholder="Contoh: Kolam Utama" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label for="deskripsi">Deskripsi (Opsional)</label>
                        <input type="text" id="deskripsi" name="deskripsi" placeholder="Deskripsi singkat...">
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label for="urutan">Urutan</label>
                        <input type="number" id="urutan" name="urutan" value="0" min="0">
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label for="gambar">Pilih Gambar</label>
                        <input type="file" id="gambar" name="gambar" accept="image/jpeg,image/png,image/webp" required
                               style="font-size:.84rem;">
                    </div>
                </div>
                <div style="margin-top:1.25rem;">
                    <button type="submit" class="btn">
                        <i data-lucide="upload" style="width:16px;height:16px"></i>
                        Upload Gambar
                    </button>
                </div>
            </form>
        </div>

        <!-- DAFTAR GAMBAR -->
        <div class="section-card">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Daftar Galeri</h2>
                    <p class="section-sub"><?= $result->num_rows ?> gambar tersedia</p>
                </div>
            </div>

            <?php if ($result->num_rows > 0): ?>
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap:1rem; padding:1.5rem;">
                <?php while ($row = $result->fetch_assoc()):
                    $img_path = file_exists("../assets/galeri/" . $row['file_gambar'])
                        ? "../assets/galeri/" . $row['file_gambar']
                        : "../assets/" . $row['file_gambar'];
                ?>
                <div style="background:#fff; border:1px solid #eae7e0; border-radius:12px; overflow:hidden; position:relative;">
                    <img src="<?= $img_path ?>" alt="<?= htmlspecialchars($row['judul']) ?>"
                         style="width:100%; height:180px; object-fit:cover; display:block;">

                    <?php if ($row['aktif'] === 'Tidak'): ?>
                    <span class="badge badge-danger" style="position:absolute;top:8px;right:8px;">Nonaktif</span>
                    <?php else: ?>
                    <span class="badge badge-success" style="position:absolute;top:8px;right:8px;">Aktif</span>
                    <?php endif; ?>

                    <div style="padding:.85rem;">
                        <div style="font-weight:700; font-size:.88rem; color:#1a1a1a; margin-bottom:2px;"><?= htmlspecialchars($row['judul']) ?></div>
                        <div style="font-size:.75rem; color:#8a8578; margin-bottom:8px;"><?= htmlspecialchars($row['deskripsi'] ?? '') ?></div>
                        <div style="display:flex; gap:6px;">
                            <button class="action-btn edit btn-edit-galeri"
                                data-id="<?= $row['id'] ?>"
                                data-judul="<?= htmlspecialchars($row['judul']) ?>"
                                data-deskripsi="<?= htmlspecialchars($row['deskripsi'] ?? '') ?>"
                                data-urutan="<?= $row['urutan'] ?>"
                                data-aktif="<?= $row['aktif'] ?>"
                                style="flex:1; justify-content:center;">
                                <i data-lucide="edit-3"></i> Edit
                            </button>
                            <button class="action-btn del btn-hapus-galeri"
                                data-id="<?= $row['id'] ?>"
                                data-judul="<?= htmlspecialchars($row['judul']) ?>"
                                style="justify-content:center;">
                                <i data-lucide="trash-2"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div style="padding:3rem; text-align:center; color:#aaa;">
                <p>Belum ada gambar di galeri. Silakan upload gambar pertama!</p>
            </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<!-- Hidden forms for JS -->
<form id="form-edit-galeri" action="handlers/galeri_handler.php" method="post" enctype="multipart/form-data" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="id" id="edit-id">
    <input type="hidden" name="judul" id="edit-judul">
    <input type="hidden" name="deskripsi" id="edit-deskripsi">
    <input type="hidden" name="urutan" id="edit-urutan">
    <input type="hidden" name="aktif" id="edit-aktif">
</form>
<form id="form-hapus-galeri" action="handlers/galeri_handler.php" method="post" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="hapus-id">
</form>

<script>
// ── NOTIFIKASI ──
const params = new URLSearchParams(window.location.search);
if (params.has('pesan')) {
    Swal.fire({ icon: 'success', title: 'Berhasil!', text: params.get('pesan'), confirmButtonColor: '#0e7c6b', timer: 3500, timerProgressBar: true })
    .then(() => { const url = new URL(window.location.href); url.searchParams.delete('pesan'); window.history.replaceState({}, '', url); });
}
if (params.has('error')) {
    Swal.fire({ icon: 'error', title: 'Gagal!', text: params.get('error'), confirmButtonColor: '#0e7c6b' })
    .then(() => { const url = new URL(window.location.href); url.searchParams.delete('error'); window.history.replaceState({}, '', url); });
}

// ── EDIT GALERI ──
document.querySelectorAll('.btn-edit-galeri').forEach(el => {
    el.addEventListener('click', function() {
        const d = this.dataset;
        Swal.fire({
            title: 'Edit Gambar',
            html: '<div style="text-align:left;">' +
                '<div class="form-group"><label style="font-size:.8rem;margin-bottom:4px;display:block;">Judul</label><input type="text" id="swal-judul" class="swal2-input" style="margin:0;width:100%" value="' + d.judul + '"></div>' +
                '<div class="form-group" style="margin-top:10px"><label style="font-size:.8rem;margin-bottom:4px;display:block;">Deskripsi</label><input type="text" id="swal-deskripsi" class="swal2-input" style="margin:0;width:100%" value="' + d.deskripsi + '"></div>' +
                '<div class="form-group" style="margin-top:10px"><label style="font-size:.8rem;margin-bottom:4px;display:block;">Urutan</label><input type="number" id="swal-urutan" class="swal2-input" style="margin:0;width:100%" value="' + d.urutan + '" min="0"></div>' +
                '<div class="form-group" style="margin-top:10px"><label style="font-size:.8rem;margin-bottom:4px;display:block;">Status</label><select id="swal-aktif" class="swal2-input" style="margin:0;width:100%"><option value="Ya"' + (d.aktif === 'Ya' ? ' selected' : '') + '>Aktif</option><option value="Tidak"' + (d.aktif === 'Tidak' ? ' selected' : '') + '>Nonaktif</option></select></div>' +
                '</div>',
            showCancelButton: true, confirmButtonColor: '#0e7c6b', confirmButtonText: 'Simpan', cancelButtonText: 'Batal', cancelButtonColor: '#e4e4e7', width: 440,
            preConfirm: function() {
                var judul = document.getElementById('swal-judul').value;
                if (!judul) { Swal.showValidationMessage('Judul wajib diisi!'); return false; }
                return { judul: judul, deskripsi: document.getElementById('swal-deskripsi').value, urutan: document.getElementById('swal-urutan').value, aktif: document.getElementById('swal-aktif').value };
            }
        }).then(function(result) {
            if (result.isConfirmed) {
                document.getElementById('edit-id').value = d.id;
                document.getElementById('edit-judul').value = result.value.judul;
                document.getElementById('edit-deskripsi').value = result.value.deskripsi;
                document.getElementById('edit-urutan').value = result.value.urutan;
                document.getElementById('edit-aktif').value = result.value.aktif;
                document.getElementById('form-edit-galeri').submit();
            }
        });
    });
});

// ── HAPUS GALERI ──
document.querySelectorAll('.btn-hapus-galeri').forEach(el => {
    el.addEventListener('click', function() {
        const id = this.dataset.id;
        const judul = this.dataset.judul;
        Swal.fire({
            icon: 'warning', title: 'Hapus Gambar?',
            html: 'Gambar <strong>' + judul + '</strong> akan dihapus permanen.',
            showCancelButton: true, confirmButtonColor: '#c0392b', cancelButtonColor: '#e4e4e7',
            confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
        }).then(function(result) {
            if (result.isConfirmed) {
                document.getElementById('hapus-id').value = id;
                document.getElementById('form-hapus-galeri').submit();
            }
        });
    });
});

// ── LOGOUT ──
document.querySelectorAll('.swal-logout').forEach(function(el) {
    el.addEventListener('click', function(e) {
        e.preventDefault();
        var tujuan = this.getAttribute('href');
        Swal.fire({ icon: 'question', title: 'Yakin ingin logout?', text: 'Sesi admin kamu akan diakhiri.', showCancelButton: true, confirmButtonColor: '#0e7c6b', cancelButtonColor: '#e4e4e7', confirmButtonText: 'Ya, Logout', cancelButtonText: 'Batal' })
        .then(function(result) { if (result.isConfirmed) window.location.href = tujuan; });
    });
});

lucide.createIcons();
</script>
</body>
</html>
