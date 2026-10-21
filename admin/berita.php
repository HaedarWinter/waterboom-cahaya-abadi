<?php
/**
 * Halaman Admin: Kelola Berita & Event
 */
require_once '../app/init.php';
check_session_timeout();
enforce_active_session($koneksi);

if (!has_permission('news', 'view')) {
    header("Location: /ujikom1/admin/?error=Akses ditolak! Anda tidak memiliki izin untuk mengelola Berita.");
    exit;
}

// Pagination
$per_page = 5;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$total_res = $koneksi->query("SELECT COUNT(*) as total FROM berita");
$total = $total_res->fetch_assoc()['total'];
$total_pages = max(1, ceil($total / $per_page));

// Data
$result = $koneksi->query("SELECT * FROM berita ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");

// Edit mode
$edit_data = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $edit_stmt = $koneksi->prepare("SELECT * FROM berita WHERE id = ?");
    $edit_stmt->bind_param("i", $eid);
    $edit_stmt->execute();
    $edit_data = $edit_stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Berita — Cahaya Abadi</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin_style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="admin-body">

<?php include 'includes/sidebar.php'; ?>

<!-- MAIN -->
<main class="admin-main">
    <header class="admin-header">
        <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">&#9776;</button>
        <div class="header-title">
            <h1 class="page-heading">Kelola Berita & Event</h1>
            <span class="breadcrumb"><a href="/ujikom1/admin/">Dashboard</a> / Berita</span>
        </div>
        <a href="logout.php" class="header-logout swal-logout"><i data-lucide="log-out"></i><span>Keluar</span></a>
    </header>

    <div class="admin-content">

        <!-- FORM TAMBAH / EDIT -->
        <div class="section-card">
            <div class="section-header">
                <div>
                    <h2 class="section-title"><?= $edit_data ? 'Edit Berita' : 'Tambah Berita Baru' ?></h2>
                    <p class="section-sub"><?= $edit_data ? 'Perbarui informasi berita' : 'Tulis berita, promo, atau event terbaru' ?></p>
                </div>
            </div>
            <div style="padding: 1.5rem;">
                <form action="handlers/berita_handler.php" method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $edit_data ? 'update' : 'simpan' ?>">
                    <?php if ($edit_data): ?>
                    <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="judul">Judul Berita</label>
                        <input type="text" id="judul" name="judul" placeholder="Masukkan judul berita..." value="<?= e($edit_data['judul'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="isi">Isi Berita</label>
                        <textarea name="isi" id="isi" rows="5" placeholder="Tulis isi berita..." style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--radius);font-family:var(--font);font-size:.86rem;color:var(--fg);resize:vertical;outline:none;" required><?= e($edit_data['isi'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="gambar">Gambar (Opsional, max 3MB)</label>
                        <input type="file" id="gambar" name="gambar" accept="image/jpeg,image/png,image/webp" style="font-size:.85rem;">
                        <?php if ($edit_data && $edit_data['gambar']): ?>
                        <div style="margin-top:8px;">
                            <img src="../assets/berita/<?= e($edit_data['gambar']) ?>" alt="Preview" style="max-height:100px;border-radius:8px;border:1px solid var(--border);">
                            <small style="display:block;color:var(--muted);font-size:.75rem;margin-top:4px;">Gambar saat ini. Upload baru untuk mengganti.</small>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex;gap:.75rem;">
                        <button type="submit" class="btn">
                            <i data-lucide="<?= $edit_data ? 'save' : 'plus' ?>"></i>
                            <?= $edit_data ? 'Simpan Perubahan' : 'Tambah Berita' ?>
                        </button>
                        <?php if ($edit_data): ?>
                        <a href="berita.php" class="btn btn-secondary">
                            <i data-lucide="x-circle"></i> Batal
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- DAFTAR BERITA -->
        <div class="section-card">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Daftar Berita</h2>
                    <p class="section-sub"><?= $total ?> berita tersedia</p>
                </div>
            </div>

            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:46px">No</th>
                            <th>Judul</th>
                            <th>Gambar</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <th style="width:160px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                        <?php $no = $offset + 1; while ($r = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="td-num"><?= $no++ ?></td>
                            <td class="td-name"><?= e($r['judul']) ?></td>
                            <td>
                                <?php if ($r['gambar']): ?>
                                <img src="../assets/berita/<?= e($r['gambar']) ?>" alt="<?= e($r['judul']) ?>" style="width:50px;height:35px;object-fit:cover;border-radius:6px;border:1px solid var(--border);cursor:pointer;" onclick="Swal.fire({imageUrl:'../assets/berita/<?= e($r['gambar']) ?>',imageAlt:'<?= e($r['judul']) ?>',showConfirmButton:false,showCloseButton:true,width:600})">
                                <?php else: ?>
                                <span style="font-size:.75rem;color:var(--muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['aktif'] === 'Ya'): ?>
                                <span class="badge badge-success">Aktif</span>
                                <?php else: ?>
                                <span class="badge badge-danger">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="date-badge"><?= date('d M Y', strtotime($r['created_at'])) ?></span></td>
                            <td class="td-actions">
                                <div class="actions-wrapper">
                                    <a href="berita.php?edit=<?= $r['id'] ?>" class="action-btn edit" title="Edit">
                                        <i data-lucide="edit-3"></i>
                                    </a>
                                    <button class="action-btn detail btn-toggle-berita" data-id="<?= $r['id'] ?>" data-status="<?= $r['aktif'] ?>" title="Toggle Status">
                                        <i data-lucide="<?= $r['aktif'] === 'Ya' ? 'eye-off' : 'eye' ?>"></i>
                                    </button>
                                    <button class="action-btn del btn-hapus-berita" data-id="<?= $r['id'] ?>" data-judul="<?= e($r['judul']) ?>" title="Hapus">
                                        <i data-lucide="trash-2"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <tr><td colspan="6" class="td-empty">Belum ada berita.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <span class="pagination-info">
                    <?= $offset + 1 ?>–<?= min($offset + $per_page, $total) ?> dari <?= $total ?> data
                </span>
                <div class="pagination-btns">
                    <a href="<?= $page > 1 ? '?page='.($page-1).($search ? '&q='.urlencode($search) : '') : '#' ?>" 
                       class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"
                       <?= $page <= 1 ? 'onclick="return false;"' : '' ?>>&larr; Sebelumnya</a>
                    <span class="page-current"><?= $page ?> / <?= $total_pages ?></span>
                    <a href="<?= $page < $total_pages ? '?page='.($page+1).($search ? '&q='.urlencode($search) : '') : '#' ?>" 
                       class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"
                       <?= $page >= $total_pages ? 'onclick="return false;"' : '' ?>>Berikutnya &rarr;</a>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<!-- Hidden forms -->
<form id="form-toggle-berita" action="handlers/berita_handler.php" method="post" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="toggle_aktif">
    <input type="hidden" name="id" id="toggle-berita-id">
</form>
<form id="form-hapus-berita" action="handlers/berita_handler.php" method="post" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="hapus">
    <input type="hidden" name="id" id="hapus-berita-id">
</form>

<script>
const params = new URLSearchParams(window.location.search);
if (params.has('pesan')) { Swal.fire({ icon:'success',title:'Berhasil!',text:params.get('pesan'),confirmButtonColor:'#0e7c6b',timer:3500,timerProgressBar:true }).then(()=>{const u=new URL(window.location.href);u.searchParams.delete('pesan');window.history.replaceState({},'',u);}); }
if (params.has('error')) { Swal.fire({ icon:'error',title:'Gagal!',text:params.get('error'),confirmButtonColor:'#0e7c6b' }).then(()=>{const u=new URL(window.location.href);u.searchParams.delete('error');window.history.replaceState({},'',u);}); }

// Toggle aktif
document.querySelectorAll('.btn-toggle-berita').forEach(el => {
    el.addEventListener('click', function() {
        document.getElementById('toggle-berita-id').value = this.dataset.id;
        document.getElementById('form-toggle-berita').submit();
    });
});

// Hapus
document.querySelectorAll('.btn-hapus-berita').forEach(el => {
    el.addEventListener('click', function() {
        const id = this.dataset.id, judul = this.dataset.judul;
        Swal.fire({
            icon:'warning', title:'Hapus Berita?',
            html:'Berita <strong>"' + judul + '"</strong> akan dihapus permanen.',
            showCancelButton:true, confirmButtonColor:'#c0392b', cancelButtonColor:'#e4e4e7',
            confirmButtonText:'Ya, Hapus', cancelButtonText:'Batal'
        }).then(r => { if(r.isConfirmed) { document.getElementById('hapus-berita-id').value=id; document.getElementById('form-hapus-berita').submit(); } });
    });
});

// Logout
document.querySelectorAll('.swal-logout').forEach(function(el) {
    el.addEventListener('click', function(e) { e.preventDefault(); var t = this.getAttribute('href');
        Swal.fire({ icon:'question',title:'Yakin ingin logout?',text:'Sesi admin kamu akan diakhiri.',showCancelButton:true,confirmButtonColor:'#0e7c6b',cancelButtonColor:'#e4e4e7',confirmButtonText:'Ya, Logout',cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) window.location.href=t; });
    });
});

lucide.createIcons();
</script>
</body>
</html>
