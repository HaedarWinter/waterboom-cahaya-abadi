<?php
/**
 * admin/buku_kas.php
 * Laporan Buku Kas (Ledger Entries) berbasis Single Source of Truth
 */
require_once '../app/init.php';
enforce_active_session($koneksi);

if (!has_canonical_permission('ledger.view')) {
    header("Location: /ujikom1/admin/?error=Akses ditolak! Anda tidak memiliki izin untuk melihat buku kas.");
    exit;
}

$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');
$kategori = $_GET['kategori'] ?? '';
$direction = $_GET['direction'] ?? '';
$search = $_GET['q'] ?? '';

// Build Query
$where = "MONTH(le.entry_date) = ? AND YEAR(le.entry_date) = ?";
$params = [(int)$bulan, (int)$tahun];
$types = "ii";

if ($kategori) {
    $where .= " AND le.category = ?";
    $params[] = $kategori;
    $types .= "s";
}
if ($direction) {
    $where .= " AND le.direction = ?";
    $params[] = $direction;
    $types .= "s";
}
if ($search) {
    $where .= " AND (le.entry_no LIKE ? OR le.note LIKE ?)";
    $s = "%$search%";
    $params[] = $s;
    $params[] = $s;
    $types .= "ss";
}

// Summary Ringkasan Kas
$stmt_sum = $koneksi->prepare("SELECT 
    SUM(CASE WHEN le.direction = 'IN' THEN le.amount ELSE 0 END) as total_in,
    SUM(CASE WHEN le.direction = 'OUT' THEN le.amount ELSE 0 END) as total_out
    FROM ledger_entries le WHERE MONTH(le.entry_date) = ? AND YEAR(le.entry_date) = ?");
$stmt_sum->bind_param("ii", $bulan, $tahun);
$stmt_sum->execute();
$sum_data = $stmt_sum->get_result()->fetch_assoc();
$total_in = (int)($sum_data['total_in'] ?? 0);
$total_out = (int)($sum_data['total_out'] ?? 0);
$saldo_bersih = $total_in - $total_out;

// Total Saldo Kas Utama Seluruh Waktu
$stmt_saldo_all = $koneksi->query("SELECT 
    SUM(CASE WHEN direction = 'IN' THEN amount ELSE -amount END) as saldo_akhir
    FROM ledger_entries");
$saldo_akhir_total = (int)($stmt_saldo_all->fetch_assoc()['saldo_akhir'] ?? 0);

// Pagination
$per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$count_stmt = $koneksi->prepare("SELECT COUNT(*) as total FROM ledger_entries le WHERE $where");
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $per_page);

// Fetch Data
$sql = "SELECT le.*, ca.name as account_name, COALESCE(a.username, 'SYSTEM') as created_by_name
        FROM ledger_entries le
        LEFT JOIN cash_accounts ca ON le.cash_account_id = ca.id
        LEFT JOIN admin a ON le.created_by = a.id
        WHERE $where
        ORDER BY le.entry_date DESC, le.id DESC
        LIMIT ? OFFSET ?";
$p_list = array_merge($params, [$per_page, $offset]);
$t_list = $types . "ii";
$stmt = $koneksi->prepare($sql);
$stmt->bind_param($t_list, ...$p_list);
$stmt->execute();
$result = $stmt->get_result();

$categories = ['TIKET', 'KODE_UNIK', 'REFUND', 'BIAYA_OPERASIONAL', 'KOREKSI', 'LAINNYA', 'SALDO_AWAL', 'BACKFILL'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buku Kas — Cahaya Abadi</title>
        <!-- Design System (Atomic CSS) -->
    <link rel="stylesheet" href="../public/assets/dist/css/app.min.css">
    
    <!-- Legacy Admin Styles (fallback) -->
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin_style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .badge-in { background: rgba(16, 185, 129, 0.1); color: var(--accent); border: 1px solid rgba(16, 185, 129, 0.2); }
        .badge-out { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); }
    </style>
</head>
<body class="admin-body">

<?php include 'includes/sidebar.php'; ?>

<main class="admin-main">
    <header class="admin-header">
        <button class="sidebar-toggle">&#9776;</button>
        <div class="header-title">
            <h1 class="page-heading">Buku Kas (Ledger)</h1>
            <span class="breadcrumb"><a href="/ujikom1/admin/">Dashboard</a> / <a href="audit_keuangan.php">Keuangan</a> / Buku Kas</span>
        </div>
        <div style="display:flex; gap:10px;">
<form method="post" action="handlers/buku_kas_handler.php" style="display:inline">
    <input type="hidden" name="action" value="export_csv">
    <input type="hidden" name="bulan" value="<?= e($bulan) ?>">
    <input type="hidden" name="tahun" value="<?= e($tahun) ?>">
    <input type="hidden" name="kategori" value="<?= e($kategori) ?>">
    <input type="hidden" name="direction" value="<?= e($direction) ?>">
    <?= csrf_field() ?>
    <button type="submit" class="header-logout" style="background:var(--surface-hover); border:1px solid var(--border); color:var(--fg-muted);">
        <i data-lucide="download"></i><span>Export CSV</span>
    </button>
</form>
            <button onclick="window.print()" class="header-logout" style="background:var(--surface-hover); border:1px solid var(--border); color:var(--fg-muted);">
                <i data-lucide="printer"></i><span>Cetak PDF</span>
            </button>
        </div>
    </header>

    <div class="admin-content">
        <!-- STAT CARDS -->
        <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 1.5rem;">
            <div class="stat-card">
                <div class="stat-icon green"><i data-lucide="arrow-down-left"></i></div>
                <div>
                    <div class="stat-value" style="font-size:1.25rem; color:var(--accent);">Rp <?= format_harga($total_in) ?></div>
                    <div class="stat-label">Arus Masuk (Bulan Ini)</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon amber"><i data-lucide="arrow-up-right"></i></div>
                <div>
                    <div class="stat-value" style="font-size:1.25rem; color:var(--danger);">Rp <?= format_harga($total_out) ?></div>
                    <div class="stat-label">Arus Keluar (Bulan Ini)</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon teal"><i data-lucide="scale"></i></div>
                <div>
                    <div class="stat-value" style="font-size:1.25rem; color:var(--info);">Rp <?= format_harga($saldo_bersih) ?></div>
                    <div class="stat-label">Surplus / Defisit Bulan Ini</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon gray"><i data-lucide="wallet"></i></div>
                <div>
                    <div class="stat-value" style="font-size:1.25rem;">Rp <?= format_harga($saldo_akhir_total) ?></div>
                    <div class="stat-label">Total Saldo Kas Akumulatif</div>
                </div>
            </div>
        </div>

        <!-- TABLE SECTION -->
        <div class="section-card">
            <div class="section-header" style="background:var(--surface); border-bottom:1px solid var(--border-subtle)">
                <div>
                    <h2 class="section-title">Jurnal Entri Buku Kas</h2>
                    <p class="section-sub">Catatan mutasi kas masuk dan keluar append-only (tidak dapat dimanipulasi).</p>
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
                    <select name="direction" class="search-input" style="width:auto;" onchange="this.form.submit()">
                        <option value="">Semua Arah</option>
                        <option value="IN" <?= $direction === 'IN' ? 'selected' : '' ?>>Masuk (IN)</option>
                        <option value="OUT" <?= $direction === 'OUT' ? 'selected' : '' ?>>Keluar (OUT)</option>
                    </select>
                    <select name="kategori" class="search-input" style="width:auto;" onchange="this.form.submit()">
                        <option value="">Semua Kategori</option>
                        <?php foreach($categories as $c): ?>
                            <option value="<?= $c ?>" <?= $kategori === $c ? 'selected' : '' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nomor entri / catatan..." class="search-input" style="flex:1; min-width:200px;">
                    <button type="submit" class="btn-search"><i data-lucide="search"></i></button>
                    <?php if ($search || $kategori || $direction): ?>
                        <a href="buku_kas.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" class="btn-clear"><i data-lucide="x"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:120px">No. Entri</th>
                            <th style="width:100px">Tanggal</th>
                            <th>Akun Kas</th>
                            <th>Arah</th>
                            <th>Kategori</th>
                            <th>Nominal</th>
                            <th>Sumber</th>
                            <th>Catatan</th>
                            <th>Petugas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                        <?php while ($r = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong style="color:var(--accent); font-size:0.8rem;"><?= htmlspecialchars($r['entry_no']) ?></strong></td>
                            <td style="font-size:0.75rem; color:var(--fg-muted)"><?= date('d M Y', strtotime($r['entry_date'])) ?></td>
                            <td style="font-size:0.8rem"><?= htmlspecialchars($r['account_name'] ?? 'Kas Utama') ?></td>
                            <td>
                                <span class="badge <?= $r['direction'] === 'IN' ? 'badge-in' : 'badge-out' ?>">
                                    <?= $r['direction'] === 'IN' ? 'MASUK' : 'KELUAR' ?>
                                </span>
                            </td>
                            <td><span style="font-size:0.75rem; font-weight:700; color:var(--fg-muted);"><?= $r['category'] ?></span></td>
                            <td style="font-weight:700; color: <?= $r['direction'] === 'IN' ? '#0e7c6b' : '#ef4444' ?>">
                                <?= $r['direction'] === 'IN' ? '+' : '-' ?> Rp <?= format_harga($r['amount']) ?>
                            </td>
                            <td style="font-size:0.75rem; color:var(--fg-subtle);">
                                <?= $r['source_type'] ?> #<?= $r['source_id'] ?>
                            </td>
                            <td style="font-size:0.85rem"><?= htmlspecialchars($r['note'] ?? '-') ?></td>
                            <td style="font-size:0.75rem; color:var(--fg-muted)"><?= htmlspecialchars($r['created_by_name']) ?></td>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <tr><td colspan="9" class="td-empty">Tidak ada mutasi buku kas untuk periode ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <span class="pagination-info"><?= $offset+1 ?>–<?= min($offset+$per_page, $total_rows) ?> dari <?= $total_rows ?> entri</span>
                <div class="pagination-btns">
                    <a href="?page=<?= $page-1 ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&kategori=<?= urlencode($kategori) ?>&direction=<?= urlencode($direction) ?>&q=<?= urlencode($search) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">&larr; Sblmny</a>
                    <span class="page-current"><?= $page ?> / <?= $total_pages ?></span>
                    <a href="?page=<?= $page+1 ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&kategori=<?= urlencode($kategori) ?>&direction=<?= urlencode($direction) ?>&q=<?= urlencode($search) ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">Brktnya &rarr;</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
lucide.createIcons();
</script>
</body>
</html>
