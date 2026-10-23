<?php
require_once '../app/init.php';
require_admin($koneksi);
if (!has_canonical_permission('dashboard.view')) {
    header('Location: /ujikom1/login.php?error=Akses ditolak.');
    exit;
}

use App\Models\Dashboard;

// ─── PANGGIL DATA STATISTIK MELALUI MODEL (CLEAN CODE) ───
$stats = Dashboard::getStats();

$total       = $stats['total_visitors'];
$hari_ini    = $stats['visitors_today'];
$rsv_pending  = $stats['rsv_pending'];

$rev_bulan_ini = $stats['rev_month'];
$rev_ytd = $stats['rev_ytd'];
$avg_rating = $stats['avg_rating'];

$rev_growth = $stats['rev_growth_pct'];
$vis_growth = $stats['vis_growth_pct'];

$chart_labels       = $stats['chart_labels'];
$chart_visitors     = $stats['chart_visitors'];
$chart_revenue      = $stats['chart_revenue'];


// ─── AKTIVITAS TERBARU (FEED) ───
// Ambil 5 reservasi terbaru & 5 ulasan terbaru, lalu gabung & sort
$recent_rsv = $koneksi->query("SELECT 'reservasi' as type, kode_booking as title, nama as user, created_at, status FROM reservasi ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$recent_rev = $koneksi->query("SELECT 'review' as type, rating as title, nama as user, created_at, status FROM reviews ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$activities = array_merge($recent_rsv, $recent_rev);
usort($activities, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
$activities = array_slice($activities, 0, 8); // Ambil 8 teratas saja

// ─── AMBIL DAFTAR PENGUNJUNG BUAT TABEL ───
// fitur cari nih, cek kalo di bar pencarian ada yg ngetik trus di enter ga (variabel q)
$search      = isset($_GET['q']) ? trim($_GET['q']) : '';

// ─── PAGINATION ───
// jumlah baris data yang dimunculkan per halaman
$per_page    = 10;
// tangkep halaman berapa sekarang dari URL (?page=), default ke 1
$page        = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

if ($search) {
    $search_param = "%$search%";
    // hitung total data yang sesuai filter buat tau total halamannya
    $stmt_tot = $koneksi->prepare("SELECT COUNT(*) AS n FROM buku_tamu WHERE nama LIKE ? OR email LIKE ? OR no_hp LIKE ?");
    $stmt_tot->bind_param("sss", $search_param, $search_param, $search_param);
    $stmt_tot->execute();
    $total_q = $stmt_tot->get_result()->fetch_assoc()['n'];
} else {
    // Jika tidak ada pencarian, total data persis sama dengan total keseluruhan di atas
    $total_q = $total;
}

// hitung total halaman (hasil bagi dibulatkan ke atas)
$total_pages = max(1, ceil($total_q / $per_page));
// pastiin page ga melebihi batas
$page        = min($page, $total_pages);
// hitung offset: mulai dari baris ke berapa di SQL
$offset      = ($page - 1) * $per_page;

// panggil data dengan LIMIT dan OFFSET buat pagination pake prepared statement
if ($search) {
    $stmt = $koneksi->prepare("SELECT * FROM buku_tamu WHERE nama LIKE ? OR email LIKE ? OR no_hp LIKE ? ORDER BY tanggal DESC, id DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("sssii", $search_param, $search_param, $search_param, $per_page, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $stmt = $koneksi->prepare("SELECT * FROM buku_tamu ORDER BY tanggal DESC, id DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $per_page, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
}



// itung yg nongol di halaman ini
$total_show  = $result->num_rows;
// nomor urut awal di halaman ini (buat penomoran tabel yang lanjut)
$no_start    = $offset + 1;
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin — Cahaya Abadi</title>
    <link rel="stylesheet" href="/ujikom1/public/assets/dist/css/app.min.css">
            <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- ApexCharts CDN -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="admin-body">

<?php include 'includes/sidebar.php'; ?>

<!-- MAIN -->
<main class="admin-main">
    <?php 
    $page_title = 'Dashboard';
    include 'includes/header.php'; 
    ?>

    <div class="admin-content">

        <!-- TOP STAT CARDS (REVENUE FOCUSED) -->
        <div class="stats-grid">
            <div class="stat-card revenue">
                <div class="stat-top">
                    <div class="stat-icon"><i data-lucide="wallet"></i></div>
                    <div class="stat-badge <?= $rev_growth >= 0 ? 'up' : 'down' ?>">
                        <i data-lucide="<?= $rev_growth >= 0 ? 'trending-up' : 'trending-down' ?>"></i>
                        <?= number_format(abs($rev_growth), 1) ?>%
                    </div>
                </div>
                <div class="stat-bottom">
                    <div class="stat-value">Rp <?= format_harga($rev_bulan_ini) ?></div>
                    <div class="stat-label">Pendapatan Bulan Ini</div>
                </div>
            </div>
            <div class="stat-card green">
                <div class="stat-top">
                    <div class="stat-icon"><i data-lucide="bar-chart-3"></i></div>
                </div>
                <div class="stat-bottom">
                    <div class="stat-value">Rp <?= format_harga($rev_ytd) ?></div>
                    <div class="stat-label">Pendapatan Tahunan</div>
                </div>
            </div>
            <div class="stat-card amber">
                <div class="stat-top">
                    <div class="stat-icon"><i data-lucide="clock"></i></div>
                    <a href="reservasi.php?status=MENUNGGU_VERIFIKASI" class="stat-link-new"><i data-lucide="arrow-right"></i></a>
                </div>
                <div class="stat-bottom">
                    <div class="stat-value"><?= $rsv_pending ?></div>
                    <div class="stat-label">Menunggu Verifikasi</div>
                </div>
            </div>
            <div class="stat-card blue">
                <div class="stat-top">
                    <div class="stat-icon"><i data-lucide="users"></i></div>
                    <div class="stat-badge <?= $vis_growth >= 0 ? 'up' : 'down' ?>">
                        <?= number_format(abs($vis_growth), 1) ?>%
                    </div>
                </div>
                <div class="stat-bottom">
                    <div class="stat-value"><?= $hari_ini ?></div>
                    <div class="stat-label">Pengunjung Hari Ini</div>
                </div>
            </div>
        </div>

        <?php if (has_permission('finance', 'view')): 
            $pajak_rate = (float)get_setting('pajak_persen', 10) / 100;
            $est_pajak_bulan = $rev_bulan_ini - ($rev_bulan_ini / (1 + $pajak_rate));
        ?>
        <!-- FINANCE OVERVIEW (FOR ACCOUNTANT/ADMIN) -->
        <div class="section-card" style="margin-bottom:1.5rem; border-left:4px solid var(--accent);">
            <div style="padding:1.25rem 1.5rem; display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; align-items:center; gap:15px;">
                    <div style="background:var(--accent-glow); color:var(--accent); padding:10px; border-radius:12px;"><i data-lucide="calculator"></i></div>
                    <div>
                        <h3 style="margin:0; font-size:1rem; font-weight:800; color:var(--fg);">Ikhtisar Keuangan Cepat</h3>
                        <p style="margin:0; font-size:0.75rem; color:var(--fg-muted);">Estimasi kewajiban pajak bulan ini: <strong style="color:#eab308">Rp <?= format_harga($est_pajak_bulan) ?></strong></p>
                    </div>
                </div>
                <a href="audit_keuangan.php" class="btn btn-secondary" style="font-size:0.75rem; padding:8px 15px;">Buka Laporan Penuh &rarr;</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- MIDDLE SECTION: CHART + QUICK ACTIONS -->
        <div class="dashboard-mid-grid">
            <!-- Tren Chart (ApexCharts & Chart.js) -->
            <div class="section-card" style="display: flex; flex-direction: column;">
                <div class="section-header">
                    <div>
                        <h2 class="section-title">Analisis Pendapatan</h2>
                        <p class="section-sub">Pendapatan kotor (Gross) berdasarkan waktu</p>
                    </div>
                </div>
                <div style="padding: 1.25rem 1.5rem; flex: 1;">
                    <h3 style="font-size:0.8rem; font-weight:700; color:var(--fg-muted); margin-bottom:.5rem; text-transform:uppercase;">Pendapatan Bulanan (Chart.js)</h3>
                    <div style="margin-bottom: 2rem;">
                        <canvas id="monthlyChart" height="90"></canvas>
                    </div>

                    <h3 style="font-size:0.8rem; font-weight:700; color:var(--fg-muted); margin-bottom:.5rem; text-transform:uppercase;">Tren 14 Hari (ApexCharts)</h3>
                    <div id="revenueChart" style="min-height: 250px;"></div>
                </div>
            </div>

            <!-- Quick Action Hub -->
            <div class="section-card">
                <div class="section-header">
                    <div>
                        <h2 class="section-title">Aksi Cepat</h2>
                        <p class="section-sub">Tugas paling sering digunakan</p>
                    </div>
                </div>
                <div style="padding: 1.5rem;">
                    <div class="action-hub">
                        <button class="hub-card btn-tambah-pengunjung" style="cursor:pointer; width:100%;">
                            <div class="hub-icon"><i data-lucide="user-plus"></i></div>
                            <div class="hub-label">Input Tamu</div>
                        </button>
                        <a href="reservasi.php?status=MENUNGGU_VERIFIKASI" class="hub-card">
                            <div class="hub-icon"><i data-lucide="check-square"></i></div>
                            <div class="hub-label">Verifikasi</div>
                        </a>
                        <a href="berita.php" class="hub-card">
                            <div class="hub-icon"><i data-lucide="plus-circle"></i></div>
                            <div class="hub-label">Tulis Berita</div>
                        </a>
                        <a href="pengaturan.php" class="hub-card">
                            <div class="hub-icon"><i data-lucide="settings"></i></div>
                            <div class="hub-label">Setting</div>
                        </a>
                    </div>
                    
                    <div style="margin-top: 1.5rem; border-top: 1px solid var(--border-subtle); padding-top: 1.5rem;">
                        <h3 style="font-size:0.8rem; font-weight:700; color:var(--fg-muted); margin-bottom:1rem; text-transform:uppercase;">Distribusi Tiket</h3>
                        <div id="donutChart"></div>
                    </div>
                    <div style="margin-top: 1.5rem; border-top: 1px solid var(--border-subtle); padding-top: 1rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:.75rem; color:var(--fg-muted); font-weight:600;">KEPUASAN PELANGGAN</span>
                            <span style="color:#eab308; font-weight:800; display:flex; align-items:center; gap:4px">
                                <i data-lucide="star" style="width:14px;fill:#eab308"></i> 
                                <?= number_format($avg_rating, 1) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- BOTTOM SECTION: VISITOR TABLE + RECENT ACTIVITY -->
        <div class="dashboard-bottom-flex">
            <!-- Visitor Table -->
            <div class="section-card h-full" style="flex: 2;">
                <div class="section-header">
                    <div>
                        <h2 class="section-title">Log Buku Tamu</h2>
                        <p class="section-sub"><?= $total_q ?> total pengunjung terdaftar</p>
                    </div>
                    <div class="header-actions">
                        <form method="GET" action="" class="search-form">
                            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                                   placeholder="Cari..." class="search-input">
                            <button type="submit" class="btn-search"><i data-lucide="search"></i></button>
                        </form>
                    </div>
                </div>

                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:46px">No</th>
                                <th>Nama</th>
                                <th>No HP</th>
                                <th>Waktu</th>
                                <th style="width:100px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($result && mysqli_num_rows($result) > 0):
                            $no = $no_start;
                            while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td class="td-num"><?= $no++ ?></td>
                                <td class="td-name"><?= htmlspecialchars($row['nama']) ?></td>
                                <td style="font-size:.8rem; color:var(--fg-muted);"><?= htmlspecialchars($row['no_hp']) ?></td>
                                <td><span class="date-badge" style="font-size:.68rem"><?= date('d/m/y H:i', strtotime($row['tanggal'])) ?></span></td>
<td class="td-actions">
                            <button class="action-btn detail swal-detail"
                                data-id="<?= $row['id'] ?>"
                                data-nama="<?= htmlspecialchars($row['nama']) ?>"
                                data-hp="<?= htmlspecialchars($row['no_hp']) ?>"
                                data-email="<?= htmlspecialchars($row['email']) ?>"
                                data-tanggal="<?= date('d M Y H:i', strtotime($row['tanggal'])) ?>"
                                data-created="<?= date('d M Y H:i', strtotime($row['created_at'])) ?>">
                                <i data-lucide="eye"></i>
                            </button>
                            <button class="action-btn del btn-delete-visitor" data-id="<?= $row['id'] ?>" data-nama="<?= htmlspecialchars($row['nama']) ?>" title="Hapus Log">
                                <i data-lucide="trash-2" style="width:14px;height:14px;"></i>
                            </button>
                        </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5" style="text-align:center; padding:2rem; color:var(--fg-subtle);">Kosong.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <div class="pagination-btns">
                        <a href="<?= $page > 1 ? '?page='.($page-1).($search ? '&q='.urlencode($search) : '') : '#' ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" aria-label="Sebelumnya"><i data-lucide="chevron-left" style="width:16px;height:16px;"></i></a>
                        <?= \App\PaginationHelper::renderPages($page, $total_pages, ['q' => $search ?: null]) ?>
                        <a href="<?= $page < $total_pages ? '?page='.($page+1).($search ? '&q='.urlencode($search) : '') : '#' ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" aria-label="Berikutnya"><i data-lucide="chevron-right" style="width:16px;height:16px;"></i></a>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Activity Feed -->
            <div class="section-card h-full" style="flex: 1;">
                <div class="section-header">
                    <h2 class="section-title">Aktivitas Terbaru</h2>
                </div>
                <div style="padding: 0 1.5rem 1.5rem;">
                    <div class="activity-feed">
                        <?php foreach($activities as $act): 
                            $icon = 'info'; $colorClass = 'blue';
                            $titleText = '';
                            if($act['type'] === 'reservasi') {
                                $icon = 'calendar-check'; $colorClass = 'amber';
                                $titleText = "Booking <strong>{$act['title']}</strong>";
                            } else {
                                $icon = 'star'; $colorClass = 'green';
                                $titleText = "Memberi rating <strong>{$act['title']}/5</strong>";
                            }
                        ?>
                        <div class="activity-item">
                            <div class="activity-icon <?= $colorClass ?>"><i data-lucide="<?= $icon ?>" style="width:16px;height:16px;"></i></div>
                            <div class="activity-content">
                                <div class="activity-title"><?= $titleText ?></div>
                                <div class="activity-meta">oleh <?= htmlspecialchars($act['user']) ?></div>
                                <div class="activity-time"><?= date('H:i', strtotime($act['created_at'])) ?> &bull; <?= date('d M', strtotime($act['created_at'])) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
// APEXCHARTS CONFIGURATION
const CHART_LABELS = <?= json_encode($chart_labels) ?>;
const CHART_VISITORS = <?= json_encode($chart_visitors) ?>;
const CHART_REVENUE = <?= json_encode($chart_revenue) ?>;
const isThemeDark = () => document.body.classList.contains('dark-theme');
const AXIS_LABEL_COLOR = isThemeDark() ? '#d1d1d6' : '#8a8578';
const AXIS_GRID_COLOR = isThemeDark() ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)';

// 1. REVENUE TREND (Area Chart)
var revenueOptions = {
    theme: { mode: isThemeDark() ? 'dark' : 'light' },
    series: [{
        name: 'Pendapatan',
        data: CHART_REVENUE
    }, {
        name: 'Pengunjung',
        data: CHART_VISITORS
    }],
    chart: {
        type: 'area',
        height: 320,
        toolbar: { show: false },
        zoom: { enabled: false },
        fontFamily: "'Plus Jakarta Sans', sans-serif"
    },
    colors: ['#0e7c6b', '#2563eb'],
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 3 },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.45,
            opacityTo: 0.05,
            stops: [20, 100]
        }
    },
    xaxis: {
        categories: CHART_LABELS,
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { style: { colors: AXIS_LABEL_COLOR, fontSize: '12px' } }
    },
    yaxis: [{
        title: { text: "Pendapatan (Rp)", style: { color: "#0e7c6b" } },
        labels: { 
            style: { colors: AXIS_LABEL_COLOR },
            formatter: function(val) { return "Rp " + (val / 1000) + "k"; }
        }
    }, {
        opposite: true,
        title: { text: "Pengunjung", style: { color: "#2563eb" } },
        labels: { style: { colors: AXIS_LABEL_COLOR } }
    }],
    grid: { borderColor: isThemeDark() ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)', strokeDashArray: 4 },
    legend: { position: 'top', horizontalAlign: 'right' },
    tooltip: { theme: isThemeDark() ? 'dark' : 'light', x: { show: true } }
};

var revenueChart = new ApexCharts(document.querySelector("#revenueChart"), revenueOptions);
revenueChart.render();

// 2. DISTRIBUTION (Donut Chart)
var donutOptions = {
    series: [<?= (int)$stats['dist_weekday'] ?>, <?= (int)$stats['dist_weekend'] ?>],
    chart: { type: 'donut', height: 200, fontFamily: "'Plus Jakarta Sans', sans-serif", foreColor: isThemeDark() ? '#d1d1d6' : '#374151' },
    theme: { mode: isThemeDark() ? 'dark' : 'light' },
    labels: ['Weekday', 'Weekend'],
    colors: ['#0e7c6b', '#f59e0b'],
    legend: { position: 'bottom' },
    dataLabels: { enabled: false },
    plotOptions: {
        pie: {
            donut: {
                size: '75%',
                labels: {
                    show: true,
                    total: { show: true, label: 'Reservasi', formatter: () => '<?= (int)($stats['dist_weekday'] + $stats['dist_weekend']) ?>' }
                }
            }
        }
    }
};

var donutChart = new ApexCharts(document.querySelector("#donutChart"), donutOptions);
donutChart.render();

// 3. MONTHLY REVENUE (Chart.js)
const ctxMonthly = document.getElementById('monthlyChart').getContext('2d');
const monthlyChartData = <?= json_encode($stats['monthly_revenue_data']) ?>;
const monthlyChartLabels = <?= json_encode($stats['monthly_labels']) ?>;

// Gradient for the bar chart
let gradient = ctxMonthly.createLinearGradient(0, 0, 0, 400);
gradient.addColorStop(0, '#0e7c6b');
gradient.addColorStop(1, '#11bba2');

const monthlyChart = new Chart(ctxMonthly, {
    type: 'bar',
    data: {
        labels: monthlyChartLabels,
        datasets: [{
            label: 'Pendapatan (Rp)',
            data: monthlyChartData,
            backgroundColor: gradient,
            borderRadius: 6,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return ' Rp ' + context.parsed.y.toLocaleString('id-ID');
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: AXIS_GRID_COLOR },
                ticks: {
                    color: AXIS_LABEL_COLOR,
                    callback: function(value) {
                        return 'Rp ' + (value/1000) + 'k';
                    }
                }
            },
            x: {
                grid: { display: false },
                ticks: { color: AXIS_LABEL_COLOR }
            }
        }
    }
});

// THEME TOGGLE SUPPORT
const axisLabelDark = () => ({ labels: { style: { colors: isThemeDark() ? '#d1d1d6' : '#8a8578', fontSize: '12px' } } });
window.addEventListener('themeChanged', (e) => {
    const isDark = e.detail.isDark;
    const axis = isDark ? '#d1d1d6' : '#8a8578';
    revenueChart.updateOptions({
        theme: { mode: isDark ? 'dark' : 'light' },
        grid: { borderColor: isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)' },
        xaxis: { ...(axisLabelDark().labels ? {} : {}), categories: CHART_LABELS, axisBorder: { show: false }, axisTicks: { show: false }, labels: { style: { colors: axis, fontSize: '12px' } } },
        yaxis: [
            { title: { text: "Pendapatan (Rp)", style: { color: "#0e7c6b" } }, labels: { style: { colors: axis }, formatter: function(val) { return "Rp " + (val / 1000) + "k"; } } },
            { opposite: true, title: { text: "Pengunjung", style: { color: "#2563eb" } }, labels: { style: { colors: axis } } }
        ],
        tooltip: { theme: isDark ? 'dark' : 'light', x: { show: true } }
    });
    donutChart.updateOptions({
        theme: { mode: isDark ? 'dark' : 'light' },
        tooltip: { theme: isDark ? 'dark' : 'light' }
    });
    // Chart.js: warna sumbu & grid mengikuti tema
    if (monthlyChart) {
        monthlyChart.options.scales.y.grid.color = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)';
        monthlyChart.options.scales.y.ticks.color = axis;
        monthlyChart.options.scales.x.ticks.color = axis;
        monthlyChart.update();
    }
});

//  SWEETALERT2 — NOTIFICATIONS & ACTIONS 
const params = new URLSearchParams(window.location.search);
if (params.has('pesan')) {
    Swal.fire({ icon: 'success', title: 'Berhasil!', text: params.get('pesan'), confirmButtonColor: '#0e7c6b', timer: 3000 });
}
if (params.has('error')) {
    Swal.fire({ icon: 'error', title: 'Gagal!', text: params.get('error'), confirmButtonColor: '#0e7c6b' });
}

// LOGOUT
document.querySelectorAll('.swal-logout').forEach(el => {
    el.addEventListener('click', e => {
        e.preventDefault();
        Swal.fire({
            icon: 'question', title: 'Logout?', text: 'Sesi Anda akan diakhiri.',
            showCancelButton: true, confirmButtonColor: '#0e7c6b', confirmButtonText: 'Ya, Logout'
        }).then(res => { if (res.isConfirmed) window.location.href = el.getAttribute('href'); });
    });
});

// HAPUS
document.querySelectorAll('.swal-hapus').forEach(el => {
    el.addEventListener('click', e => {
        e.preventDefault();
        Swal.fire({
            icon: 'warning', title: 'Hapus data?', html: `Data <b>${el.dataset.nama}</b> akan dihapus permanen.`,
            showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Ya, Hapus'
        }).then(res => { if (res.isConfirmed) window.location.href = el.getAttribute('href'); });
    });
});

// HAPUS LOG BUKU TAMU (POST + CSRF)
document.querySelectorAll('.btn-delete-visitor').forEach(el => {
    el.addEventListener('click', () => {
        Swal.fire({
            icon: 'warning', title: 'Hapus log pengunjung?',
            html: `Log <b>${el.dataset.nama}</b> akan dihapus permanen.`,
            showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
        }).then(res => {
            if (res.isConfirmed) {
                const fd = new FormData();
                fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
                fd.append('id', el.dataset.id);
                fetch('handlers/hapus.php', { method: 'POST', body: fd })
                    .then(() => location.href = '/ujikom1/admin/?pesan=Log pengunjung dihapus');
            }
        });
    });
});

// DETAIL
document.querySelectorAll('.swal-detail').forEach(el => {
    el.addEventListener('click', () => {
        const d = el.dataset;
        Swal.fire({
            title: 'Detail Pengunjung',
            html: `<div style="text-align:left; font-size:.9rem; line-height:1.8; color:var(--fg);">
                <div style="display:flex; justify-content:space-between; padding:.5rem 0; border-bottom:1px solid var(--border-subtle);"><span style="color:var(--fg-muted)">Nama</span><strong>${d.nama}</strong></div>
                <div style="display:flex; justify-content:space-between; padding:.5rem 0; border-bottom:1px solid var(--border-subtle);"><span style="color:var(--fg-muted)">No HP</span><span>${d.hp}</span></div>
                <div style="display:flex; justify-content:space-between; padding:.5rem 0; border-bottom:1px solid var(--border-subtle);"><span style="color:var(--fg-muted)">Email</span><span>${d.email}</span></div>
                <div style="display:flex; justify-content:space-between; padding:.5rem 0;"><span style="color:var(--fg-muted)">Waktu</span><span>${d.tanggal}</span></div>
            </div>`,
            confirmButtonColor: '#0e7c6b'
        });
    });
});

// TAMBAH TAMU
document.querySelectorAll('.btn-tambah-pengunjung').forEach(el => {
    el.addEventListener('click', () => {
        Swal.fire({
            title: 'Input Pengunjung Baru',
            html: '<div style="text-align:left;">' +
                  '<div style="margin-bottom:10px"><label style="font-size:.8rem;">Nama</label><input id="s-nama" class="swal2-input" style="width:100%;margin:5px 0 0"></div>' +
                  '<div style="margin-bottom:10px"><label style="font-size:.8rem;">No HP</label><input id="s-hp" class="swal2-input" style="width:100%;margin:5px 0 0"></div>' +
                  '<div style="margin-bottom:10px"><label style="font-size:.8rem;">Email (- jika tidak ada)</label><input id="s-mail" class="swal2-input" style="width:100%;margin:5px 0 0"></div>' +
                  '<div><label style="font-size:.8rem;">Waktu</label><input id="s-tgl" type="datetime-local" class="swal2-input" style="width:100%;margin:5px 0 0" value="<?= date('Y-m-d\TH:i') ?>"></div>' +
                  '</div>',
            showCancelButton: true, confirmButtonColor: '#0e7c6b', confirmButtonText: 'Simpan',
            preConfirm: () => {
                const n = document.getElementById('s-nama').value, h = document.getElementById('s-hp').value, e = document.getElementById('s-mail').value, t = document.getElementById('s-tgl').value;
                if(!n||!h||!e||!t) return Swal.showValidationMessage('Semua wajib diisi');
                return {n,h,e,t};
            }
        }).then(r => {
            if(r.isConfirmed) {
                const fd = new FormData();
                fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
                fd.append('nama', r.value.n); fd.append('no_hp', r.value.h); fd.append('email', r.value.e); fd.append('tanggal', r.value.t); fd.append('website_url', '');
                fetch('handlers/simpan.php', { method:'POST', body:fd }).then(() => location.href='/ujikom1/admin/?pesan=Data ditambahkan');
            }
        });
    });
});

lucide.createIcons();
</script>

</body>
</html>
