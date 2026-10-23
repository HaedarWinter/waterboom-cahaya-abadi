<?php
/**
 * Halaman Admin: Kelola Reservasi
 */
require_once '../app/init.php';
use App\Services\BookingStateService;
check_session_timeout();
enforce_active_session($koneksi);

// Cek hak akses: reservation.view
if (!has_canonical_permission('reservation.view')) {
    header("Location: /ujikom1/admin/?error=Anda tidak memiliki hak akses untuk melihat data reservasi.");
    exit;
}

// Filter
$filter_status = $_GET['status'] ?? '';
$search = $_GET['q'] ?? '';

$where = "1=1";
$params = [];
$types = "";

if ($filter_status && isset(BookingStateService::labels()[$filter_status])) {
    $where .= " AND status = ?";
    $params[] = $filter_status;
    $types .= "s";
}
if ($search) {
    $where .= " AND (kode_booking LIKE ? OR nama LIKE ? OR no_hp LIKE ?)";
    $s = "%$search%";
    $params[] = $s; $params[] = $s; $params[] = $s;
    $types .= "sss";
}

// Pagination
$per_page = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// Total count
$count_stmt = $koneksi->prepare("SELECT COUNT(*) as total FROM reservasi WHERE $where");
if ($types) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total / $per_page);

// Data
$sql = "SELECT * FROM reservasi WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
$data_params = array_merge($params, [$per_page, $offset]);
$data_types = $types . "ii";
$stmt = $koneksi->prepare($sql);
if ($data_types) $stmt->bind_param($data_types, ...$data_params);
$stmt->execute();
$result = $stmt->get_result();

// Stats
$stats_pending = $koneksi->query("SELECT COUNT(*) as c FROM reservasi WHERE status = 'MENUNGGU_BAYAR'")->fetch_assoc()['c'];
$stats_konfirm = $koneksi->query("SELECT COUNT(*) as c FROM reservasi WHERE status = 'LUNAS'")->fetch_assoc()['c'];
$stats_total = $koneksi->query("SELECT COUNT(*) as c FROM reservasi")->fetch_assoc()['c'];
$stats_revenue = $koneksi->query("SELECT SUM(total_harga) as s FROM reservasi WHERE status IN ('LUNAS', 'SELESAI')")->fetch_assoc()['s'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Reservasi — Cahaya Abadi</title>
        <!-- Design System (Atomic CSS) -->
    <link rel="stylesheet" href="../public/assets/dist/css/app.min.css">
    <link rel="stylesheet" href="../public/assets/css/admin_style.css">
    <link rel="stylesheet" href="../public/assets/css/style.css">
    
    <!-- Legacy Admin Styles (fallback) -->
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="admin-body">

<?php include 'includes/sidebar.php'; ?>

<!-- MAIN -->
<main class="admin-main">
    <?php 
    $page_title = 'Kelola Reservasi';
    $breadcrumb = 'Reservasi';
    include 'includes/header.php'; 
    ?>

    <div class="admin-content">

        <!-- STAT CARDS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon gray"><i data-lucide="calendar-days"></i></div>
                <div>
                    <div class="stat-value"><?= $stats_total ?></div>
                    <div class="stat-label">Total Reservasi</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon amber"><i data-lucide="clock"></i></div>
                <div>
                    <div class="stat-value"><?= $stats_pending ?></div>
                    <div class="stat-label">Menunggu Bayar</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i data-lucide="check-circle"></i></div>
                <div>
                    <div class="stat-value"><?= $stats_konfirm ?></div>
                    <div class="stat-label">Lunas</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon teal"><i data-lucide="banknote"></i></div>
                <div>
                    <div class="stat-value" style="font-size:1.3rem;">Rp <?= format_harga($stats_revenue) ?></div>
                    <div class="stat-label">Total Pendapatan</div>
                </div>
            </div>
        </div>

        <!-- TABLE SECTION -->
        <div class="section-card">
            <div class="section-header">
                <div>
                    <h2 class="section-title" style="font-size:1.1rem;color:var(--accent);">Daftar Reservasi</h2>
                    <p class="section-sub">Kelola dan pantau data kunjungan harian.</p>
                </div>
                <div class="header-actions">
                    <!-- Filter Status -->
                    <div class="filter-group">
                        <a href="reservasi.php" class="filter-pill <?= !$filter_status ? 'active' : '' ?>">Semua</a>
                        <a href="reservasi.php?status=MENUNGGU_BAYAR" class="filter-pill <?= $filter_status === 'MENUNGGU_BAYAR' ? 'active' : '' ?>">Menunggu Bayar</a>
                        <a href="reservasi.php?status=MENUNGGU_VERIFIKASI" class="filter-pill <?= $filter_status === 'MENUNGGU_VERIFIKASI' ? 'active' : '' ?>">Menunggu Verifikasi</a>
                        <a href="reservasi.php?status=LUNAS" class="filter-pill <?= $filter_status === 'LUNAS' ? 'active' : '' ?>">Lunas</a>
                        <a href="reservasi.php?status=SELESAI" class="filter-pill <?= $filter_status === 'SELESAI' ? 'active' : '' ?>">Selesai</a>
                        <a href="reservasi.php?status=DIBATALKAN" class="filter-pill <?= $filter_status === 'DIBATALKAN' ? 'active' : '' ?>">Batal</a>
                        <a href="reservasi.php?status=DITOLAK" class="filter-pill <?= $filter_status === 'DITOLAK' ? 'active' : '' ?>">Ditolak</a>
                    </div>
                    <!-- Search -->
                    <form method="get" class="search-form">
                        <?php if ($filter_status): ?><input type="hidden" name="status" value="<?= $filter_status ?>"><?php endif; ?>
                        <input type="text" name="q" class="search-input" placeholder="Cari kode/nama..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn-search"><i data-lucide="search"></i></button>
                        <?php if ($search): ?><a href="reservasi.php<?= $filter_status ? '?status='.$filter_status : '' ?>" class="btn-clear"><i data-lucide="x"></i></a><?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Tanggal</th>
                            <th>Tiket</th>
                            <th>Orang</th>
                            <th>Total</th>
                            <th>Bayar</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                        <?php while ($r = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong style="color:var(--accent);letter-spacing:.5px;"><?= htmlspecialchars($r['kode_booking']) ?></strong></td>
                            <td class="td-name"><?= htmlspecialchars($r['nama']) ?></td>
                            <td><span class="date-badge"><?= date('d M Y', strtotime($r['tanggal_kunjungan'])) ?></span></td>
                            <td><?= $r['jenis_tiket'] ?></td>
                            <td><?= $r['jumlah_dewasa'] ?>D + <?= $r['jumlah_anak'] ?>A</td>
                            <td><strong>Rp <?= format_harga($r['total_harga']) ?></strong></td>
                            <td>
                                <div class="td-payment-flex">
                                <?php if ($r['metode_bayar'] === 'Transfer Bank'): ?>
                                    <?php if ($r['bukti_bayar']): ?>
                                        <img src="../assets/bukti_bayar/<?= htmlspecialchars($r['bukti_bayar']) ?>" alt="Bukti" class="payment-thumb" onclick="Swal.fire({imageUrl:'../assets/bukti_bayar/<?= htmlspecialchars($r['bukti_bayar']) ?>',imageAlt:'Bukti Bayar',showConfirmButton:false,showCloseButton:true,width:500})">
                                    <?php else: ?>
                                        <span style="font-size:.62rem;color:#b45309;background:rgba(245,158,11,.12);padding:2px 6px;border-radius:4px;border:1px solid rgba(245,158,11,.25);white-space:nowrap;">Belum Upload</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="font-size:.62rem;color:var(--muted);white-space:nowrap;">Di Lokasi</span>
                                <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= BookingStateService::badgeClass($r['status']) ?>"><?= e(BookingStateService::label($r['status'])) ?></span>
                            </td>
                            <td class="td-actions">
                                <div class="actions-wrapper">
                                    <button class="action-btn detail btn-detail-rsv" data-id="<?= $r['id'] ?>"
                                        data-kode="<?= htmlspecialchars($r['kode_booking']) ?>"
                                        data-nama="<?= htmlspecialchars($r['nama']) ?>"
                                        data-hp="<?= htmlspecialchars($r['no_hp']) ?>"
                                        data-email="<?= htmlspecialchars($r['email']) ?>"
                                        data-tanggal="<?= date('d M Y', strtotime($r['tanggal_kunjungan'])) ?>"
                                        data-jenis="<?= $r['jenis_tiket'] ?>"
                                        data-dewasa="<?= $r['jumlah_dewasa'] ?>"
                                        data-anak="<?= $r['jumlah_anak'] ?>"
                                        data-total="Rp <?= format_harga($r['total_harga']) ?>"
                                        data-status="<?= $r['status'] ?>"
                                        data-status-label="<?= e(BookingStateService::label($r['status'])) ?>"
                                        data-metode="<?= htmlspecialchars($r['metode_bayar'] ?? 'Bayar di Lokasi') ?>"
                                        data-catatan="<?= htmlspecialchars($r['catatan'] ?? '-') ?>"
                                        data-bukti="<?= $r['bukti_bayar'] ? '../assets/bukti_bayar/'.$r['bukti_bayar'] : '' ?>"
                                        data-alasan="<?= htmlspecialchars($r['catatan_tolak'] ?? '') ?>"
                                        data-created="<?= date('d M Y, H:i', strtotime($r['created_at'])) ?>">
                                        <i data-lucide="eye" style="width:14px;height:14px;"></i>
                                    </button>
                                    
                                    <?php if ($r['status'] === BookingStateService::MENUNGGU_VERIFIKASI && $r['metode_bayar'] === 'Transfer Bank' && $r['bukti_bayar']): ?>
                                    <button class="action-btn btn-icon-square check btn-verifikasi" data-id="<?= $r['id'] ?>" data-kode="<?= $r['kode_booking'] ?>" title="Verifikasi Pembayaran">
                                        <i data-lucide="check-circle" style="width:14px;height:14px;"></i>
                                    </button>
                                    <button class="action-btn btn-icon-square x btn-tolak-bayar" data-id="<?= $r['id'] ?>" data-kode="<?= $r['kode_booking'] ?>" title="Tolak Pembayaran">
                                        <i data-lucide="slash" style="width:14px;height:14px;"></i>
                                    </button>
                                    <?php endif; ?>

                                     <?php if ($r['status'] === 'LUNAS'): ?>
                                     <button class="action-btn btn-icon-square x btn-refund-ajukan" data-id="<?= $r['id'] ?>" data-kode="<?= $r['kode_booking'] ?>" title="Ajukan Refund">
                                         <i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i>
                                     </button>
                                     <?php elseif ($r['status'] === 'REFUND_DIAJUKAN'): ?>
                                     <button class="action-btn btn-icon-square check btn-refund-proses" data-id="<?= $r['id'] ?>" data-kode="<?= $r['kode_booking'] ?>" title="Proses Refund">
                                         <i data-lucide="wallet-cards" style="width:14px;height:14px;"></i>
                                     </button>
                                     <?php endif; ?>

                                     <?php if (in_array($r['status'], ['MENUNGGU_BAYAR', 'MENUNGGU_VERIFIKASI', 'DITOLAK'], true)): ?>
                                    <button class="action-btn del btn-hapus-rsv" data-id="<?= $r['id'] ?>" data-kode="<?= $r['kode_booking'] ?>" data-status="<?= $r['status'] ?>" title="Batalkan Reservasi">
                                        <i data-lucide="x-circle" style="width:14px;height:14px;"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <tr><td colspan="9" class="td-empty">Tidak ada data reservasi.</td></tr>
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
<form id="form-hapus" action="../../app/Http/Controllers/Admin/$1" method="post" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="status" value="DIBATALKAN">
    <input type="hidden" name="id" id="hapus-id">
</form>
<form id="form-verifikasi" action="../../app/Http/Controllers/Admin/$1" method="post" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="verifikasi_bayar">
    <input type="hidden" name="id" id="verifikasi-id">
</form>
<form id="form-tolak" action="../../app/Http/Controllers/Admin/$1" method="post" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="tolak_bayar">
    <input type="hidden" name="id" id="tolak-id">
    <input type="hidden" name="alasan" id="tolak-alasan">
</form>

<!-- Refund forms -->
<form id="form-refund-ajukan" action="../../app/Http/Controllers/Admin/$1" method="post" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="status" value="REFUND_DIAJUKAN">
    <input type="hidden" name="id" id="refund-ajukan-id">
    <input type="hidden" name="alasan" id="refund-ajukan-alasan">
</form>
<form id="form-refund-proses" action="../../app/Http/Controllers/Admin/$1" method="post" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="status" value="DIREFUND">
    <input type="hidden" name="id" id="refund-proses-id">
    <input type="hidden" name="alasan" id="refund-proses-alasan">
</form>

<script>
// Notif
const params = new URLSearchParams(window.location.search);
if (params.has('pesan')) { Swal.fire({ icon:'success',title:'Berhasil!',text:params.get('pesan'),confirmButtonColor:'#0e7c6b',timer:3500,timerProgressBar:true }).then(()=>{const u=new URL(window.location.href);u.searchParams.delete('pesan');window.history.replaceState({},'',u);}); }
if (params.has('error')) { Swal.fire({ icon:'error',title:'Gagal!',text:params.get('error'),confirmButtonColor:'#0e7c6b' }).then(()=>{const u=new URL(window.location.href);u.searchParams.delete('error');window.history.replaceState({},'',u);}); }

// Detail
document.querySelectorAll('.btn-detail-rsv').forEach(el => {
    el.addEventListener('click', function() {
        const d = this.dataset;
        Swal.fire({
            title: '<div style="text-align:left;font-size:1.1rem;font-weight:800;color:var(--accent);padding-bottom:.5rem;border-bottom:2px solid var(--accent);display:inline-block;">Detail Reservasi</div>',
            width: d.bukti ? 800 : 450,
            confirmButtonColor: '#0e7c6b',
            confirmButtonText: 'Tutup',
            html: `
            <div class="modal-detail-wrapper">
                <!-- Kolom Kiri: Informasi -->
                <div class="modal-column">
                    <div style="margin-bottom:1.5rem;">
                        <span style="font-size:1.5rem;font-weight:900;color:var(--accent);letter-spacing:1px;">${d.kode}</span>
                    </div>
                    <div class="modal-info-list" style="margin-bottom:1rem;">
                        <div class="modal-info-item"><span class="modal-info-label">Nama Pengunjung</span><span class="modal-info-value">${d.nama}</span></div>
                        <div class="modal-info-item"><span class="modal-info-label">No. Telepon</span><span class="modal-info-value">${d.hp}</span></div>
                        <div class="modal-info-item"><span class="modal-info-label">Email</span><span class="modal-info-value">${d.email}</span></div>
                        <div class="modal-info-item"><span class="modal-info-label">Tanggal Kunjungan</span><span class="modal-info-value">${d.tanggal}</span></div>
                        <div class="modal-info-item"><span class="modal-info-label">Jenis Tiket</span><span class="modal-info-value">${d.jenis}</span></div>
                        <div class="modal-info-item"><span class="modal-info-label">Dewasa / Anak</span><span class="modal-info-value">${d.dewasa} / ${d.anak}</span></div>
                        <div class="modal-info-item"><span class="modal-info-label">Metode Pembayaran</span><span class="modal-info-value">${d.metode}</span></div>
                        <div class="modal-info-item"><span class="modal-info-label">Status</span><span style="font-size:0.75rem;padding:2px 10px;border-radius:6px;background:var(--surface-hover);color:var(--fg);font-weight:700;">${d.statusLabel}</span></div>
                    </div>
                    <div style="padding:1rem;background:var(--surface-hover);border-radius:12px;border:1px solid var(--border-subtle);">
                       <span style="font-size:.78rem;color:var(--fg-muted);display:block;margin-bottom:4px;font-weight:600;">Catatan Pengunjung:</span>
                       <p style="font-size:.85rem;color:var(--fg);margin:0;font-style:italic;">"${d.catatan}"</p>
                    </div>
                    ${d.alasan ? `
                    <div style="margin-top:1rem;padding:1rem;background:rgba(225,29,72,.08);border-radius:12px;border:1px solid rgba(225,29,72,.25);color:#e11d48;">
                        <strong style="font-size:.78rem;display:block;margin-bottom:4px;">Alasan Penolakan:</strong>
                        <p style="font-size:.82rem;margin:0;">${d.alasan}</p>
                    </div>` : ''}
                    <p style="font-size:.7rem;color:var(--fg-subtle);margin-top:1.5rem;text-align:left;">Dipesan pada: ${d.created} WIB</p>
                </div>

                <!-- Kolom Kanan: Bukti (Jika Ada) -->
                ${d.bukti ? `
                <div class="modal-column">
                    <div class="modal-proof-container">
                        <span style="font-size:.85rem;font-weight:700;color:var(--fg-muted);margin-bottom:12px;display:block;text-align:center;">Bukti Pembayaran</span>
                        <img src="${d.bukti}" class="modal-proof-img" title="Klik untuk memperbesar" onclick="window.open('${d.bukti}')">
                        <p style="font-size:.65rem;color:var(--fg-subtle);margin-top:10px;text-align:center;">Silakan periksa keaslian bukti transfer di m-banking Anda.</p>
                    </div>
                </div>` : ''}
            </div>
            `
        });
    });
});

function viewBukti(url) {
    Swal.fire({
        title: 'Bukti Pembayaran',
        imageUrl: url,
        imageAlt: 'Bukti Bayar',
        showConfirmButton: false,
        showCloseButton: true,
        width: 500,
        customClass: { popup: 'rounded-16' }
    });
}

// Batalkan reservasi
document.querySelectorAll('.btn-hapus-rsv').forEach(el => {
    el.addEventListener('click', function() {
        const id = this.dataset.id;
        const kode = this.dataset.kode;
        Swal.fire({
            icon: 'warning', title: 'Batalkan Reservasi?',
            html: 'Reservasi <strong>' + kode + '</strong> akan dibatalkan dan kuotanya dilepas.',
            showCancelButton: true, confirmButtonColor: '#c0392b', cancelButtonColor: '#52525b', confirmButtonText: 'Ya, Batalkan', cancelButtonText: 'Batal'
        }).then(function(result) {
            if (result.isConfirmed) {
                document.getElementById('hapus-id').value = id;
                document.getElementById('form-hapus').submit();
            }
        });
    });
});

// Verifikasi pembayaran
document.querySelectorAll('.btn-verifikasi').forEach(el => {
    el.addEventListener('click', function() {
        const id = this.dataset.id, kode = this.dataset.kode;
        Swal.fire({
            icon:'question', title:'Verifikasi Pembayaran?',
            html:'Pembayaran untuk <strong>' + kode + '</strong> akan diverifikasi dan QR Code tiket akan dibuat.',
            showCancelButton:true, confirmButtonColor:'#0e7c6b', cancelButtonColor:'#e4e4e7',
            confirmButtonText:'Ya, Verifikasi', cancelButtonText:'Batal'
        }).then(r => { if(r.isConfirmed) { document.getElementById('verifikasi-id').value=id; document.getElementById('form-verifikasi').submit(); } });
    });
});

// Tolak pembayaran
document.querySelectorAll('.btn-tolak-bayar').forEach(el => {
    el.addEventListener('click', function() {
        const id = this.dataset.id, kode = this.dataset.kode;
        Swal.fire({
            title: 'Tolak Pembayaran?',
            html: '<p style="font-size:.88rem;margin-bottom:1rem;">Berikan alasan penolakan untuk: <strong>' + kode + '</strong></p>',
            input: 'textarea', inputPlaceholder: 'Masukkan alasan penolakan (misal: Bukti transfer tidak valid/kabur)...',
            inputAttributes: { 'aria-label': 'Masukkan alasan penolakan' },
            showCancelButton: true, confirmButtonColor: '#e11d48', cancelButtonColor: '#52525b',
            confirmButtonText: 'Tolak Pembayaran', cancelButtonText: 'Batal',
            inputValidator: (value) => { if (!value) { return 'Alasan penolakan wajib diisi!' } }
        }).then(r => { 
            if(r.isConfirmed) { 
                document.getElementById('tolak-id').value = id; 
                document.getElementById('tolak-alasan').value = r.value;
                document.getElementById('form-tolak').submit(); 
            } 
        });
    });
});

// Refund Ajukan
document.querySelectorAll('.btn-refund-ajukan').forEach(el => {
    el.addEventListener('click', function() {
        const id = this.dataset.id, kode = this.dataset.kode;
        Swal.fire({
            title: 'Ajukan Refund?',
            html: '<p style="font-size:.88rem;margin-bottom:1rem;">Booking <strong>' + kode + '</strong> akan diajukan refund penuh.</p>',
            input: 'textarea',
            inputPlaceholder: 'Masukkan alasan refund...',
            showCancelButton: true,
            confirmButtonColor: '#eab308',
            cancelButtonColor: '#52525b',
            confirmButtonText: 'Ajukan Refund',
            cancelButtonText: 'Batal',
            inputValidator: (value) => { if (!value) { return 'Alasan refund wajib diisi!' } }
        }).then(r => {
            if(r.isConfirmed) {
                document.getElementById('refund-ajukan-id').value = id;
                document.getElementById('refund-ajukan-alasan').value = r.value;
                document.getElementById('form-refund-ajukan').submit();
            }
        });
    });
});

// Refund Proses
document.querySelectorAll('.btn-refund-proses').forEach(el => {
    el.addEventListener('click', function() {
        const id = this.dataset.id, kode = this.dataset.kode;
        Swal.fire({
            icon: 'warning',
            title: 'Proses Refund Penuh?',
            html: 'Dana akan dikembalikan ke pengunjung untuk <strong>' + kode + '</strong>. Tindakan ini tidak dapat dibatalkan.',
            showCancelButton: true,
            confirmButtonColor: '#0e7c6b',
            cancelButtonColor: '#52525b',
            confirmButtonText: 'Ya, Refund Penuh',
            cancelButtonText: 'Batal'
        }).then(r => {
            if(r.isConfirmed) {
                document.getElementById('refund-proses-id').value = id;
                document.getElementById('refund-proses-alasan').value = 'Refund diproses oleh admin';
                document.getElementById('form-refund-proses').submit();
            }
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
// ── NOTIFIKASI SWEETALERT ──
if (params.has('pesan')) {
    Swal.fire({
        icon: 'success', title: 'Berhasil!', text: params.get('pesan'),
        confirmButtonColor: '#0e7c6b', timer: 3500, timerProgressBar: true
    }).then(() => {
        const url = new URL(window.location.href);
        url.searchParams.delete('pesan');
        window.history.replaceState({}, '', url);
    });
}
if (params.has('error')) {
    Swal.fire({
        icon: 'error', title: 'Gagal!', text: params.get('error'),
        confirmButtonColor: '#0e7c6b'
    }).then(() => {
        const url = new URL(window.location.href);
        url.searchParams.delete('error');
        window.history.replaceState({}, '', url);
    });
}

lucide.createIcons();
</script>
</body>
</html>
