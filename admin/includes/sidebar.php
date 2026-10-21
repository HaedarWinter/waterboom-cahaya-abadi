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
        const theme = localStorage.getItem('theme') || 'light';
        if (theme === 'dark') {
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
        <a href="/ujikom1/admin/" class="nav-item <?= nav_active('index.php', $current_file) ?>">
            <i data-lucide="layout-grid" class="nav-icon"></i>
            <span>Dashboard</span>
        </a>
        <?php if (has_permission('user', 'manage')): ?>
        <a href="/ujikom1/admin/kelola_admin.php" class="nav-item <?= nav_active('kelola_admin.php', $current_file) ?>">
            <i data-lucide="shield-check" class="nav-icon"></i>
            <span>Kelola Admin</span>
        </a>
        <?php endif; ?>

        <?php if (has_permission('gallery', 'view')): ?>
        <a href="/ujikom1/admin/galeri.php" class="nav-item <?= nav_active('galeri.php', $current_file) ?>">
            <i data-lucide="images" class="nav-icon"></i>
            <span>Kelola Galeri</span>
        </a>
        <?php endif; ?>

        <?php if (has_permission('reservasi', 'view')): ?>
        <a href="/ujikom1/admin/scanner.php" class="nav-item <?= nav_active('scanner.php', $current_file) ?>">
            <i data-lucide="scan-line" class="nav-icon"></i>
            <span>Scanner Tiket</span>
        </a>
        <a href="/ujikom1/admin/reservasi.php" class="nav-item <?= nav_active('reservasi.php', $current_file) ?>">
            <i data-lucide="calendar-check" class="nav-icon"></i>
            <span>Reservasi</span>
        </a>
        <?php endif; ?>

        <?php if (has_permission('finance', 'view')): ?>
        <a href="/ujikom1/admin/audit_keuangan.php" class="nav-item <?= nav_active('audit_keuangan.php', $current_file) ?>">
            <i data-lucide="wallet" class="nav-icon"></i>
            <span>Audit Keuangan</span>
        </a>
        <?php endif; ?>

        <?php if (has_permission('reviews', 'view')): ?>
        <a href="/ujikom1/admin/reviews.php" class="nav-item <?= nav_active('reviews.php', $current_file) ?>">
            <i data-lucide="star" class="nav-icon"></i>
            <span>Kelola Review</span>
        </a>
        <?php endif; ?>

        <?php if (has_permission('news', 'view')): ?>
        <a href="/ujikom1/admin/berita.php" class="nav-item <?= nav_active('berita.php', $current_file) ?>">
            <i data-lucide="newspaper" class="nav-icon"></i>
            <span>Kelola Berita</span>
        </a>
        <?php endif; ?>

        <?php if (has_permission('faq', 'view')): ?>
        <a href="/ujikom1/admin/faq.php" class="nav-item <?= nav_active('faq.php', $current_file) ?>">
            <i data-lucide="help-circle" class="nav-icon"></i>
            <span>Kelola FAQ</span>
        </a>
        <?php endif; ?>

        <?php if (has_permission('settings', 'manage')): ?>
        <a href="/ujikom1/admin/pengaturan.php" class="nav-item <?= nav_active('pengaturan.php', $current_file) ?>">
            <i data-lucide="settings" class="nav-icon"></i>
            <span>Pengaturan</span>
        </a>
        <?php endif; ?>

        <!-- LOG AKTIVITAS (KHUSUS SUPER ADMIN) -->
        <?php if ($_SESSION['role'] === 'Super Admin'): ?>
        <a href="/ujikom1/admin/logs.php" class="nav-item <?= nav_active('logs.php', $current_file) ?>">
            <i data-lucide="clipboard-list" class="nav-icon"></i>
            <span>Log Aktivitas</span>
        </a>
        <?php endif; ?>

        <a href="/ujikom1/" class="nav-item" target="_blank">
            <i data-lucide="external-link" class="nav-icon"></i>
            <span>Lihat Website</span>
        </a>
    </nav>
    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
        <div class="user-meta">
            <div class="user-name"><?= e($_SESSION['username']) ?></div>
            <div class="user-role"><?= $_SESSION['role'] ?></div>
        </div>
        <div style="display:flex; gap:5px;">
            <button id="themeToggle" class="logout-btn" title="Ganti Tema" style="cursor:pointer; border:none;">
                <i data-lucide="moon"></i>
            </button>
            <a href="logout.php" class="logout-btn swal-logout" title="Logout"><i data-lucide="log-out"></i></a>
        </div>
    </div>
</aside>

<!-- Sidebar Overlay (Mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

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

// Sync toggle icon on load
if (body.classList.contains('dark-theme')) {
    themeToggle.querySelector('i').setAttribute('data-lucide', 'sun');
}

themeToggle.addEventListener('click', () => {
    const isDark = body.classList.toggle('dark-theme');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    
    // Update Icon
    const icon = themeToggle.querySelector('i');
    icon.setAttribute('data-lucide', isDark ? 'sun' : 'moon');
    lucide.createIcons(); // Refresh Lucide

    // Dispatch event for other components (like Charts)
    window.dispatchEvent(new CustomEvent('themeChanged', { detail: { isDark } }));
    });
});
</script>
