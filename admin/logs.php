<?php
/**
 * admin/logs.php
 * Halaman Audit Logs — Melihat histori aktivitas admin
 * Hanya Super Admin yang bisa mengakses halaman ini
 */
require_once '../app/init.php';
enforce_active_session($koneksi);

if (!has_canonical_permission('activity.view')) {
    header("Location: /ujikom1/admin/?error=Halaman Log Aktivitas hanya dapat diakses oleh Super Admin.");
    exit;
}

// ─── FILTER & PENCARIAN ───
$search = $_GET['q'] ?? '';
$where = "1=1";
$params = [];
$types = "";

if ($search) {
    $where = "(admin_username LIKE ? OR action LIKE ? OR details LIKE ? OR target_table LIKE ?)";
    $s = "%$search%";
    $params = [$s, $s, $s, $s];
    $types = "ssss";
}

// ─── PAGINATION ───
$per_page = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$count_sql = "SELECT COUNT(*) as total FROM audit_logs WHERE $where";
$count_stmt = $koneksi->prepare($count_sql);
if ($types) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = max(1, ceil($total / $per_page));

// ─── AMBIL DATA ───
$data_sql = "SELECT * FROM audit_logs WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $koneksi->prepare($data_sql);
$data_params = array_merge($params, [$per_page, $offset]);
$data_types = $types . "ii";
$stmt->bind_param($data_types, ...$data_params);
$stmt->execute();
$result = $stmt->get_result();

// Mapper warna untuk Action
function get_action_badge($action) {
    $class = 'badge-pending'; // default gray-ish
    if (strpos($action, 'ADD') !== false) $class = 'badge-success';
    if (strpos($action, 'EDIT') !== false) $class = 'badge-success';
    if (strpos($action, 'DELETE') !== false) $class = 'badge-danger';
    if (strpos($action, 'LOGIN') !== false) $class = 'badge-success';
    if (strpos($action, 'FAIL') !== false) $class = 'badge-danger';
    
    return "<span class='badge $class'>$action</span>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Aktivitas — Cahaya Abadi</title>
    <link rel="stylesheet" href="/ujikom1/public/assets/dist/css/app.min.css">
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .log-details { font-size: 0.8rem; color: var(--fg-muted); max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .log-action-cell { font-family: monospace; font-size: 0.75rem; font-weight: 600; }
        .ip-badge { font-family: monospace; font-size: 0.7rem; color: var(--fg); background: var(--surface-hover); border: 1px solid var(--border-subtle); padding: 2px 5px; border-radius: 4px; }
    </style>
</head>
<body class="admin-body">

<?php include 'includes/sidebar.php'; ?>

<!-- MAIN -->
<main class="admin-main">
    <header class="admin-header">
        <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">&#9776;</button>
        <div class="header-title">
            <h1 class="page-heading">Log Aktivitas Sistem</h1>
            <span class="breadcrumb"><a href="/ujikom1/admin/">Dashboard</a> / Audit Logs</span>
        </div>
        <a href="logout.php?token=<?= e(csrf_token()) ?>" class="header-logout swal-logout"><i data-lucide="log-out"></i><span>Keluar</span></a>
    </header>

    <div class="admin-content">

        <div class="section-card">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Histori Aktivitas</h2>
                    <p class="section-sub">Memantau tindakan administrator secara real-time</p>
                </div>
                <div class="header-actions">
                    <form method="get" class="search-form">
                        <input type="text" name="q" class="search-input" placeholder="Cari aksi/admin/pesan..." value="<?= e($search) ?>">
                        <button type="submit" class="btn-search"><i data-lucide="search"></i></button>
                        <?php if ($search): ?><a href="logs.php" class="btn-clear"><i data-lucide="x"></i></a><?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:46px">No</th>
                            <th>Waktu</th>
                            <th>Administrator</th>
                            <th>Aksi</th>
                            <th>Tabel</th>
                            <th>Keterangan</th>
                            <th>IP Address</th>
                            <th style="width:60px">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                        <?php $no = $offset + 1; while ($r = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="td-num"><?= $no++ ?></td>
                            <td><span class="date-badge"><?= date('d/m/y H:i:s', strtotime($r['created_at'])) ?></span></td>
                            <td class="td-name"><strong><?= e($r['admin_username']) ?></strong></td>
                            <td class="log-action-cell"><?= get_action_badge($r['action']) ?></td>
                            <td style="font-size:0.8rem; color:var(--fg-muted);"><?= e($r['target_table'] ?: '—') ?></td>
                            <td class="log-details"><?= e($r['details']) ?></td>
                            <td><span class="ip-badge"><?= e($r['ip_address']) ?></span></td>
                            <td class="td-actions">
                                <button class="action-btn detail btn-view-log" 
                                        data-admin="<?= e($r['admin_username']) ?>"
                                        data-action="<?= e($r['action']) ?>"
                                        data-time="<?= date('d M Y H:i:s', strtotime($r['created_at'])) ?>"
                                        data-details="<?= e($r['details']) ?>"
                                        data-table="<?= e($r['target_table']) ?>"
                                        data-id="<?= $r['target_id'] ?>"
                                        data-ip="<?= e($r['ip_address']) ?>">
                                    <i data-lucide="info"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <tr><td colspan="8" class="td-empty">Belum ada aktivitas yang tercatat.</td></tr>
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
                    <a href="<?= $page > 1 ? '?page='.($page-1).'&q='.urlencode($search) : '#' ?>" 
                       class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"
                       <?= $page <= 1 ? 'onclick="return false;"' : '' ?>>&larr; Sblmny</a>
                    <span class="page-current"><?= $page ?> / <?= $total_pages ?></span>
                    <a href="<?= $page < $total_pages ? '?page='.($page+1).'&q='.urlencode($search) : '#' ?>" 
                       class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"
                       <?= $page >= $total_pages ? 'onclick="return false;"' : '' ?>>Brktny &rarr;</a>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<script>
// Detail Log Viewer
document.querySelectorAll('.btn-view-log').forEach(el => {
    el.addEventListener('click', function() {
        const d = this.dataset;
        Swal.fire({
            title: 'Detail Aktivitas',
            html: `
                <div style="text-align:left; font-size:0.9rem; line-height:1.6;">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr style=">border-bottom:1px solid var(--border-subtle);"><td style="padding:8px; color:var(--fg-muted); width:100px;">Waktu</td><td style="padding:8px;"><strong>${d.time}</strong></td></tr>
                        <tr style=">border-bottom:1px solid var(--border-subtle);"><td style="padding:8px; color:var(--fg-muted);">Admin</td><td style="padding:8px;">${d.admin}</td></tr>
                        <tr style=">border-bottom:1px solid var(--border-subtle);"><td style="padding:8px; color:var(--fg-muted);">Aksi</td><td style="padding:8px;"><span class="badge badge-success">${d.action}</span></td></tr>
                        <tr style=">border-bottom:1px solid var(--border-subtle);"><td style="padding:8px; color:var(--fg-muted);">Target</td><td style="padding:8px;">${d.table || '—'} #${d.id || '—'}</td></tr>
                        <tr style=">border-bottom:1px solid var(--border-subtle);"><td style="padding:8px; color:var(--fg-muted);">IP Address</td><td style="padding:8px;"><span class="ip-badge">${d.ip}</span></td></tr>
                        <tr><td style="padding:8px 8px 0 8px; color:var(--fg-muted);" colspan="2">Keterangan:</td></tr>
                        <tr><td style="padding:4px 8px 8px 8px; font-style:italic;" colspan="2">${d.details || 'Tidak ada keterangan tambahan.'}</td></tr>
                    </table>
                </div>
            `,
            confirmButtonColor: '#0e7c6b',
            confirmButtonText: 'Tutup',
            width: 500
        });
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
