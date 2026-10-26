<?php
/**
 * Halaman Admin: Kelola FAQ
 */
require_once '../app/init.php';
global $koneksi;
require_once __DIR__ . '/../resources/views/components/pagination.php';
enforce_active_session();

check_session_timeout();
if (!has_canonical_permission('content.faq.manage')) {
     header("Location: " . base_url('admin/?error=Akses ditolak! Anda tidak memiliki izin untuk mengelola FAQ.'));
     exit;
 }

// Pagination
$per_page = 5;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$total_res = $koneksi->query("SELECT COUNT(*) as total FROM faq");
$total = $total_res->fetch_assoc()['total'];
$total_pages = max(1, ceil($total / $per_page));

// Data
$result = $koneksi->query("SELECT * FROM faq ORDER BY urutan ASC, id ASC LIMIT $per_page OFFSET $offset");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= e(asset_url('assets/dist/css/app.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/admin_style.css')) ?>">
    <title>Kelola FAQ — Cahaya Abadi</title>
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
            <h1 class="page-heading">Kelola FAQ</h1>
            <span class="breadcrumb"><a href="<?= base_url('admin/') ?>">Dashboard</a> / FAQ</span>
        </div>
        <a href="logout.php?token=<?= e(csrf_token()) ?>" class="header-logout swal-logout"><i data-lucide="log-out"></i><span>Keluar</span></a>
    </header>

    <div class="admin-content">

        <!-- TABLE -->
        <div class="section-card">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Daftar FAQ</h2>
                    <p class="section-sub"><?= $total ?> pertanyaan tersedia</p>
                </div>
                <div class="header-actions">
                    <button class="action-btn detail btn-tambah-faq">
                        <i data-lucide="plus-circle"></i>
                        <span>Tambah FAQ</span>
                    </button>
                </div>
            </div>

            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:55px">Urutan</th>
                            <th>Pertanyaan</th>
                            <th>Jawaban</th>
                            <th>Status</th>
                            <th style="width:160px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                        <?php while ($r = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="td-num"><?= $r['urutan'] ?></td>
                            <td class="td-name" style="max-width:250px;">
                                <span style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                                    <?= htmlspecialchars($r['pertanyaan']) ?>
                                </span>
                            </td>
                            <td style="max-width:300px;">
                                <span style="font-size:.82rem;color:var(--muted);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                                    <?= htmlspecialchars($r['jawaban']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($r['aktif'] === 'Ya'): ?>
                                <span class="badge badge-success">Aktif</span>
                                <?php else: ?>
                                <span class="badge badge-danger">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="td-actions">
                                <div class="actions-wrapper">
                                    <button class="action-btn edit btn-edit-faq"
                                        data-id="<?= $r['id'] ?>"
                                        data-pertanyaan="<?= htmlspecialchars($r['pertanyaan']) ?>"
                                        data-jawaban="<?= htmlspecialchars($r['jawaban']) ?>"
                                        data-urutan="<?= $r['urutan'] ?>"
                                        title="Edit">
                                        <i data-lucide="edit-3"></i>
                                    </button>
                                    <button class="action-btn detail btn-toggle-faq" data-id="<?= $r['id'] ?>" title="Toggle Status">
                                        <i data-lucide="<?= $r['aktif'] === 'Ya' ? 'eye-off' : 'eye' ?>"></i>
                                    </button>
                                    <button class="action-btn del btn-hapus-faq" data-id="<?= $r['id'] ?>" data-pertanyaan="<?= htmlspecialchars($r['pertanyaan']) ?>" title="Hapus">
                                        <i data-lucide="trash-2"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <tr><td colspan="5" class="td-empty">Belum ada FAQ.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?= render_pagination($page, $total_pages) ?>
        </div>

    </div>
</main>

<!-- Hidden forms -->
<form id="form-simpan-faq" action="<?= e(base_url("admin/handlers/legacy_action.php?handler=faq")) ?>" method="post" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" id="faq-action" value="simpan">
    <input type="hidden" name="id" id="faq-id">
    <input type="hidden" name="pertanyaan" id="faq-pertanyaan">
    <input type="hidden" name="jawaban" id="faq-jawaban">
    <input type="hidden" name="urutan" id="faq-urutan">
</form>
<form id="form-toggle-faq" action="<?= e(base_url("admin/handlers/legacy_action.php?handler=faq")) ?>" method="post" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="toggle_aktif">
    <input type="hidden" name="id" id="toggle-faq-id">
</form>
<form id="form-hapus-faq" action="<?= e(base_url("admin/handlers/legacy_action.php?handler=faq")) ?>" method="post" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="hapus">
    <input type="hidden" name="id" id="hapus-faq-id">
</form>

<script>
const params = new URLSearchParams(window.location.search);
if (params.has('pesan')) { Swal.fire({ icon:'success',title:'Berhasil!',text:params.get('pesan'),confirmButtonColor:'#0e7c6b',timer:3500,timerProgressBar:true }).then(()=>{const u=new URL(window.location.href);u.searchParams.delete('pesan');window.history.replaceState({},'',u);}); }
if (params.has('error')) { Swal.fire({ icon:'error',title:'Gagal!',text:params.get('error'),confirmButtonColor:'#0e7c6b' }).then(()=>{const u=new URL(window.location.href);u.searchParams.delete('error');window.history.replaceState({},'',u);}); }

function showFaqForm(mode, data) {
    Swal.fire({
        title: mode === 'edit' ? 'Edit FAQ' : 'Tambah FAQ Baru',
        html: '<div style="text-align:left;">' +
              '<div class="form-group"><label style="font-size:.8rem;margin-bottom:4px;display:block;">Pertanyaan</label><input type="text" id="swal-pertanyaan" class="swal2-input" style="margin:0;width:100%" placeholder="Tulis pertanyaan..." value="' + (data?.pertanyaan || '') + '"></div>' +
              '<div class="form-group" style="margin-top:10px"><label style="font-size:.8rem;margin-bottom:4px;display:block;">Jawaban</label><textarea id="swal-jawaban" class="swal2-textarea" style="margin:0;width:100%;min-height:100px" placeholder="Tulis jawaban...">' + (data?.jawaban || '') + '</textarea></div>' +
              '<div class="form-group" style="margin-top:10px"><label style="font-size:.8rem;margin-bottom:4px;display:block;">Urutan</label><input type="number" id="swal-urutan" class="swal2-input" style="margin:0;width:100%" value="' + (data?.urutan || '0') + '" min="0"></div>' +
              '</div>',
        showCancelButton: true,
        confirmButtonColor: '#0e7c6b',
        confirmButtonText: mode === 'edit' ? 'Simpan' : 'Tambah',
        cancelButtonText: 'Batal',
        cancelButtonColor: '#e4e4e7',
        width: 500,
        preConfirm: function() {
            var p = document.getElementById('swal-pertanyaan').value;
            var j = document.getElementById('swal-jawaban').value;
            var u = document.getElementById('swal-urutan').value;
            if (!p || !j) { Swal.showValidationMessage('Pertanyaan dan jawaban wajib diisi!'); return false; }
            return { pertanyaan: p, jawaban: j, urutan: u };
        }
    }).then(function(result) {
        if (result.isConfirmed) {
            document.getElementById('faq-action').value = mode === 'edit' ? 'update' : 'simpan';
            document.getElementById('faq-id').value = data?.id || '';
            document.getElementById('faq-pertanyaan').value = result.value.pertanyaan;
            document.getElementById('faq-jawaban').value = result.value.jawaban;
            document.getElementById('faq-urutan').value = result.value.urutan;
            document.getElementById('form-simpan-faq').submit();
        }
    });
}

// Tambah
document.querySelectorAll('.btn-tambah-faq').forEach(el => {
    el.addEventListener('click', () => showFaqForm('tambah'));
});

// Edit
document.querySelectorAll('.btn-edit-faq').forEach(el => {
    el.addEventListener('click', function() {
        showFaqForm('edit', {
            id: this.dataset.id,
            pertanyaan: this.dataset.pertanyaan,
            jawaban: this.dataset.jawaban,
            urutan: this.dataset.urutan
        });
    });
});

// Toggle
document.querySelectorAll('.btn-toggle-faq').forEach(el => {
    el.addEventListener('click', function() {
        document.getElementById('toggle-faq-id').value = this.dataset.id;
        document.getElementById('form-toggle-faq').submit();
    });
});

// Hapus
document.querySelectorAll('.btn-hapus-faq').forEach(el => {
    el.addEventListener('click', function() {
        const id = this.dataset.id, q = this.dataset.pertanyaan;
        Swal.fire({
            icon:'warning', title:'Hapus FAQ?',
            html:'FAQ ini akan dihapus permanen.',
            showCancelButton:true, confirmButtonColor:'#c0392b', cancelButtonColor:'#e4e4e7',
            confirmButtonText:'Ya, Hapus', cancelButtonText:'Batal'
        }).then(r => { if(r.isConfirmed) { document.getElementById('hapus-faq-id').value=id; document.getElementById('form-hapus-faq').submit(); } });
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
