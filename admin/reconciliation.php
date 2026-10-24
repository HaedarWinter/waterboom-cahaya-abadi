<?php
/**
 * admin/reconciliation.php
 * Rekonsiliasi: Cocokkan Ledger dengan Bank/Bukti Fisik
 */
require_once __DIR__ . '/../app/init.php';
enforce_active_session($koneksi);

if (!has_canonical_permission('reconciliation.manage')) {
    header("Location: /ujikom1/admin/?error=Akses ditolak! Hanya Finance Manager yang dapat melakukan rekonsiliasi.");
    exit;
}

$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');
$status_filter = $_GET['status'] ?? '';

$where = "MONTH(le.entry_date) = ? AND YEAR(le.entry_date) = ?";
$params = [(int)$bulan, (int)$tahun];
$types = "ii";

if ($status_filter === 'unreconciled') {
    $where .= " AND (lr.status IS NULL OR lr.status = 'REVERTED')";
} elseif ($status_filter === 'reconciled') {
    $where .= " AND lr.status = 'RECONCILED'";
}

$stmt_unrec = $koneksi->prepare("SELECT COUNT(*) as c FROM ledger_entries le LEFT JOIN ledger_reconciliations lr ON le.id = lr.ledger_entry_id WHERE lr.status IS NULL OR lr.status = 'REVERTED'");
if ($stmt_unrec) {
    $stmt_unrec->execute();
    $unreconciled_count = $stmt_unrec->get_result()->fetch_assoc()['c'];
} else {
    $unreconciled_count = 0;
}

$stmt_rec = $koneksi->prepare("SELECT COUNT(*) as c FROM ledger_entries le LEFT JOIN ledger_reconciliations lr ON le.id = lr.ledger_entry_id WHERE lr.status = 'RECONCILED'");
if ($stmt_rec) {
    $stmt_rec->execute();
    $reconciled_count = $stmt_rec->get_result()->fetch_assoc()['c'];
} else {
    $reconciled_count = 0;
}

// Pagination
$per_page = 25;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$count_sql = "SELECT COUNT(*) as total FROM ledger_entries le LEFT JOIN ledger_reconciliations lr ON le.id = lr.ledger_entry_id WHERE $where";
$count_stmt = $koneksi->prepare($count_sql);
if ($count_stmt) {
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_rows = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_rows = 0;
}
$total_pages = ceil($total_rows / $per_page);

// Fetch Data
    $sql = "SELECT le.*, ca.name as account_name, COALESCE(a.username, 'SYSTEM') as created_by_name,
            lr.status as reconciliation_status, lr.created_at as reconciliation_at, lr.notes as reconciliation_notes
            FROM ledger_entries le
            LEFT JOIN cash_accounts ca ON le.cash_account_id = ca.id
            LEFT JOIN admin a ON le.created_by = a.id
            LEFT JOIN ledger_reconciliations lr ON le.id = lr.ledger_entry_id
            WHERE $where
            ORDER BY le.entry_date ASC, le.id ASC
            LIMIT ? OFFSET ?";
    $p_list = array_merge($params, [$per_page, $offset]);
    $t_list = $types . "ii";
    $stmt = $koneksi->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($t_list, ...$p_list);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = false;
    }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= e(asset_url('assets/dist/css/app.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/admin_style.css')) ?>">
    <title>Rekonsiliasi Pembayaran — Cahaya Abadi</title>
                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="admin-body">

<?php include 'includes/sidebar.php'; ?>

<main class="admin-main">
    <header class="admin-header">
        <button class="sidebar-toggle">&#9776;</button>
        <div class="header-title">
            <h1 class="page-heading">Rekonsiliasi Pembayaran</h1>
            <span class="breadcrumb"><a href="/ujikom1/admin/">Dashboard</a> / <a href="audit_keuangan.php">Keuangan</a> / Rekonsiliasi</span>
        </div>
    </header>

    <div class="admin-content">
        <!-- STAT CARDS -->
        <div class="stats-grid" style="grid-template-columns: repeat(2, 1fr); margin-bottom: 1.5rem;">
            <div class="stat-card">
                <div class="stat-icon amber"><i data-lucide="alert-circle"></i></div>
                <div>
                    <div class="stat-value"><?= $unreconciled_count ?></div>
                    <div class="stat-label">Belum Direkonsiliasi (Bulan Ini)</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i data-lucide="check-circle"></i></div>
                <div>
                    <div class="stat-value"><?= $reconciled_count ?></div>
                    <div class="stat-label">Sudah Direkonsiliasi (Bulan Ini)</div>
                </div>
            </div>
        </div>

        <!-- TABLE SECTION -->
        <div class="section-card">
            <div class="section-header" style="background:var(--surface); border-bottom:1px solid var(--border-subtle)">
                <div>
                    <h2 class="section-title">Daftar Entri Buku Kas</h2>
                    <p class="section-sub">Cocokkan setiap entri dengan bukti bank atau fisik. Tandai sebagai direkonsiliasi.</p>
                </div>
            </div>

            <!-- FILTER BAR -->
            <div class="filter-bar" style="padding:1rem 1.5rem; background:var(--surface-hover); display:flex; gap:12px; align-items:center; flex-wrap:wrap; border-bottom: 1px solid var(--border-subtle);">
                <form method="get" style="display:flex; gap:10px; flex:1; flex-wrap:wrap;">
                    <select name="bulan" class="search-input" style="width:auto;" onchange="this.form.submit()">
                        <?php for($m=1; $m<=12; $m++): ?>
                            <option value="<?= sprintf('%02d', $m) ?>" <?= $bulan == $m ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="tahun" class="search-input" style="width:auto;" onchange="this.form.submit()">
                        <?php for($y=date('Y'); $y>=2024; $y--): ?>
                            <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="status" class="search-input" style="width:auto;" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <option value="unreconciled" <?= $status_filter === 'unreconciled' ? 'selected' : '' ?>>Belum Direkonsiliasi</option>
                        <option value="reconciled" <?= $status_filter === 'reconciled' ? 'selected' : '' ?>>Sudah Direkonsiliasi</option>
                    </select>
                </form>
            </div>

            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:120px">No. Entri</th>
                            <th style="width:100px">Tanggal</th>
                            <th>Arah</th>
                            <th>Kategori</th>
                            <th>Nominal</th>
                            <th>Sumber</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                        <?php while ($r = $result->fetch_assoc()): ?>
                        <tr style="background: <?= $r['reconciliation_status'] === 'RECONCILED' ? 'rgba(16,185,129,0.05)' : 'rgba(251,146,60,0.05)' ?>;">
                            <td><strong style="color:var(--accent); font-size:0.8rem;"><?= htmlspecialchars($r['entry_no']) ?></strong></td>
                            <td style="font-size:0.75rem; color:var(--fg-muted)"><?= date('d M Y', strtotime($r['entry_date'])) ?></td>
                            <td>
                                <span class="badge <?= $r['direction'] === 'IN' ? 'badge-masuk' : 'badge-keluar' ?>" style="font-size:0.7rem;">
                                    <?= $r['direction'] === 'IN' ? 'MASUK' : 'KELUAR' ?>
                                </span>
                            </td>
                            <td style="font-size:0.75rem; color:var(--fg-muted);"><?= $r['category'] ?></td>
                            <td style="font-weight:700; color: <?= $r['direction'] === 'IN' ? '#0e7c6b' : '#ef4444' ?>">
                                <?= $r['direction'] === 'IN' ? '+' : '-' ?> Rp <?= format_harga($r['amount']) ?>
                            </td>
                            <td style="font-size:0.75rem; color:var(--fg-subtle);">
                                <?= $r['source_type'] ?> #<?= $r['source_id'] ?>
                            </td>
                            <td>
                                <?php if ($r['reconciliation_status'] === 'RECONCILED'): ?>
                                    <span class="badge" style="background:rgba(16,185,129,0.1); color:var(--accent); font-size:0.7rem;">✓ Direkonsiliasi</span>
                                    <div style="font-size:0.65rem; color:var(--fg-subtle); margin-top:2px;">
                                        <?= date('d M Y H:i', strtotime($r['reconciliation_at'])) ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge" style="background:rgba(251,146,60,0.1); color:#f97316; font-size:0.7rem;">⚠ Belum</span>
                                <?php endif; ?>
                            </td>
                            <td class="td-actions">
                                <?php if ($r['reconciliation_status'] !== 'RECONCILED'): ?>
                                <button class="action-btn check btn-reconcile" data-id="<?= $r['id'] ?>" data-entry="<?= $r['entry_no'] ?>" title="Tandai Direkonsiliasi">
                                    <i data-lucide="check-circle-2" style="width:14px;height:14px;"></i>
                                </button>
                                <?php else: ?>
                                <button class="action-btn x btn-unrec" data-id="<?= $r['id'] ?>" data-entry="<?= $r['entry_no'] ?>" title="Batalkan Rekonsiliasi">
                                    <i data-lucide="x-circle" style="width:14px;height:14px;"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <tr><td colspan="8" class="td-empty">Tidak ada entri buku kas untuk periode ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <span class="pagination-info"><?= $offset+1 ?>–<?= min($offset+$per_page, $total_rows) ?> dari <?= $total_rows ?> entri</span>
                <div class="pagination-btns">
                    <a href="?page=<?= $page-1 ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&status=<?= urlencode($status_filter) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">&larr; Sblmny</a>
                    <span class="page-current"><?= $page ?> / <?= $total_pages ?></span>
                    <a href="?page=<?= $page+1 ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&status=<?= urlencode($status_filter) ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">Brktny &rarr;</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Hidden forms -->
<form id="form-reconcile" action="<?= e(base_url("admin/handlers/legacy_action.php?handler=reconciliation")) ?>" method="post" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="reconcile">
    <input type="hidden" name="id" id="rec-id">
    <input type="hidden" name="notes" id="rec-notes">
</form>
<form id="form-unrec" action="<?= e(base_url("admin/handlers/legacy_action.php?handler=reconciliation")) ?>" method="post" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="unreconcile">
    <input type="hidden" name="id" id="unrec-id">
    <input type="hidden" name="reason" id="unrec-reason">
</form>

<script>
lucide.createIcons();

document.querySelectorAll('.btn-reconcile').forEach(el => {
    el.addEventListener('click', function() {
        const id = this.dataset.id, entry = this.dataset.entry;
        Swal.fire({
            title: 'Tandai Direkonsiliasi?',
            html: '<p style="font-size:.88rem;margin-bottom:1rem;">Entri <strong>' + entry + '</strong> dinyatakan cocok dengan bukti bank/fisik.</p>',
            input: 'textarea',
            inputPlaceholder: 'Catatan rekonsiliasi (opsional)...',
            showCancelButton: true,
            confirmButtonColor: '#0e7c6b',
            cancelButtonColor: '#52525b',
            confirmButtonText: 'Ya, Rekonsiliasi',
            cancelButtonText: 'Batal'
        }).then(r => {
            if(r.isConfirmed) {
                document.getElementById('rec-id').value = id;
                document.getElementById('rec-notes').value = r.value || '';
                document.getElementById('form-reconcile').submit();
            }
        });
    });
});

document.querySelectorAll('.btn-unrec').forEach(el => {
    el.addEventListener('click', function() {
        const id = this.dataset.id, entry = this.dataset.entry;
        Swal.fire({
            icon: 'warning',
            title: 'Batalkan Rekonsiliasi?',
            html: 'Entri <strong>' + entry + '</strong> akan ditandai belum direkonsiliasi.',
            input: 'textarea',
            inputPlaceholder: 'Alasan pembatalan...',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#52525b',
            confirmButtonText: 'Ya, Batalkan',
            cancelButtonText: 'Batal'
        }).then(r => {
            if(r.isConfirmed) {
                document.getElementById('unrec-id').value = id;
                document.getElementById('unrec-reason').value = r.value || '';
                document.getElementById('form-unrec').submit();
            }
        });
    });
});

const params = new URLSearchParams(window.location.search);
if(params.has('pesan')) Swal.fire({ icon:'success', title:'Berhasil', text:params.get('pesan'), timer:3000 });
if(params.has('error')) Swal.fire({ icon:'error', title:'Error', text:params.get('error') });
</script>

</body>
</html>
