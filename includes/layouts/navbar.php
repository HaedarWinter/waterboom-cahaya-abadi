<nav class="navbar">
    <a href="index.php" class="logo">
        <i data-lucide="waves" class="logo-icon"></i> Cahaya Abadi
    </a>
    <div class="nav-links">
        <a href="index.php" class="<?= $current_page === 'index' ? 'active' : '' ?>">Beranda</a>
        <a href="index.php#info">Info Wisata</a>
        <a href="reservasi.php" class="<?= $current_page === 'reservasi' ? 'active' : '' ?>">Reservasi</a>
        <a href="cek_booking.php" class="<?= $current_page === 'cek_booking' ? 'active' : '' ?>">Cek Booking</a>
        <?php if (isset($_SESSION['login'])) : ?>
            <a href="admin/index.php">Dashboard</a>
        <?php else : ?>
            <a href="login.php" class="<?= $current_page === 'login' ? 'active' : '' ?>">Login Admin</a>
        <?php endif; ?>
        <button id="themeToggle" aria-label="Ganti tema terang/gelap" style="background:none;border:none;cursor:pointer;color:var(--accent);width:28px;height:28px;display:flex;align-items:center;justify-content:center;">
            <i data-lucide="moon" class="icon-theme-moon" style="width:18px;"></i>
            <i data-lucide="sun" class="icon-theme-sun" style="width:18px;"></i>
        </button>
    </div>
    <button class="hamburger" id="hamburgerBtn" aria-label="Menu"><span></span></button>
</nav>

<div class="mobile-nav" id="mobileNav">
    <a href="index.php" class="<?= $current_page === 'index' ? 'active' : '' ?>">Beranda</a>
    <a href="index.php#info">Info Wisata</a>
    <a href="reservasi.php" class="<?= $current_page === 'reservasi' ? 'active' : '' ?>">Reservasi</a>
    <a href="cek_booking.php" class="<?= $current_page === 'cek_booking' ? 'active' : '' ?>">Cek Booking</a>
    <?php if (isset($_SESSION['login'])) : ?>
        <a href="admin/index.php">Dashboard</a>
    <?php else : ?>
        <a href="login.php">Login Admin</a>
    <?php endif; ?>
</div>

<script>
    (function() {
        const btn = document.getElementById('themeToggle');
        if (!btn) return;
        btn.addEventListener('click', () => {
            try {
                const isDark = document.body.classList.toggle('dark-theme');
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
                window.dispatchEvent(new CustomEvent('themeChanged', { detail: { isDark } }));
            } catch (e) {}
        });
        window.addEventListener('themeChanged', function(e) {
            document.documentElement.style.colorScheme = e.detail.isDark ? 'dark' : 'light';
        });
    })();
</script>
