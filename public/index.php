<?php 
require_once '../app/init.php';

use App\Models\Gallery;
use App\Models\News;
use App\Models\Review;
use App\Models\Faq;
use App\Models\Setting;
use App\Models\Reservasi;

$page_title = "Beranda";
$current_page = 'index';

// Ambil data melalui Model (Clean & Scalable)
$galeri_result = Gallery::getActive();
$total_foto = ($galeri_result) ? $galeri_result->num_rows : 0;

$berita_result = News::getActive(3);

$reviews_result = Review::getApproved(6);
$avg_rating = Review::getStats();

$faq_result = Faq::getActive();

// Ambil Kuota untuk Widget FOMO
$hari_ini = date('Y-m-d');
$besok = date('Y-m-d', strtotime('+1 day'));
$kuota_hari_ini = Reservasi::cekKuota($hari_ini);
$kuota_besok = Reservasi::cekKuota($besok);



include '../resources/views/layouts/header.php';
include '../resources/views/layouts/navbar.php';
?>

<div class="hero" id="index.php">
    <div class="hero-inner">
        <div class="hero-content">
            <span class="hero-tagline">#1 Family Waterpark in Banjaran</span>
            <h1>Kesegaran Alam<br>untuk Keluarga</h1>
            <p>Rasakan sensasi berenang dengan air pegunungan yang jernih di Waterboom Cahaya Abadi. Destinasi wisata air paling seru untuk liburan akhir pekan Anda.</p>
            <div class="hero-links">
                <a href="reservasi.php" class="hero-link">
                    Reservasi Sekarang <i data-lucide="ticket"></i>
                </a>
                <a href="cek_booking.php" class="hero-link">
                    Cek Status Booking <i data-lucide="search"></i>
                </a>
                <a href="#info" class="hero-link">
                    Lihat Fasilitas <i data-lucide="chevron-down"></i>
                </a>
            </div>
        </div>
        
        <div class="hero-visual">
            <div class="floating-card">
                <div class="card-maps-wrapper">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3959.6172204879877!2d107.56295627563807!3d-7.054183969128669!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e68e63a678eb8eb%3A0x2061ed09c7e931d3!2sWaterboom%20Cahaya%20Abadi!5e0!3m2!1sid!2sid!4v1776410694392!5m2!1sid!2sid" width="275" height="200" style="border-radius:10px; border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
                <div class="card-content">
                    <span class="label">Yuk langsung saja cek lokasi</span>
                    <h3>Waterboom Cahaya Abadi</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Corner -->
    <div class="hero-stats-corner">
        <div class="hero-stat-item">
            <span class="value">4.1</span>
            <span class="label">Rating rata-rata dari pengunjung</span>
        </div>
        <div class="hero-stat-item">
            <span class="value">1.3K+</span>
            <span class="label">Ulasan positif di platform kami</span>
        </div>
        <div class="hero-stat-item">
            <span class="value">10+</span>
            <span class="label">Wahana air seru untuk anak & dewasa</span>
        </div>
    </div>

    <!-- Kuota Widget FOMO -->
    <div class="hero-kuota-widget reveal">
        <div class="kuota-header">
            <i data-lucide="flame" class="kuota-icon"></i> Ketersediaan Tiket 
        </div>
        <div class="kuota-body">
            <div class="kuota-item <?= $kuota_hari_ini['sisa'] <= 0 ? 'full' : '' ?>">
                <span class="tanggal">Hari Ini (<?= date('d M', strtotime($hari_ini)) ?>)</span>
                <span class="sisa"><?= $kuota_hari_ini['sisa'] <= 0 ? 'Habis!' : "{$kuota_hari_ini['sisa']} Slot" ?></span>
            </div>
            <div class="kuota-item <?= $kuota_besok['sisa'] <= 0 ? 'full' : '' ?>">
                <span class="tanggal">Besok (<?= date('d M', strtotime($besok)) ?>)</span>
                <span class="sisa"><?= $kuota_besok['sisa'] <= 0 ? 'Habis!' : "{$kuota_besok['sisa']} Slot" ?></span>
            </div>
        </div>
    </div>
</div>

<div class="main-content">

    <div class="card bento-info reveal" id="info">
        <div class="card-header">
            <i data-lucide="palmtree" class="card-icon"></i>
            <h2>Liburan Seru Bareng Keluarga</h2>
        </div>
        <p class="deskripsi">
            <?= nl2br(htmlspecialchars(Setting::get('deskripsi_website', 'Selamat datang di Waterboom Cahaya Abadi!'))) ?>
        </p>
        <div class="info-grid">
            <div class="info-item">
                <i data-lucide="ticket" class="info-item-icon"></i>
                <h4>Harga Tiket</h4>
                <p>Weekday: Rp <?= format_harga(Setting::get('harga_weekday', '20000')) ?><br>Weekend/Libur: Rp <?= format_harga(Setting::get('harga_weekend', '25000')) ?></p>
            </div>
            <div class="info-item">
                <i data-lucide="clock" class="info-item-icon"></i>
                <h4>Jam Operasional</h4>
                <p><?= htmlspecialchars(Setting::get('hari_operasional', 'Setiap hari')) ?><br><?= htmlspecialchars(Setting::get('jam_buka', '08.00')) ?> – <?= htmlspecialchars(Setting::get('jam_tutup', '17.00')) ?> WIB</p>
            </div>
            <div class="info-item">
                <i data-lucide="map-pin" class="info-item-icon"></i>
                <h4>Lokasi</h4>
                <p><?= nl2br(htmlspecialchars(Setting::get('alamat', 'Banjaran, Bandung'))) ?></p>
            </div>
            <div class="info-item">
                <i data-lucide="phone" class="info-item-icon"></i>
                <h4>Kontak</h4>
                <p><?= htmlspecialchars(Setting::get('no_telepon', '-')) ?><br><?= htmlspecialchars(Setting::get('instagram', '-')) ?></p>
            </div>
        </div>
    </div>

    <!-- FASILITAS -->
    <div class="card bento-fasilitas reveal">
        <div class="card-header">
            <i data-lucide="sparkles" class="card-icon"></i>
            <h2>Fasilitas & Wahana</h2>
        </div>
        
        <div class="fasilitas-flex">
            <div class="fas-group">
                <p class="fas-group-title"><i data-lucide="star"></i> Utama</p>
                <ul class="fasilitas-list">
                    <?php 
                    $utama = explode(',', Setting::get('fasilitas_utama', 'Kolam Renang, Waterboom'));
                    foreach($utama as $u): if(trim($u)): ?>
                        <li><i data-lucide="check-circle" class="fas-icon"></i> <?= htmlspecialchars(trim($u)) ?></li>
                    <?php endif; endforeach; ?>
                </ul>
            </div>
            <div class="fas-group">
                <p class="fas-group-title"><i data-lucide="smile"></i> Pendukung</p>
                <ul class="fasilitas-list">
                    <?php 
                    $pendukung = explode(',', Setting::get('fasilitas_pendukung', 'Saung, Kamar Ganti, Mushola'));
                    foreach($pendukung as $p): if(trim($p)): ?>
                        <li><i data-lucide="check-circle" class="fas-icon"></i> <?= htmlspecialchars(trim($p)) ?></li>
                    <?php endif; endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- GALLERY -->
    <div class="card bento-galeri reveal" style="background: var(--accent-light);">
        <div class="card-header" style="margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <i data-lucide="images" class="card-icon"></i>
                <h2>Galeri Keseruan</h2>
            </div>
            <!-- Navigasi Carousel -->
            <div class="carousel-nav">
                <button class="nav-btn prev" id="galeriPrev"><i data-lucide="chevron-left"></i></button>
                <button class="nav-btn next" id="galeriNext"><i data-lucide="chevron-right"></i></button>
            </div>
        </div>
        <p class="deskripsi" style="margin-bottom: 1rem;">Intip suasana Waterboom Cahaya Abadi yang bikin betah berlama-lama main air!</p>
        
        <div class="carousel-container" id="galeriTrack">
            <div class="carousel-track">
                <?php 
                if ($total_foto > 0): 
                    $galeri_result->data_seek(0);
                    while ($foto = $galeri_result->fetch_assoc()): ?>
                    <div class="galeri-item">
                        <img src="assets/galeri/<?= htmlspecialchars($foto['file_gambar']) ?>" alt="<?= htmlspecialchars($foto['judul']) ?>" onerror="this.src='assets/<?= htmlspecialchars($foto['file_gambar']) ?>'">
                        <div class="galeri-caption"><?= htmlspecialchars($foto['judul']) ?></div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color:var(--muted);font-size:.85rem;padding: 2rem;text-align: center;width: 100%;">Belum ada foto yang ditambahkan.</p>
                <?php endif; ?>
            </div>
        </div>
        <!-- Indikator Posisi Galeri -->
        <div class="carousel-info">
            <span id="galeriCounter">1 / <?= $total_foto ?></span>
        </div>
    </div>

    <!-- BUKU TAMU -->
    <div class="card bento-bukutamu reveal" id="bukutamu">
        <div class="card-header">
            <i data-lucide="book-open" class="card-icon"></i>
            <h2>Buku Tamu</h2>
        </div>
        <p class="deskripsi">
            Yuk, absen dulu! Silakan isi data di bawah ini sebagai jejak pertemanan kalau kamu udah pernah seru-seruan bareng di Waterboom Cahaya Abadi.
        </p>

        <form action="../app/Http/Controllers/Admin/simpan.php" method="post" id="formBukuTamu">
            <?= csrf_field() ?>
            <!-- Honeypot: field tersembunyi anti-bot -->
            <div style="position:absolute;left:-9999px;" aria-hidden="true">
                <input type="text" name="website_url" tabindex="-1" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="nama">Nama Lengkap</label>
                <input type="text" id="nama" name="nama" placeholder="Masukkan nama lengkap Anda" value="<?= htmlspecialchars($old['nama'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="no_hp">Nomor HP</label>
                <input type="tel" id="no_hp" name="no_hp" placeholder="Contoh: 081234567890" value="<?= htmlspecialchars($old['no_hp'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Alamat Email</label>
                <input type="text" id="email" name="email" placeholder="Contoh: nama@email.com" 
                       pattern="[a-zA-Z0-9][a-zA-Z0-9._%+-]*@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}|-"
                       title="Email harus dimulai dengan huruf/angka, atau gunakan '-' jika tidak memiliki email"
                       inputmode="email"
                       value="<?= htmlspecialchars($old['email'] ?? '') ?>" required>
                <small style="font-size: 11px; color:var(--muted); margin-top: 4px; display: block;">* Isi dengan tanda hubung (<b>-</b>) jika tidak memiliki email.</small>
            </div>
            <div class="form-group">
                <label for="tanggal">Tanggal Kunjungan / Observasi</label>
                <?php
                $hari_ini = date('Y-m-d\TH:i');
                ?>
                <input type="datetime-local" id="tanggal" name="tanggal"
                       min="<?= $hari_ini ?>" value="<?= htmlspecialchars($old['tanggal'] ?? '') ?>" required>
            </div>
            <button type="submit" class="btn">Kirim Data <i data-lucide="arrow-right"></i></button>
        </form>
    </div>

</div>

<!-- BERITA & EVENT (Full Width) -->
<?php if ($berita_result && $berita_result->num_rows > 0): ?>
<div class="section-fullwidth reveal" id="berita">
    <div class="section-fullwidth-inner">
        <div class="section-fw-header">
            <div>
                <span class="section-fw-tag"><i data-lucide="newspaper" style="width:14px;height:14px;"></i> Berita & Event</span>
                <h2 class="section-fw-title">Info Terbaru</h2>
                <p class="section-fw-desc">Update terkini seputar Waterboom Cahaya Abadi</p>
            </div>
        </div>
        <div class="berita-grid">
            <?php while ($b = $berita_result->fetch_assoc()): ?>
            <div class="berita-card">
                <?php if ($b['gambar']): ?>
                <div class="berita-img">
                    <img src="assets/berita/<?= htmlspecialchars($b['gambar']) ?>" alt="<?= htmlspecialchars($b['judul']) ?>">
                </div>
                <?php else: ?>
                <div class="berita-img berita-img-placeholder">
                    <i data-lucide="newspaper" style="width:32px;height:32px;color:var(--accent);opacity:.4;"></i>
                </div>
                <?php endif; ?>
                <div class="berita-body">
                    <span class="berita-date"><i data-lucide="calendar" style="width:12px;height:12px;"></i> <?= date('d M Y', strtotime($b['created_at'])) ?></span>
                    <h3 class="berita-title"><?= htmlspecialchars($b['judul']) ?></h3>
                    <p class="berita-excerpt"><?= htmlspecialchars(mb_strimwidth($b['isi'], 0, 120, '...')) ?></p>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- TESTIMONI / REVIEW (Full Width) -->
<div class="section-fullwidth reveal" id="testimoni">
    <div class="section-fullwidth-inner">
        <div class="section-fw-header">
            <div>
                <span class="section-fw-tag"><i data-lucide="star" style="width:14px;height:14px;"></i> Testimoni</span>
                <h2 class="section-fw-title">Apa Kata Mereka?</h2>
                <p class="section-fw-desc">Ulasan dari pengunjung yang sudah merasakan keseruan di sini</p>
            </div>
            <?php if ($avg_rating['total'] > 0): ?>
            <div class="rating-summary">
                <div class="rating-big"><?= $avg_rating['avg'] ?></div>
                <div>
                    <div class="rating-stars">★★★★★</div>
                    <div class="rating-count"><?= $avg_rating['total'] ?> ulasan</div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($reviews_result && $reviews_result->num_rows > 0): ?>
        <div class="review-grid">
            <?php while ($rv = $reviews_result->fetch_assoc()): ?>
            <div class="review-card">
                <div class="review-header">
                    <div class="review-avatar"><?= strtoupper(mb_substr($rv['nama'], 0, 1)) ?></div>
                    <div>
                        <div class="review-name"><?= htmlspecialchars($rv['nama']) ?></div>
                        <div class="review-stars"><?= str_repeat('★', $rv['rating']) ?><?= str_repeat('☆', 5 - $rv['rating']) ?></div>
                    </div>
                </div>
                <p class="review-text"><?= htmlspecialchars($rv['ulasan']) ?></p>
                <span class="review-date"><?= date('d M Y', strtotime($rv['created_at'])) ?></span>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <p style="color:var(--muted);font-size:.85rem;text-align:center;padding:1rem;">Belum ada ulasan. Jadilah yang pertama!</p>
        <?php endif; ?>

        <!-- Combined Feedback & FAQ Section -->
        <div class="feedback-faq-wrapper">
            <!-- FAQ SIDE -->
            <?php if ($faq_result && $faq_result->num_rows > 0): ?>
            <div id="faq">
                <div class="section-fw-header">
                    <div>
                        <span class="section-fw-tag"><i data-lucide="help-circle" style="width:14px;height:14px;"></i> FAQ</span>
                        <h2 class="section-fw-title">Pertanyaan Umum</h2>
                        <p class="section-fw-desc">Informasi yang sering ditanyakan pengunjung</p>
                    </div>
                </div>
                <div class="faq-list">
                    <?php while ($fq = $faq_result->fetch_assoc()): ?>
                    <div class="faq-item">
                        <button class="faq-question">
                            <span><?= htmlspecialchars($fq['pertanyaan']) ?></span>
                            <i data-lucide="chevron-down" class="faq-chevron"></i>
                        </button>
                        <div class="faq-answer">
                            <p><?= nl2br(htmlspecialchars($fq['jawaban'])) ?></p>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- FORM TULIS REVIEW -->
            <div class="review-form-card">
                <h3><i data-lucide="edit-3" style="width:18px;height:18px;"></i> Tulis Ulasan</h3>
                <form action="../app/Http/Controllers/Admin/review_handler.php" method="post" id="formReview">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="simpan">
                    <div style="position:absolute;left:-9999px;" aria-hidden="true">
                        <input type="text" name="website_url" tabindex="-1" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="review_nama">Nama Anda</label>
                        <input type="text" id="review_nama" name="nama" placeholder="Masukkan nama Anda" required>
                    </div>
                    <div class="form-group">
                        <label>Rating</label>
                        <div class="star-input" id="starInput">
                            <span class="star-btn" data-value="1">★</span>
                            <span class="star-btn" data-value="2">★</span>
                            <span class="star-btn" data-value="3">★</span>
                            <span class="star-btn" data-value="4">★</span>
                            <span class="star-btn" data-value="5">★</span>
                        </div>
                        <input type="hidden" name="rating" id="ratingValue" value="5">
                    </div>
                    <div class="form-group">
                        <label for="review_ulasan">Ulasan</label>
                        <textarea name="ulasan" id="review_ulasan" rows="3" placeholder="Ceritakan pengalaman Anda..." style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--radius);font-family:var(--font);font-size:.86rem;color:var(--fg);resize:vertical;outline:none;" required></textarea>
                    </div>
                    <button type="submit" class="btn">Kirim Ulasan <i data-lucide="send"></i></button>
                </form>
            </div>
        </div>
    </div>
</div>

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
                    <span class="footer-info-text"><?= htmlspecialchars(Setting::get('alamat', 'Banjaran, Bandung')) ?></span>
                </li>
                <li>
                    <i data-lucide="clock" class="footer-info-icon"></i>
                    <span class="footer-info-text">Senin - Minggu: <?= htmlspecialchars(Setting::get('jam_buka', '08.00')) ?> – <?= htmlspecialchars(Setting::get('jam_tutup', '17.00')) ?> WIB</span>
                </li>
                <li>
                    <i data-lucide="phone" class="footer-info-icon"></i>
                    <span class="footer-info-text"><?= htmlspecialchars(Setting::get('no_telepon', '0822-1800-0132')) ?></span>
                </li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; 2026 Waterboom Cahaya Abadi &mdash; Banjaran, Bandung. Semua hak dilindungi.
    </div>
</footer>

<script>
//  HAMBURGER MENU 
const hamburger = document.getElementById('hamburgerBtn');
const mobileNav = document.getElementById('mobileNav');

if (hamburger && mobileNav) {
    hamburger.addEventListener('click', function() {
        this.classList.toggle('open');
        mobileNav.classList.toggle('open');
    });

    mobileNav.querySelectorAll('a').forEach(function(a) {
        a.addEventListener('click', function() {
            hamburger.classList.remove('open');
            mobileNav.classList.remove('open');
        });
    });
}

//  SCROLL REVEAL ANIMATION 
const reveals = document.querySelectorAll('.reveal');
const revealObserver = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            revealObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.12 });

reveals.forEach(function(el) {
    revealObserver.observe(el);
});

//  SWEETALERT2 HANDLER — INDEX / BUKU TAMU 
const params = new URLSearchParams(window.location.search);
const pesan = params.get('pesan');
const error = params.get('error');

if (pesan || error) {
    const formTarget = document.getElementById('bukutamu');
    if (formTarget) {
        document.documentElement.style.scrollBehavior = 'auto';
        formTarget.scrollIntoView();
        document.documentElement.style.scrollBehavior = '';
    }

    Swal.fire({
        icon: pesan ? 'success' : 'error',
        title: pesan ? 'Data Terkirim!' : 'Oops!',
        text: pesan || error,
        confirmButtonColor: pesan ? '#0e7c6b' : '#c0392b',
        confirmButtonText: pesan ? 'Oke' : 'Perbaiki Data',
        timer: pesan ? 4000 : null,
        timerProgressBar: !!pesan
    }).then(() => {
        const url = new URL(window.location.href);
        url.searchParams.delete('pesan');
        url.searchParams.delete('error');
        window.history.replaceState({}, '', url);
    });
}

// Review alerts
const pesan_review = params.get('pesan_review');
const error_review = params.get('error_review');
if (pesan_review || error_review) {
    Swal.fire({
        icon: pesan_review ? 'success' : 'error',
        title: pesan_review ? 'Terima Kasih!' : 'Oops!',
        text: pesan_review || error_review,
        confirmButtonColor: '#0e7c6b',
        timer: pesan_review ? 4000 : null,
        timerProgressBar: !!pesan_review
    }).then(() => {
        const url = new URL(window.location.href);
        url.searchParams.delete('pesan_review');
        url.searchParams.delete('error_review');
        window.history.replaceState({}, '', url);
    });
}

//  GALLERY CAROUSEL LOGIC 
const track = document.getElementById('galeriTrack');
const nextBtn = document.getElementById('galeriNext');
const prevBtn = document.getElementById('galeriPrev');

if (track && nextBtn && prevBtn) {
    const currentIndicator = document.getElementById('galeriCurrent');

    const getScrollInfo = () => {
        const item = track.querySelector('.galeri-item');
        const gap = parseInt(window.getComputedStyle(track).columnGap) || 20;
        const itemWidth = item ? item.offsetWidth + gap : track.offsetWidth * 0.8;
        return { itemWidth, gap };
    };

    nextBtn.addEventListener('click', () => {
        const { itemWidth } = getScrollInfo();
        track.scrollBy({ left: itemWidth, behavior: 'smooth' });
    });

    prevBtn.addEventListener('click', () => {
        const { itemWidth } = getScrollInfo();
        track.scrollBy({ left: -itemWidth, behavior: 'smooth' });
    });

    const toggleButtons = () => {
        prevBtn.style.opacity = track.scrollLeft <= 10 ? '0.3' : '1';
        prevBtn.style.pointerEvents = track.scrollLeft <= 10 ? 'none' : 'auto';
        
        const isEnd = track.scrollLeft + track.offsetWidth >= track.scrollWidth - 15;
        nextBtn.style.opacity = isEnd ? '0.3' : '1';
        nextBtn.style.pointerEvents = isEnd ? 'none' : 'auto';

        const counterEl = document.getElementById('galeriCounter');
        if (counterEl) {
            const { itemWidth } = getScrollInfo();
            const totalItems = <?= $total_foto ?>;
            const itemsInView = Math.max(1, Math.round(track.offsetWidth / itemWidth));
            
            // Hitung index awal (paling kiri)
            let start = Math.round(track.scrollLeft / itemWidth) + 1;
            start = Math.max(1, Math.min(start, totalItems));
            
            // Hitung index akhir yang terlihat
            let end = start + itemsInView - 1;
            if (isEnd) end = totalItems;
            end = Math.max(start, Math.min(end, totalItems));
            
            // Tampilkan range jika lebih dari 1 item terlihat
            if (start === end || totalItems <= 1) {
                counterEl.innerText = `${start} / ${totalItems}`;
            } else {
                counterEl.innerText = `${start}-${end} / ${totalItems}`;
            }
        }
    };

    track.addEventListener('scroll', toggleButtons);
    window.addEventListener('resize', toggleButtons);
    window.addEventListener('load', toggleButtons);
    setTimeout(toggleButtons, 500); 
}

//  NAVBAR ACTIVE STATE & SMOOTH SCROLL 
const sections = document.querySelectorAll('.hero, #info, #bukutamu, #berita, #testimoni, #faq');
const navLinks = document.querySelectorAll('.nav-links a, .mobile-nav a');

window.addEventListener('scroll', () => {
    let current = 'index.php'; 
    
    sections.forEach(section => {
        const sectionTop = section.offsetTop;
        if (window.pageYOffset >= (sectionTop - 200)) {
            const id = section.getAttribute('id');
            if (id && id !== 'index.php') {
                current = '#' + id;
            } else {
                current = 'index.php';
            }
        }
    });

    navLinks.forEach(link => {
        link.classList.remove('active');
        const href = link.getAttribute('href');
        
        // Cek apakah href cocok dengan current (handle 'index.php#id' vs '#id')
        if (href === current || (current !== 'index.php' && href.endsWith(current))) {
            link.classList.add('active');
        }
    });
});

document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth' });
        }
    });
});

// STAR RATING INPUT
const starInput = document.getElementById('starInput');
if (starInput) {
    const stars = starInput.querySelectorAll('.star-btn');
    const ratingVal = document.getElementById('ratingValue');
    let currentRating = 5;

    function updateStars(rating) {
        stars.forEach((s, i) => {
            s.classList.toggle('active', i < rating);
        });
        ratingVal.value = rating;
        currentRating = rating;
    }

    stars.forEach(s => {
        s.addEventListener('click', () => updateStars(parseInt(s.dataset.value)));
        s.addEventListener('mouseenter', () => {
            const val = parseInt(s.dataset.value);
            stars.forEach((st, i) => st.classList.toggle('hover', i < val));
        });
        s.addEventListener('mouseleave', () => {
            stars.forEach(st => st.classList.remove('hover'));
        });
    });

    updateStars(5);
}

// FAQ ACCORDION
document.querySelectorAll('.faq-question').forEach(btn => {
    btn.addEventListener('click', function() {
        const item = this.parentElement;
        const isOpen = item.classList.contains('open');

        // Close all
        document.querySelectorAll('.faq-item.open').forEach(el => {
            el.classList.remove('open');
        });

        // Toggle current
        if (!isOpen) {
            item.classList.add('open');
        }
    });
});

// Initialize Lucide Icons
lucide.createIcons();
</script>

</body>
</html>
