<?php ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wisata Air Terjun Cipamingkis - Bandung</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="logo">Cipamingkis</a>
    <div class="nav-links">
        <a href="index.php" class="active">Beranda</a>
        <a href="#info">Info Wisata</a>
        <a href="#bukutamu">Buku Tamu</a>
        <a href="login.php">Login Admin</a>
    </div>
</nav>

<div class="hero">
    <h1>Air Terjun Cipamingkis</h1>
    <p>Wisata alam yang asri di Kabupaten Bandung, Jawa Barat. Nikmati keindahan air terjun setinggi 30 meter di tengah hutan hijau.</p>
    <div class="hero-badges">
        <span class="badge">Kab. Bandung, Jawa Barat</span>
        <span class="badge">Buka 08.00 – 17.00 WIB</span>
        <span class="badge">Tiket Rp 15.000</span>
    </div>
    <div class="hero-img-container">
        <img src="assets/hero.png" alt="Air Terjun Cipamingkis" class="hero-img">
    </div>
</div>

<div class="main-content">

    <div class="card" id="info">
        <h2>Informasi Wisata</h2>
        <p class="deskripsi">
            Air Terjun Cipamingkis terletak di Desa Cipamingkis, Kecamatan Pacet, Kabupaten Bandung.
            Tempat ini menawarkan suasana alam yang sejuk dengan pemandangan air terjun yang indah,
            cocok untuk liburan keluarga maupun wisata edukasi bersama rombongan sekolah.
        </p>
        <div class="info-grid">
            <div class="info-item">
                <div>
                    <h4>Harga Tiket</h4>
                    <p>Masuk: Rp 15.000/orang<br>Motor: Rp 5.000 | Mobil: Rp 10.000</p>
                </div>
            </div>
            <div class="info-item">
                <div>
                    <h4>Jam Operasional</h4>
                    <p>Setiap hari<br>08.00 – 17.00 WIB</p>
                </div>
            </div>
            <div class="info-item">
                <div>
                    <h4>Lokasi</h4>
                    <p>Desa Cipamingkis, Kec. Pacet<br>Kabupaten Bandung, Jawa Barat</p>
                </div>
            </div>
            <div class="info-item">
                <div>
                    <h4>Kontak</h4>
                    <p>Pengelola Wisata<br>0812-3456-7890</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Fasilitas Tersedia</h2>
        <ul class="fasilitas-list">
            <li>Area Parkir</li>
            <li>Musholla</li>
            <li>Warung Makan</li>
            <li>Toilet Umum</li>
            <li>Gazebo</li>
            <li>Spot Foto</li>
            <li>Kios Oleh-oleh</li>
        </ul>
    </div>

    <div class="card" id="bukutamu">
        <h2>Buku Tamu</h2>
        <p class="deskripsi">
            Silakan isi data di bawah ini sebelum atau sesudah berkunjung. Data Anda akan kami simpan sebagai tanda kehadiran.
        </p>

        <?php if (isset($_GET['pesan'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_GET['pesan']) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        <form action="simpan.php" method="post" id="formBukuTamu">
            <div class="form-group">
                <label for="nama">Nama Lengkap</label>
                <input type="text" id="nama" name="nama" placeholder="Masukkan nama lengkap Anda" required>
            </div>
            <div class="form-group">
                <label for="no_hp">Nomor HP</label>
                <input type="tel" id="no_hp" name="no_hp" placeholder="Contoh: 081234567890" required>
            </div>
            <div class="form-group">
                <label for="email">Alamat Email</label>
                <input type="email" id="email" name="email" placeholder="Contoh: nama@email.com" required>
            </div>
            <div class="form-group">
                <label for="tanggal">Tanggal Kunjungan / Observasi</label>
                <input type="date" id="tanggal" name="tanggal" required>
            </div>
            <button type="submit" class="btn">Kirim Data</button>
        </form>
    </div>

</div>

<footer>
    <p>&copy; 2025 Wisata Air Terjun Cipamingkis &mdash; Kabupaten Bandung, Jawa Barat</p>
</footer>

<script>
    document.getElementById('formBukuTamu').addEventListener('submit', function(e) {
        var hp = document.getElementById('no_hp').value.trim();
        if (hp.length < 10) {
            alert('Nomor HP minimal 10 digit!');
            e.preventDefault();
        }
    });
    document.querySelectorAll('a[href^="#"]').forEach(function(a) {
        a.addEventListener('click', function(e) {
            var t = document.querySelector(this.getAttribute('href'));
            if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth' }); }
        });
    });
</script>

</body>
</html>
