<?php
/**
 * Halaman Admin: Kelola Review & Rating
 */
require_once '../app/init.php';
enforce_active_session($koneksi);
check_session_timeout();

if (!has_permission('reviews', 'view')) {
    header("Location: /ujikom1/admin/?error=Akses ditolak! Anda tidak memiliki izin untuk mengelola Review.");
    exit;
}

// Filter
$filter_status = $_GET['status'] ?? '';
$search = $_GET['q'] ?? '';

$where = "1=1";
$params = [];
$types = "";

if ($filter_status && in_array($filter_status, ['Pending','Approved','Ditolak'])) {
    $where .= " AND status = ?";
    $params[] = $filter_status;
    $types .= "s";
}
if ($search) {
    $where .= " AND (nama LIKE ? OR ulasan LIKE ?)";
    $s = "%$search%";
    $params[] = $s; $params[] = $s;
    $types .= "ss";
}

// Pagination
$per_page = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$count_stmt = $koneksi->prepare("SELECT COUNT(*) as total FROM reviews WHERE $where");
if ($types) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = max(1, ceil($total / $per_page));

$data_params = array_merge($params, [$per_page, $offset]);
$data_types = $types . "ii";
$stmt = $koneksi->prepare("SELECT * FROM reviews WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
if ($data_types) $stmt->bind_param($data_types, ...$data_params);
$stmt->execute();
$result = $stmt->get_result();

// Stats
$stats_pending  = $koneksi->query("SELECT COUNT(*) as c FROM reviews WHERE status = 'Pending'")->fetch_assoc()['c'];
$stats_approved = $koneksi->query("SELECT COUNT(*) as c FROM reviews WHERE status = 'Approved'")->fetch_assoc()['c'];
$avg_rating     = $koneksi->query("SELECT ROUND(AVG(rating), 1) as avg FROM reviews WHERE status = 'Approved'")->fetch_assoc()['avg'] ?? 0;

$status_colors = [
    'Pending'  => ['bg'=>'#fffbeb','color'=>'#92400e','border'=>'#fef3c7'],
    'Approved' => ['bg'=>'#ecfdf5','color'=>'#065f46','border'=>'#d1fae5'],
    'Ditolak'  => ['bg'=>'#fef2f2','color'=>'#991b1b','border'=>'#fee2e2'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Review — Cahaya Abadi</title>
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
            <h1 class="page-heading">Kelola Review</h1>
            <span class="breadcrumb"><a href="/ujikom1/admin/">Dashboard</a> / Review</span>
        </div>
        <a href="logout.php" class="header-logout swal-logout"><i data-lucide="log-out"></i><span>Keluar</span></a>
    </header>

    <div class="admin-content">

        <!-- STAT CARDS -->
        <div class="stats-grid">
            <div class="stat-card amber">
                <div class="stat-icon"><i data-lucide="clock"></i></div>
                <div>
                    <div class="stat-value"><?= $stats_pending ?></div>
                    <div class="stat-label">Menunggu Review</div>
                </div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i data-lucide="check-circle"></i></div>
                <div>
                    <div class="stat-value"><?= $stats_approved ?></div>
                    <div class="stat-label">Disetujui</div>
                </div>
            </div>
            <div class="stat-card blue">
                <div class="stat-icon"><i data-lucide="star"></i></div>
                <div>
                    <div class="stat-value"><?= $avg_rating ?: '-' ?></div>
                    <div class="stat-label">Rata-rata Rating</div>
                </div>
            </div>
        </div>

        <!-- TABLE -->
        <div class="section-card">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Data Review</h2>
                    <p class="section-sub"><?= $total ?> review ditemukan</p>
                </div>
                <div class="header-actions">
                    <div class="filter-group">
                        <a href="reviews.php" class="filter-pill <?= !$filter_status ? 'active' : '' ?>">Semua</a>
                        <a href="reviews.php?status=Pending" class="filter-pill <?= $filter_status === 'Pending' ? 'active' : '' ?>">Pending</a>
                        <a href="reviews.php?status=Approved" class="filter-pill <?= $filter_status === 'Approved' ? 'active' : '' ?>">Approved</a>
                        <a href="reviews.php?status=Ditolak" class="filter-pill <?= $filter_status === 'Ditolak' ? 'active' : '' ?>">Ditolak</a>
                    </div>
                    <form method="get" class="search-form">
                        <?php if ($filter_status): ?><input type="hidden" name="status" value="<?= $filter_status ?>"><?php endif; ?>
                        <input type="text" name="q" class="search-input" placeholder="Cari nama/ulasan..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn-search"><i data-lucide="search"></i></button>
                        <?php if ($search): ?><a href="reviews.php<?= $filter_status ? '?status='.$filter_status : '' ?>" class="btn-clear"><i data-lucide="x"></i></a><?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:46px">No</th>
                            <th>Nama</th>
                            <th>Rating</th>
                            <th>Ulasan</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <th style="width:160px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                        <?php $no = $offset + 1; while ($r = $result->fetch_assoc()):
                            $sc = $status_colors[$r['status']] ?? $status_colors['Pending'];
                        ?>
                        <tr>
                            <td class="td-num"><?= $no++ ?></td>
                            <td class="td-name"><?= htmlspecialchars($r['nama']) ?></td>
                            <td>
                                <span style="color:#f59e0b;font-size:.9rem;letter-spacing:1px;">
                                    <?= str_repeat('★', $r['rating']) ?><?= str_repeat('☆', 5 - $r['rating']) ?>
                                </span>
                            </td>
                            <td style="max-width:250px;">
                                <span style="font-size:.82rem;color:var(--fg);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                                    <?= htmlspecialchars($r['ulasan']) ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $badge_class = 'badge-pending';
                                if ($r['status'] === 'Approved') $badge_class = 'badge-success';
                                elseif ($r['status'] === 'Ditolak') $badge_class = 'badge-danger';
                                ?>
                                <span class="badge <?= $badge_class ?>"><?= $r['status'] ?></span>
                            </td>
                            <td><span class="date-badge"><?= date('d M Y', strtotime($r['created_at'])) ?></span></td>
                            <td class="td-actions">
                                <div class="actions-wrapper">
                                    <?php if ($r['status'] !== 'Approved'): ?>
                                    <button class="action-btn detail btn-approve" data-id="<?= $r['id'] ?>" data-nama="<?= htmlspecialchars($r['nama']) ?>" title="Setujui">
                                        <i data-lucide="check"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($r['status'] !== 'Ditolak'): ?>
                                    <button class="action-btn edit btn-tolak" data-id="<?= $r['id'] ?>" data-nama="<?= htmlspecialchars($r['nama']) ?>" title="Tolak">
                                        <i data-lucide="x"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button class="action-btn del btn-hapus-review" data-id="<?= $r['id'] ?>" data-nama="<?= htmlspecialchars($r['nama']) ?>" title="Hapus">
                                        <i data-lucide="trash-2"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <tr><td colspan="7" class="td-empty">Tidak ada data review.</td></tr>
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
                    <a href="<?= $page > 1 ? '?page=' . ($page - 1) . '&status=' . $filter_status . '&q=' . urlencode($search) : '#' ?>" 
                       class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"
                       <?= $page <= 1 ? 'onclick="return false;"' : '' ?>>
                        &larr; Sebelumnya
                    </a>
                    <span class="page-current"><?= $page ?> / <?= $total_pages ?></span>
                    <a href="<?= $page < $total_pages ? '?page=' . ($page + 1) . '&status=' . $filter_status . '&q=' . urlencode($search) : '#' ?>" 
                       class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"
                       <?= $page >= $total_pages ? 'onclick="return false;"' : '' ?>>
                        Berikutnya &rarr;
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<!-- Hidden forms -->
<form id="form-approve" action="handlers/review_handler.php" method="post" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="approve">
    <input type="hidden" name="id" id="approve-id">
</form>
<form id="form-tolak" action="handlers/review_handler.php" method="post" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="tolak">
    <input type="hidden" name="id" id="tolak-id">
</form>
<form id="form-hapus-review" action="handlers/review_handler.php" method="post" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="hapus">
    <input type="hidden" name="id" id="hapus-review-id">
</form>

<script>
const params = new URLSearchParams(window.location.search);
if (params.has('pesan')) { Swal.fire({ icon:'success',title:'Berhasil!',text:params.get('pesan'),confirmButtonColor:'#0e7c6b',timer:3500,timerProgressBar:true }).then(()=>{const u=new URL(window.location.href);u.searchParams.delete('pesan');window.history.replaceState({},'',u);}); }
if (params.has('error')) { Swal.fire({ icon:'error',title:'Gagal!',text:params.get('error'),confirmButtonColor:'#0e7c6b' }).then(()=>{const u=new URL(window.location.href);u.searchParams.delete('error');window.history.replaceState({},'',u);}); }

// Approve
document.querySelectorAll('.btn-approve').forEach(el => {
    el.addEventListener('click', function() {
        const id = this.dataset.id, nama = this.dataset.nama;
        Swal.fire({
            icon:'question', title:'Setujui Review?',
            html:'Review dari <strong>' + nama + '</strong> akan ditampilkan di website.',
            showCancelButton:true, confirmButtonColor:'#0e7c6b', cancelButtonColor:'#e4e4e7',
            confirmButtonText:'Ya, Setujui', cancelButtonText:'Batal'
        }).then(r => { if(r.isConfirmed) { document.getElementById('approve-id').value=id; document.getElementById('form-approve').submit(); } });
    });
});

// Tolak
document.querySelectorAll('.btn-tolak').forEach(el => {
    el.addEventListener('click', function() {
        const id = this.dataset.id, nama = this.dataset.nama;
        Swal.fire({
            icon:'warning', title:'Tolak Review?',
            html:'Review dari <strong>' + nama + '</strong> akan ditolak.',
            showCancelButton:true, confirmButtonColor:'#c0392b', cancelButtonColor:'#e4e4e7',
            confirmButtonText:'Ya, Tolak', cancelButtonText:'Batal'
        }).then(r => { if(r.isConfirmed) { document.getElementById('tolak-id').value=id; document.getElementById('form-tolak').submit(); } });
    });
});

// Hapus
document.querySelectorAll('.btn-hapus-review').forEach(el => {
    el.addEventListener('click', function() {
        const id = this.dataset.id, nama = this.dataset.nama;
        Swal.fire({
            icon:'warning', title:'Hapus Review?',
            html:'Review dari <strong>' + nama + '</strong> akan dihapus permanen.',
            showCancelButton:true, confirmButtonColor:'#c0392b', cancelButtonColor:'#e4e4e7',
            confirmButtonText:'Ya, Hapus', cancelButtonText:'Batal'
        }).then(r => { if(r.isConfirmed) { document.getElementById('hapus-review-id').value=id; document.getElementById('form-hapus-review').submit(); } });
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
