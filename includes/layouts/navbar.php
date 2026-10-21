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
