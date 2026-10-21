<?php
/**
 * admin/audit_keuangan.php
 * Halaman Audit Keuangan Profesional (Akuntan Pro)
 */
require_once '../app/init.php';
enforce_active_session($koneksi);

// ─── AUTO-CREATE TABLE (Self-healing schema) ───
$koneksi->query("CREATE TABLE IF NOT EXISTS laporan_keuangan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    tipe ENUM('Masuk', 'Keluar') NOT NULL DEFAULT 'Keluar',
    kategori VARCHAR(50) NOT NULL DEFAULT 'Operasional',
    nominal DECIMAL(15, 2) NOT NULL DEFAULT 0,
    keterangan TEXT,
    lampiran VARCHAR(255),
    tanggal DATE NOT NULL,
    status ENUM('Final', 'Draft') DEFAULT 'Final',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tanggal (tanggal),
    INDEX idx_tipe (tipe)
)");

// Cek hak akses: finance.view
if (!has_permission('finance', 'view')) {
    header("Location: /ujikom1/admin/?error=Akses ditolak! Anda tidak memiliki izin untuk melihat laporan keuangan.");
    exit;
}

$can_manage = has_permission('finance', 'manage');

// ─── FILTER & PAGINATION ───
$bulan   = $_GET['bulan'] ?? date('m');
$tahun   = $_GET['tahun'] ?? date('Y');
$search  = $_GET['q'] ?? '';
$kategori= $_GET['kategori'] ?? '';

$where = "MONTH(tanggal) = ? AND YEAR(tanggal) = ?";
$params = [(int)$bulan, (int)$tahun];
$types = "ii";

if ($search) {
    $where .= " AND (keterangan LIKE ? OR kategori LIKE ?)";
    $s = "%$search%";
    $params[] = $s; $params[] = $s;
    $types .= "ss";
}
if ($kategori) {
    $where .= " AND kategori = ?";
    $params[] = $kategori;
    $types .= "s";
}

// ─── HITUNG RINGKASAN (PRO FOCUSED) ───
$pajak_rate = (float)get_setting('pajak_persen', 10) / 100;

// 1. Pendapatan Reservasi (Sesuai Prinsip Realisasi & Akrual: Dikonfirmasi & Selesai)
$stmt_rev = $koneksi->prepare("SELECT SUM(total_harga) as s FROM reservasi WHERE (status = 'Selesai' OR status = 'Dikonfirmasi') AND MONTH(created_at) = ? AND YEAR(created_at) = ?");
$stmt_rev->bind_param("ii", $bulan, $tahun);
$stmt_rev->execute();
$rev_system = $stmt_rev->get_result()->fetch_assoc()['s'] ?? 0;

// 2. Transaksi Manual (Masuk & Keluar)
$stmt_sum = $koneksi->prepare("SELECT 
    SUM(CASE WHEN tipe = 'Masuk' THEN nominal ELSE 0 END) as masuk,
    SUM(CASE WHEN tipe = 'Keluar' THEN nominal ELSE 0 END) as keluar
    FROM laporan_keuangan WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
$stmt_sum->bind_param("ii", $bulan, $tahun);
$stmt_sum->execute();
$sums = $stmt_sum->get_result()->fetch_assoc();

$total_pendapatan_kotor = $rev_system + ($sums['masuk'] ?? 0);
$total_pengeluaran      = $sums['keluar'] ?? 0;

// Hitung Estimasi Pajak (Dari Pendapatan Sistem & Manual Masuk)
$estimasi_pajak = $total_pendapatan_kotor - ($total_pendapatan_kotor / (1 + $pajak_rate));
$total_pendapatan_bersih = $total_pendapatan_kotor - $estimasi_pajak - $total_pengeluaran;

// ─── DATA CHART (30 HARI TERAKHIR) ───
$chart_labels = [];
$chart_income = [];
$chart_expense = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d M', strtotime($d));
    
    // Income per day (System + Manual)
    $stmt_i = $koneksi->prepare("SELECT 
        (SELECT COALESCE(SUM(total_harga),0) FROM reservasi WHERE DATE(created_at) = ? AND status = 'Selesai') +
        (SELECT COALESCE(SUM(nominal),0) FROM laporan_keuangan WHERE tipe = 'Masuk' AND tanggal = ?) as i");
    $stmt_i->bind_param("ss", $d, $d);
    $stmt_i->execute();
    $chart_income[] = (float)$stmt_i->get_result()->fetch_assoc()['i'];
    
    // Expense per day
    $stmt_e = $koneksi->prepare("SELECT COALESCE(SUM(nominal),0) as e FROM laporan_keuangan WHERE tipe = 'Keluar' AND tanggal = ?");
    $stmt_e->bind_param("s", $d);
    $stmt_e->execute();
    $chart_expense[] = (float)$stmt_e->get_result()->fetch_assoc()['e'];
}

// ─── ANTREAN VERIFIKASI (VERIFICATION QUEUE) ───
$verify_queue = $koneksi->query("SELECT * FROM reservasi WHERE status = 'Pending' AND metode_bayar = 'Transfer Bank' AND bukti_bayar IS NOT NULL ORDER BY created_at ASC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$verify_count = $koneksi->query("SELECT COUNT(*) as n FROM reservasi WHERE status = 'Pending' AND metode_bayar = 'Transfer Bank' AND bukti_bayar IS NOT NULL")->fetch_assoc()['n'];

// ─── AMBIL DATA TABEL (SQL UNION UNTUK OTOMATISASI) ───
$per_page = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// Base Virtual Table
$union_base = "
    (SELECT id, admin_id, tipe, kategori, nominal, keterangan, lampiran, tanggal, status, created_at, 'manual' as source
     FROM laporan_keuangan)
    UNION ALL
    (SELECT id, 0 as admin_id, 'Masuk' as tipe, 'Reservasi' as kategori, total_harga as nominal, 
     CONCAT('Booking ', kode_booking, ' (', nama, ')') as keterangan, NULL as lampiran, DATE(created_at) as tanggal, status, created_at, 'system' as source
     FROM reservasi WHERE status IN ('Dikonfirmasi', 'Selesai'))
";

$count_sql = "SELECT COUNT(*) as total FROM ($union_base) AS t WHERE $where";
$count_stmt = $koneksi->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $per_page);

$sql = "SELECT t.*, COALESCE(a.username, 'SYSTEM') as username 
        FROM ($union_base) AS t 
        LEFT JOIN admin a ON t.admin_id = a.id 
        WHERE $where 
        ORDER BY t.tanggal DESC, t.id DESC 
        LIMIT ? OFFSET ?";
$p_list = array_merge($params, [$per_page, $offset]);
$t_list = $types . "ii";
$stmt = $koneksi->prepare($sql);
$stmt->bind_param($t_list, ...$p_list);
$stmt->execute();
$result = $stmt->get_result();

$kategori_list = ['Reservasi', 'Operasional', 'Gaji Karyawan', 'Pemeliharaan', 'Pajak', 'Inventaris', 'Listrik & Air', 'Promosi', 'Lainnya'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Keuangan — Cahaya Abadi</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin_style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .finance-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
        .fin-card { background: var(--surface); padding: 1.5rem; border-radius: 16px; border: 1px solid var(--border); box-shadow: var(--shadow); }
        .fin-card.profit { border-left: 4px solid var(--accent); }
        .fin-card.expense { border-left: 4px solid var(--danger); }
        .fin-card.net { border-left: 4px solid var(--info); background: var(--surface-hover); }
        .fin-label { font-size: 0.75rem; font-weight: 700; color: var(--fg-muted); text-transform: uppercase; margin-bottom: 0.5rem; letter-spacing: 0.5px; }
        .fin-value { font-size: 1.5rem; font-weight: 900; color: var(--fg); }
        .fin-sub { font-size: 0.7rem; color: var(--fg-subtle); margin-top: 4px; }
        
        .badge-masuk { background: rgba(16, 185, 129, 0.1); color: var(--accent); border: 1px solid rgba(16, 185, 129, 0.2); }
        .badge-keluar { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); }
        
        @media print {
            .admin-sidebar, .admin-header, .header-actions, .filter-bar, .action-btn, .pagination, .btn-add { display: none !important; }
            .admin-main { margin: 0; padding: 0; }
            .fin-card { border: 1px solid #ddd; box-shadow: none; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'includes/sidebar.php'; ?>

<main class="admin-main">
    <header class="admin-header">
        <button class="sidebar-toggle">&#9776;</button>
        <div class="header-title">
            <h1 class="page-heading">Audit Keuangan</h1>
            <span class="breadcrumb"><a href="/ujikom1/admin/">Dashboard</a> / Keuangan</span>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="handlers/keuangan_handler.php?action=export_csv" class="header-logout" style="background:var(--surface-hover); border:1px solid var(--border); color:var(--fg-muted);">
                <i data-lucide="download"></i><span>Export CSV</span>
            </a>
            <button onclick="window.print()" class="header-logout" style="background:var(--surface-hover); border:1px solid var(--border); color:var(--fg-muted);">
                <i data-lucide="printer"></i><span>Cetak PDF</span>
            </button>
        </div>
    </header>

    <div class="admin-content">
        
        <!-- ROW 1: SUMMARY CARDS & VERIFICATION QUEUE -->
        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:1.5rem; margin-bottom:1.5rem;">
            <!-- Financial Summary Cards -->
            <div class="finance-grid" style="margin-bottom:0;">
                <div class="fin-card profit">
                    <div class="fin-label">Total Pendapatan (Gross)</div>
                    <div class="fin-value" style="color:var(--accent)">Rp <?= format_harga($total_pendapatan_kotor) ?></div>
                    <div class="fin-sub">Bulan: <?= date('F', mktime(0,0,0,$bulan,1)) ?></div>
                </div>
                <div class="fin-card net">
                    <div class="fin-label">Estimasi Kewajiban Pajak</div>
                    <div class="fin-value" style="color:#eab308">Rp <?= format_harga($estimasi_pajak) ?></div>
                    <div class="fin-sub">PB1/PPN (<?= (int)get_setting('pajak_persen', 10) ?>%)</div>
                </div>
                <div class="fin-card net" style="border-left-color:var(--info)">
                    <div class="fin-label">Laba Bersih Estimasi</div>
                    <div class="fin-value" style="color:var(--info)">Rp <?= format_harga($total_pendapatan_bersih) ?></div>
                    <div class="fin-sub">Setelah Pajak & Pengeluaran</div>
                </div>
            </div>

            <!-- Verification Alert Queue -->
            <div class="fin-card" style="padding: 1.2rem; border-left: 4px solid #ef4444; background: rgba(239, 68, 68, 0.02);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                    <div class="fin-label" style="margin:0; color:#ef4444">Antrean Verifikasi</div>
                    <span class="badge badge-danger"><?= $verify_count ?> Baru</span>
                </div>
                <div class="verification-mini-list">
                    <?php if (count($verify_queue) > 0): ?>
                        <?php foreach($verify_queue as $v): ?>
                        <a href="reservasi.php?status=Pending&q=<?= $v['kode_booking'] ?>" class="v-mini-item" style="display:flex; justify-content:space-between; text-decoration:none; padding:8px; border-radius:8px; background:var(--surface); border:1px solid var(--border-subtle); margin-bottom:6px; font-size:0.75rem;">
                            <strong style="color:var(--fg)"><?= $v['kode_booking'] ?></strong>
                            <span style="color:var(--fg-muted)">Rp <?= format_harga($v['total_harga']) ?></span>
                            <i data-lucide="chevron-right" style="width:14px; height:14px; color:#ef4444"></i>
                        </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center; padding:10px; color:#ccc; font-size:0.7rem;">Semua bersih! Tidak ada antrean.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ROW 2: CHART TRENDS -->
        <div class="section-card" style="margin-bottom:1.5rem;">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Tren Keuangan 30 Hari Terakhir</h2>
                    <p class="section-sub">Analisis perbandingan arus kas masuk dan keluar secara harian.</p>
                </div>
            </div>
            <div style="padding: 1rem 1.5rem;">
                <canvas id="financeTrendChart" height="250"></canvas>
            </div>
        </div>

        <!-- ROW 3: BUKU BESAR TRANSACTION TABLE -->
        <div class="section-card">
            <div class="section-header" style="background:var(--surface); border-bottom:1px solid var(--border-subtle)">
                <div>
                    <h2 class="section-title">Buku Besar Transaksi</h2>
                    <p class="section-sub">Mencatat pendapatan sistem dan pengeluaran manual secara kronologis.</p>
                </div>
                <div style="display:flex; gap:10px;">
                    <?php if ($can_manage): ?>
                    <button class="btn btn-add" id="btn-tambah-transaksi">
                        <i data-lucide="plus"></i> Tambah Transaksi
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- FILTER BAR -->
            <div class="filter-bar" style="padding:1rem 1.5rem; background:var(--surface-hover); display:flex; gap:12px; align-items:center; flex-wrap:wrap; border-bottom: 1px solid var(--border-subtle);">
                <form method="get" id="filterForm" style="display:flex; gap:10px; flex:1;">
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
                    <select name="kategori" class="search-input" style="width:auto;" onchange="this.form.submit()">
                        <option value="">Semua Kategori</option>
                        <?php foreach($kategori_list as $k): ?>
                            <option value="<?= $k ?>" <?= $kategori == $k ? 'selected' : '' ?>><?= $k ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari keterangan..." class="search-input" style="flex:1">
                    <button type="submit" class="btn-search"><i data-lucide="search"></i></button>
                </form>
            </div>

            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:100px">Tanggal</th>
                            <th>Kategori</th>
                            <th>Keterangan</th>
                            <th>Nominal</th>
                            <th>Bukti</th>
                            <th>Oleh</th>
                            <?php if ($can_manage): ?><th>Aksi</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                        <?php while ($r = $result->fetch_assoc()): ?>
                        <tr>
                            <td style="font-size:0.75rem; color:#888"><?= date('d M Y', strtotime($r['tanggal'])) ?></td>
                            <td><span class="badge <?= $r['tipe'] === 'Masuk' ? 'badge-masuk' : 'badge-keluar' ?>"><?= $r['kategori'] ?></span></td>
                            <td style="font-size:0.85rem"><?= htmlspecialchars($r['keterangan']) ?></td>
                            <td style="font-weight:700; color: <?= $r['tipe'] === 'Masuk' ? '#0e7c6b' : '#ef4444' ?>">
                                <?= $r['tipe'] === 'Masuk' ? '+' : '-' ?> Rp <?= format_harga($r['nominal']) ?>
                            </td>
                            <td>
                                <?php if ($r['lampiran']): ?>
                                    <button class="action-btn" onclick="viewBukti('<?= htmlspecialchars($r['lampiran'], ENT_QUOTES, 'UTF-8') ?>')"><i data-lucide="image"></i></button>
                                <?php else: ?>
                                    <span style="color:#ccc; font-size:10px;">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.75rem; color:#8a8578">
                                <?php if ($r['source'] === 'system'): ?>
                                    <span style="color:var(--accent); font-weight:700;">SYSTEM</span>
                                <?php else: ?>
                                    <?= htmlspecialchars($r['username']) ?>
                                <?php endif; ?>
                            </td>
                            <?php if ($can_manage): ?>
                            <td class="td-actions">
                                <?php if ($r['source'] === 'manual'): ?>
                                <button class="action-btn detail btn-edit-fin" 
                                    data-id="<?= $r['id'] ?>"
                                    data-tipe="<?= $r['tipe'] ?>"
                                    data-kategori="<?= $r['kategori'] ?>"
                                    data-nominal="<?= (float)$r['nominal'] ?>"
                                    data-keterangan="<?= htmlspecialchars($r['keterangan']) ?>"
                                    data-tanggal="<?= $r['tanggal'] ?>"
                                    data-lampiran="<?= $r['lampiran'] ?>">
                                    <i data-lucide="edit-3"></i>
                                </button>
                                <button class="action-btn del btn-hapus-fin" data-id="<?= $r['id'] ?>"><i data-lucide="trash-2"></i></button>
                                <?php else: ?>
                                    <a href="reservasi.php?q=<?= explode(' ', $r['keterangan'])[1] ?>" class="action-btn detail" title="Lihat Detail Reservasi">
                                        <i data-lucide="external-link"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <tr><td colspan="7" class="td-empty">Tidak ada transaksi ditemukan untuk periode ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <span class="pagination-info"><?= $offset+1 ?>–<?= min($offset+$per_page, $total_rows) ?> dari <?= $total_rows ?> data</span>
                <div class="pagination-btns">
                    <a href="?page=<?= $page-1 ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&q=<?= urlencode($search) ?>&kategori=<?= urlencode($kategori) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">&larr; Sblmny</a>
                    <span class="page-current"><?= $page ?> / <?= $total_pages ?></span>
                    <a href="?page=<?= $page+1 ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&q=<?= urlencode($search) ?>&kategori=<?= urlencode($kategori) ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">Brktnya &rarr;</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- MODAL TAMBAH/EDIT -->
<div id="modalFinance" style="display:none; position:fixed; z-index:100; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div style="background:var(--surface); width:560px; max-width:95vw; border-radius:20px; padding:2rem 2.5rem; box-shadow: var(--shadow-lg); max-height:90vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; border-bottom:2px solid var(--border-subtle); padding-bottom:1rem;">
            <h2 id="modal-title" style="font-weight:800; font-size:1.2rem; color:var(--accent); margin:0;">Input Transaksi Baru</h2>
            <button onclick="closeModal()" style="border:0; background:none; cursor:pointer; padding:4px; color:var(--fg);"><i data-lucide="x"></i></button>
        </div>
        <form action="handlers/keuangan_handler.php" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="simpan">
            <input type="hidden" name="id" id="f-id" value="0">
            <input type="hidden" name="old_lampiran" id="f-old-lampiran">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                <div>
                    <label style="font-size:.8rem; font-weight:700; color:var(--fg); display:block; margin-bottom:6px;">Tipe</label>
                    <select name="tipe" id="f-tipe" required class="search-input" style="width:100%; font-size:.88rem;">
                        <option value="Keluar">Pengeluaran (-)</option>
                        <option value="Masuk">Pendapatan Lain (+)</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:.8rem; font-weight:700; color:var(--fg); display:block; margin-bottom:6px;">Kategori</label>
                    <select name="kategori" id="f-kategori" required class="search-input" style="width:100%;  font-size:.88rem;">
                        <?php foreach($kategori_list as $k): ?>
                            <option value="<?= $k ?>"><?= $k ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="margin-bottom:1rem;">
                <label style="font-size:.8rem; font-weight:700; color:var(--fg); display:block; margin-bottom:6px;">Nominal (Rp)</label>
                <input type="text" name="nominal" id="f-nominal" required placeholder="0" class="search-input" style="width:100%; font-weight:700; font-size:1.15rem; color:var(--accent); padding:12px; box-sizing:border-box;">
            </div>

            <div style="margin-bottom:1rem;">
                <label style="font-size:.8rem; font-weight:700; color:var(--fg); display:block; margin-bottom:6px;">Keterangan</label>
                <textarea name="keterangan" id="f-keterangan" required class="search-input" style="width:100%; height:80px; resize:none; padding:10px 12px; font-size:.88rem; box-sizing:border-box;" placeholder="Misal: Pembayaran gaji security bulan Jan..."></textarea>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.5rem;">
                <div>
                    <label style="font-size:.8rem; font-weight:700; color:var(--fg); display:block; margin-bottom:6px;">Tanggal</label>
                    <input type="date" name="tanggal" id="f-tanggal" required class="search-input" style="width:100%; padding:10px 12px; font-size:.88rem; box-sizing:border-box;" value="<?= date('Y-m-d') ?>">
                </div>
                <div>
                    <label style="font-size:.8rem; font-weight:700; color:var(--fg); display:block; margin-bottom:6px;">Bukti Nota (Opsional)</label>
                    <input type="file" name="lampiran" accept=".jpg,.jpeg,.png,.pdf" class="search-input" style="width:100%; padding:8px 10px; font-size:.78rem; box-sizing:border-box;">
                </div>
            </div>

            <button type="submit" class="btn" style="width:100%; padding:14px; font-size:.95rem; font-weight:700; letter-spacing:.5px;">SIMPAN TRANSAKSI</button>
        </form>
    </div>
</div>

<!-- Hidden form for deletion -->
<form id="form-hapus-fin" action="handlers/keuangan_handler.php" method="post" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="hapus">
    <input type="hidden" name="id" id="h-id">
</form>

<script>
    const modal = document.getElementById('modalFinance');
    function openModal() { modal.style.display = 'flex'; }
    function closeModal() { modal.style.display = 'none'; }

    document.getElementById('btn-tambah-transaksi')?.addEventListener('click', () => {
        document.getElementById('modal-title').innerText = 'Input Transaksi Baru';
        document.getElementById('f-id').value = 0;
        document.getElementById('f-tipe').value = 'Keluar';
        document.getElementById('f-kategori').value = 'Operasional';
        document.getElementById('f-nominal').value = '';
        document.getElementById('f-keterangan').value = '';
        document.getElementById('f-old-lampiran').value = '';
        openModal();
    });

    document.querySelectorAll('.btn-edit-fin').forEach(el => {
        el.addEventListener('click', () => {
            const d = el.dataset;
            document.getElementById('modal-title').innerText = 'Edit Transaksi';
            document.getElementById('f-id').value = d.id;
            document.getElementById('f-tipe').value = d.tipe;
            document.getElementById('f-kategori').value = d.kategori;
            document.getElementById('f-nominal').value = parseFloat(d.nominal).toLocaleString('id-ID');
            document.getElementById('f-keterangan').value = d.keterangan;
            document.getElementById('f-tanggal').value = d.tanggal;
            document.getElementById('f-old-lampiran').value = d.lampiran;
            openModal();
        });
    });

    document.querySelectorAll('.btn-hapus-fin').forEach(el => {
        el.addEventListener('click', () => {
            Swal.fire({
                title: 'Hapus Transaksi?',
                text: 'Data yang dihapus tidak dapat dikembalikan.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Ya, Hapus'
            }).then(r => {
                if(r.isConfirmed) {
                    document.getElementById('h-id').value = el.dataset.id;
                    document.getElementById('form-hapus-fin').submit();
                }
            });
        });
    });

    function viewBukti(file) {
        Swal.fire({
            title: 'Lampiran Transaksi',
            imageUrl: '../assets/bukti_keuangan/' + file,
            imageAlt: 'Bukti Nota',
            showConfirmButton: false,
            showCloseButton: true,
            width: 500
        });
    }

    // Auto-format nominal
    document.getElementById('f-nominal').addEventListener('input', function(e) {
        let v = this.value.replace(/\D/g, '');
        this.value = v ? parseInt(v).toLocaleString('id-ID') : '';
    });

    lucide.createIcons();
    
    // ─── CHART.JS TREND ───
    const CHART_LABELS = <?= json_encode($chart_labels) ?>;
    const CHART_INCOME = <?= json_encode($chart_income) ?>;
    const CHART_EXPENSE = <?= json_encode($chart_expense) ?>;
    
    const ctx = document.getElementById('financeTrendChart');
    if (ctx) {
        const getColors = (isDark) => ({
            grid: isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)',
            text: isDark ? '#d1d1d6' : '#8a8578'
        });
        let c = getColors(document.body.classList.contains('dark-theme'));

        const myChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: CHART_LABELS,
                datasets: [
                    {
                        label: 'Pendapatan (Rp)',
                        data: CHART_INCOME,
                        borderColor: '#0e7c6b',
                        backgroundColor: 'rgba(14, 124, 107, 0.1)',
                        fill: true, tension: 0.4
                    },
                    {
                        label: 'Pengeluaran (Rp)',
                        data: CHART_EXPENSE,
                        borderColor: '#ef4444',
                        backgroundColor: 'transparent',
                        borderDash: [5, 5], tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', align: 'end', labels: { boxWidth: 12, font: { size: 11 }, color: c.text } },
                    tooltip: { padding: 12, cornerRadius: 8 }
                },
                scales: {
                    y: { grid: { color: c.grid }, ticks: { color: c.text, callback: v => 'Rp' + (v/1000) + 'k' } },
                    x: { grid: { display: false }, ticks: { color: c.text } }
                }
            }
        });

        window.addEventListener('themeChanged', (e) => {
            const nc = getColors(e.detail.isDark);
            myChart.options.scales.y.grid.color = nc.grid;
            myChart.options.scales.y.ticks.color = nc.text;
            myChart.options.scales.x.ticks.color = nc.text;
            myChart.options.plugins.legend.labels.color = nc.text;
            myChart.update();
        });
    }

    // Notifications
    const params = new URLSearchParams(window.location.search);
    if(params.has('pesan')) Swal.fire({ icon:'success', title:'Success', text:params.get('pesan'), timer:3000 });
    if(params.has('error')) Swal.fire({ icon:'error', title:'Error', text:params.get('error') });
</script>

</body>
</html>
