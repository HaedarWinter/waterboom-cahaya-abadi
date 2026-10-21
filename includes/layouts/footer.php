<footer>
    <div class="footer-inner">
        <div>
            <div class="footer-brand"><i data-lucide="waves"></i> Cahaya Abadi</div>
            <p class="footer-desc">Destinasi wisata keluarga terbaik di kawasan Kiangroke, Banjaran. Nikmati kesegaran air pegunungan asli dengan fasilitas modern dan aman.</p>
        </div>
        <div>
            <div class="footer-title">Navigasi</div>
            <ul class="footer-links">
                <li><a href="index.php">Beranda</a></li>
                <li><a href="index.php#info">Info Wisata</a></li>
                <li><a href="reservasi.php">Reservasi</a></li>
                <li><a href="cek_booking.php">Cek Booking</a></li>
            </ul>
        </div>
        <div>
            <div class="footer-title">Kontak & Lokasi</div>
            <ul class="footer-links">
                <li>
                    <i data-lucide="map-pin" class="footer-info-icon"></i>
                    <span class="footer-info-text"><?= htmlspecialchars(get_setting('alamat', 'Banjaran, Bandung')) ?></span>
                </li>
                <li>
                    <i data-lucide="clock" class="footer-info-icon"></i>
                    <span class="footer-info-text">Senin - Minggu: <?= htmlspecialchars(get_setting('jam_buka', '08.00')) ?> – <?= htmlspecialchars(get_setting('jam_tutup', '17.00')) ?> WIB</span>
                </li>
                <li>
                    <i data-lucide="phone" class="footer-info-icon"></i>
                    <span class="footer-info-text"><?= htmlspecialchars(get_setting('no_telepon', '0822-1800-0132')) ?></span>
                </li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; 2026 Waterboom Cahaya Abadi &mdash; Banjaran, Bandung. Semua hak dilindungi.
    </div>
</footer>

<script>
    // General Scripts (Hamburger, Lucide)
    var hamburger = document.getElementById('hamburgerBtn');
    var mobileNav = document.getElementById('mobileNav');
    if (hamburger && mobileNav) {
        hamburger.addEventListener('click', function() {
            this.classList.toggle('open');
            mobileNav.classList.toggle('open');
        });
    }
    lucide.createIcons();
</script>
<?php if (isset($extra_footer)) echo $extra_footer; ?>
</body>
</html>
