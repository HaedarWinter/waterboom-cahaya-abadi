-- bikin database dulu --
CREATE DATABASE IF NOT EXISTS db_ujikom;
USE db_ujikom;

-- 1. Tabel Buku Tamu --
CREATE TABLE IF NOT EXISTS buku_tamu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    no_hp VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    tanggal DATETIME NOT NULL DEFAULT (CURRENT_TIMESTAMP),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Tabel Admin --
CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Super Admin', 'Admin', 'Akuntan') DEFAULT 'Admin',
    status ENUM('Aktif', 'Nonaktif') DEFAULT 'Aktif',
    permissions TEXT, -- JSON format for granular access
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Data Admin Default — dihapus di PRD bagian 14 (mandatory password change). 
-- Seed awal dilakukan lewat scripts/reset_demo.php di mode demo atau via UI Super Admin.
-- Line rows dihapus untuk menghindari penggunaan kredensial default.

-- 3. Tabel Galeri --
CREATE TABLE IF NOT EXISTS galeri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(100) NOT NULL,
    deskripsi VARCHAR(255),
    file_gambar VARCHAR(255) NOT NULL,
    urutan INT DEFAULT 0,
    aktif ENUM('Ya','Tidak') DEFAULT 'Ya',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Tabel Settings (CMS) --
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    label VARCHAR(100),
    kategori VARCHAR(50) DEFAULT 'umum'
);

-- Data Pengaturan Website
INSERT INTO settings (setting_key, setting_value, label, kategori) VALUES
('harga_weekday', '20000', 'Harga Tiket Weekday (Rp)', 'tiket'),
('harga_weekend', '25000', 'Harga Tiket Weekend/Libur (Rp)', 'tiket'),
('kuota_default', '200', 'Kuota Pengunjung Default Per Hari', 'tiket'),
('rekening_bank', 'Bank BCA - 1234567890 a/n Waterboom Cahaya Abadi', 'Info Rekening Pembayaran', 'pembayaran'),
('jam_buka', '08.00', 'Jam Buka', 'operasional'),
('jam_tutup', '17.00', 'Jam Tutup', 'operasional'),
('hari_operasional', 'Setiap hari', 'Hari Operasional', 'operasional'),
('alamat', 'Jl. Pahlawan No.10, Kiangroke, Kec. Banjaran, Kab. Bandung', 'Alamat Lengkap', 'kontak'),
('no_telepon', '0822-1800-0132', 'Nomor Telepon', 'kontak'),
('instagram', '@wbcahayaabadi', 'Akun Instagram', 'kontak'),
('deskripsi_website', 'Lagi bingung cari tempat main air yang asyik buat weekend-an? Singgah aja ke Waterboom Cahaya Abadi (Villa Buaya) di Desa Kiangroke, Banjaran! Di sini airnya seger banget karena deket dari mata air pegunungan. Kolamnya luas, ada yang khusus untuk anak-anak lengkap dengan perosotan kecil, dan ada juga untuk orang dewasa. Plus, banyak spot saung buat ngariung nyantai bari botram bareng keluarga!', 'Deskripsi Website (Halaman Depan)', 'umum');

-- 5. Tabel Reservasi --
CREATE TABLE IF NOT EXISTS reservasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_booking VARCHAR(10) NOT NULL UNIQUE,
    nama VARCHAR(100) NOT NULL,
    no_hp VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    tanggal_kunjungan DATE NOT NULL,
    jenis_tiket ENUM('Weekday','Weekend') NOT NULL,
    jumlah_dewasa INT DEFAULT 1,
    jumlah_anak INT DEFAULT 0,
    total_harga INT NOT NULL,
    metode_bayar ENUM('Bayar di Lokasi','Transfer Bank') DEFAULT 'Bayar di Lokasi',
    bukti_bayar VARCHAR(255) DEFAULT NULL,
    status ENUM('Pending','Dikonfirmasi','Selesai','Dibatalkan','Ditolak') DEFAULT 'Pending',
    qr_token VARCHAR(100) DEFAULT NULL,
    catatan TEXT,
    catatan_tolak TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. Tabel Kuota Harian (Custom per Tanggal) --
CREATE TABLE IF NOT EXISTS kuota_harian (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL UNIQUE,
    kuota_max INT NOT NULL DEFAULT 200,
    terisi INT DEFAULT 0,
    keterangan VARCHAR(255)
);

-- 7. Tabel Reviews --
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    ulasan TEXT NOT NULL,
    status ENUM('Pending','Approved','Ditolak') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 8. Tabel Berita & Event --
CREATE TABLE IF NOT EXISTS berita (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(200) NOT NULL,
    isi TEXT NOT NULL,
    gambar VARCHAR(255),
    aktif ENUM('Ya','Tidak') DEFAULT 'Ya',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 9. Tabel FAQ --
CREATE TABLE IF NOT EXISTS faq (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pertanyaan TEXT NOT NULL,
    jawaban TEXT NOT NULL,
    urutan INT DEFAULT 0,
    aktif ENUM('Ya','Tidak') DEFAULT 'Ya'
);