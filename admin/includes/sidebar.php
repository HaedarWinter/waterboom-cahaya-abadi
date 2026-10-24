<?php
/**
 * admin/includes/sidebar.php
 * Sidebar navigasi pusat untuk Admin Panel
 */
$current_file = basename($_SERVER['PHP_SELF']);

function nav_active($file, $current) {
    return ($file === $current) ? 'active' : '';
}
?>
<!-- Theme Init Script (Prevent Flash) -->
<script>
    (function() {
        const forced = new URLSearchParams(location.search).get('theme');
        const theme = (forced === 'dark' || forced === 'light') ? forced : (localStorage.getItem('theme') || 'light');
        if (theme === 'dark') {
            document.documentElement.style.colorScheme = 'dark';
            document.body.classList.add('dark-theme');
        }
    })();
</script>
<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div>
            <div class="brand-name">Cahaya Abadi</div>
            <div class="brand-sub">Admin Panel</div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Menu Utama</div>
        <a href="<?= e(base_url('admin/index.php')) ?>" class="nav-item <?= nav_active('index.php', $current_file) ?>">
            <i data-lucide="layout-grid" class="nav-icon"></i>
            <span>Dashboard</span>
        </a>
        <?php if (has_permission('user', 'manage')): ?>
        <a href="<?= e(base_url('admin/kelola_admin.php')) ?>" class="nav-item <?= nav_active('kelola_admin.php', $current_file) ?>">
            <i data-lucide="shield-check" class="nav-icon"></i>
            <span>Kelola Admin</span>
        </a>
        <?php endif; ?>

        <?php if (has_permission('gallery', 'view')): ?>
        <a href="<?= e(base_url('admin/galeri.php')) ?>" class="nav-item <?= nav_active('galeri.php', $current_file) ?>">
            <i data-lucide="images" class="nav-icon"></i>
            <span>Kelola Galeri</span>
        </a>
        <?php endif; ?>

        <?php if (has_permission('reservasi', 'view')): ?>
        <a href="<?= e(base_url('admin/scanner.php')) ?>" class="nav-item <?= nav_active('scanner.php', $current_file) ?>">
            <i data-lucide="scan-line" class="nav-icon"></i>
            <span>Scanner Tiket</span>
        </a>
        <a href="<?= e(base_url('admin/reservasi.php')) ?>" class="nav-item <?= nav_active('reservasi.php', $current_file) ?>">
            <i data-lucide="calendar-check" class="nav-icon"></i>
            <span>Reservasi</span>
        </a>
        <?php endif; ?>

        <?php if (has_permission('finance', 'view') || has_canonical_permission('ledger.view')): ?>
        <a href="<?= e(base_url('admin/audit_keuangan.php')) ?>" class="nav-item <?= nav_active('audit_keuangan.php', $current_file) ?>">
            <i data-lucide="wallet" class="nav-icon"></i>
            <span>Audit Keuangan</span>
        </a>
        <a href="<?= e(base_url('admin/buku_kas.php')) ?>" class="nav-item <?= nav_active('buku_kas.php', $current_file) ?>">
            <i data-lucide="book-open-check" class="nav-icon"></i>
            <span>Buku Kas</span>
        </a>
        <a href="<?= e(base_url('admin/reconciliation.php')) ?>" class="nav-item <?= nav_active('reconciliation.php', $current_file) ?>">
            <i data-lucide="calculator" class="nav-icon"></i>
            <span>Rekonsiliasi</span>
        </a>
        <?php endif; ?>

        <?php if (has_canonical_permission('ledger.entry_manual')): ?>
        <a href="<?= e(base_url('admin/expenses.php')) ?>" class="nav-item <?= nav_active('expenses.php', $current_file) ?>">
            <i data-lucide="credit-card" class="nav-icon"></i>
            <span>Pengeluaran</span>
        </a>
        <?php endif; ?>

        <?php if (has_canonical_permission('closing.perform')): ?>
        <a href="<?= e(base_url('admin/closings.php')) ?>" class="nav-item <?= nav_active('closings.php', $current_file) ?>">
            <i data-lucide="lock" class="nav-icon"></i>
            <span>Tutup Harian</span>
        </a>
        <?php endif; ?>

        <?php if (has_canonical_permission('report.finance.view')): ?>
        <a href="<?= e(base_url('admin/reports.php')) ?>" class="nav-item <?= nav_active('reports.php', $current_file) ?>">
            <i data-lucide="bar-chart-3" class="nav-icon"></i>
            <span>Laporan Keuangan</span>
        </a>
        <?php endif; ?>

        <?php if (has_canonical_permission('settings.manage')): ?>
        <a href="<?= e(base_url('admin/notifications.php')) ?>" class="nav-item <?= nav_active('notifications.php', $current_file) ?>">
            <i data-lucide="bell" class="nav-icon"></i>
            <span>Notifikasi</span>
        </a>
        <a href="<?= e(base_url('admin/templates.php')) ?>" class="nav-item <?= nav_active('templates.php', $current_file) ?>">
            <i data-lucide="message-square" class="nav-icon"></i>
            <span>Template WA</span>
        </a>
        <?php endif; ?>

        <?php if (has_permission('reviews', 'view')): ?>
        <a href="<?= e(base_url('admin/reviews.php')) ?>" class="nav-item <?= nav_active('reviews.php', $current_file) ?>">
            <i data-lucide="star" class="nav-icon"></i>
            <span>Kelola Review</span>
        </a>
        <?php endif; ?>

        <?php if (has_permission('news', 'view')): ?>
        <a href="<?= e(base_url('admin/berita.php')) ?>" class="nav-item <?= nav_active('berita.php', $current_file) ?>">
            <i data-lucide="newspaper" class="nav-icon"></i>
            <span>Kelola Berita</span>
        </a>
        <?php endif; ?>

        <?php if (has_permission('faq', 'view')): ?>
        <a href="<?= e(base_url('admin/faq.php')) ?>" class="nav-item <?= nav_active('faq.php', $current_file) ?>">
            <i data-lucide="help-circle" class="nav-icon"></i>
            <span>Kelola FAQ</span>
        </a>
        <?php endif; ?>

        <?php if (has_permission('settings', 'manage')): ?>
        <a href="<?= e(base_url('admin/pengaturan.php')) ?>" class="nav-item <?= nav_active('pengaturan.php', $current_file) ?>">
            <i data-lucide="settings" class="nav-icon"></i>
            <span>Pengaturan</span>
        </a>
        <?php endif; ?>

        <!-- LOG AKTIVITAS (KHUSUS SUPER ADMIN) -->
        <?php if ($_SESSION['role'] === 'Super Admin'): ?>
        <a href="<?= e(base_url('admin/logs.php')) ?>" class="nav-item <?= nav_active('logs.php', $current_file) ?>">
            <i data-lucide="clipboard-list" class="nav-icon"></i>
            <span>Log Aktivitas</span>
        </a>
        <?php endif; ?>

        <a href="<?= e(base_url()) ?>" class="nav-item" target="_blank">
            <i data-lucide="external-link" class="nav-icon"></i>
            <span>Lihat Website</span>
        </a>
    </nav>
    <div class="sidebar-user">
        <div class="user-avatar"><?= e(strtoupper(substr((string)($_SESSION['username'] ?? 'A'), 0, 1))) ?></div>
        <div class="user-meta">
            <div class="user-name"><?= e($_SESSION['username'] ?? 'Admin') ?></div>
            <div class="user-role"><?= e($_SESSION['role'] ?? 'Admin') ?></div>
        </div>
        <div class="sidebar-user__actions">
            <button id="themeToggle" class="logout-btn" type="button" title="Ganti Tema" aria-label="Ganti tema terang atau gelap">
                <i data-lucide="moon" class="icon-theme-moon"></i>
                <i data-lucide="sun" class="icon-theme-sun"></i>
            </button>
            <a href="logout.php?token=<?= e(csrf_token()) ?>" class="logout-btn swal-logout" title="Keluar" aria-label="Keluar"><i data-lucide="log-out" aria-hidden="true"></i></a>
        </div>
    </div>
</aside>

<!-- Sidebar Overlay (Mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<form id="logout-form" method="post" action="<?= e(base_url('admin/logout.php')) ?>" hidden>
    <?= csrf_field() ?>
</form>

<script>
// SEC-2: semua tautan logout menjadi POST ber-CSRF. Handler capture ini
// menggantikan handler lama per halaman tanpa perlu menyentuh semuanya.
document.addEventListener('click', function(e) {
    const el = e.target.closest('.swal-logout');
    if (!el) return;
    e.preventDefault();
    e.stopPropagation();
    const submit = () => document.getElementById('logout-form').submit();
    if (typeof Swal === 'undefined') { submit(); return; }
    Swal.fire({
        icon: 'question', title: 'Logout?', text: 'Sesi Anda akan diakhiri.',
        showCancelButton: true, confirmButtonColor: '#0e7c6b', confirmButtonText: 'Ya, Logout'
    }).then(function(res) { if (res.isConfirmed) submit(); });
}, true);
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Jalankan Lucide
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebarOverlay');
const togglers = document.querySelectorAll('.sidebar-toggle');

togglers.forEach(btn => {
    btn.addEventListener('click', (e) => {
        e.preventDefault();
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
    });
});

overlay.addEventListener('click', () => {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
});

/**
 * Theme Toggle logic
 */
const themeToggle = document.getElementById('themeToggle');
const body = document.body;

themeToggle.setAttribute('aria-pressed', body.classList.contains('dark-theme') ? 'true' : 'false');

themeToggle.addEventListener('click', () => {
    const isDark = body.classList.toggle('dark-theme');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');

    // Dispatch event for other components (like Charts)
    themeToggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
    window.dispatchEvent(new CustomEvent('themeChanged', { detail: { isDark } }));
});
});
</script>
