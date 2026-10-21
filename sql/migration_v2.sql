-- ══════════════════════════════════════════════════════════════
-- MIGRATION V2: Fitur Baru — Bukti Bayar, QR Code, Kuota,
--                Review, Berita, FAQ
-- ══════════════════════════════════════════════════════════════
USE db_ujikom;

-- ─── 1. ALTER tabel reservasi: tambah kolom pembayaran ───
ALTER TABLE reservasi 
    ADD COLUMN metode_bayar ENUM('Bayar di Lokasi','Transfer Bank') DEFAULT 'Bayar di Lokasi' AFTER catatan,
    ADD COLUMN bukti_bayar VARCHAR(255) DEFAULT NULL AFTER metode_bayar,
    ADD COLUMN qr_token VARCHAR(64) DEFAULT NULL AFTER bukti_bayar;

-- ─── 2. Tabel kuota harian ───
CREATE TABLE IF NOT EXISTS kuota_harian (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL UNIQUE,
    kuota_max INT DEFAULT 200,
    terisi INT DEFAULT 0
);

-- ─── 3. Tabel reviews ───
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    rating TINYINT NOT NULL,
    ulasan TEXT,
    status ENUM('Pending','Approved','Ditolak') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── 4. Tabel berita ───
CREATE TABLE IF NOT EXISTS berita (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(200) NOT NULL,
    isi TEXT NOT NULL,
    gambar VARCHAR(255) DEFAULT NULL,
    aktif ENUM('Ya','Tidak') DEFAULT 'Ya',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── 5. Tabel FAQ ───
CREATE TABLE IF NOT EXISTS faq (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pertanyaan VARCHAR(255) NOT NULL,
    jawaban TEXT NOT NULL,
    urutan INT DEFAULT 0,
    aktif ENUM('Ya','Tidak') DEFAULT 'Ya',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── 6. Setting baru: kuota + rekening ───
INSERT INTO settings (setting_key, setting_value, label, kategori) VALUES
('kuota_default', '200', 'Kuota Pengunjung Default per Hari', 'operasional'),
('rekening_bank', 'BCA', 'Nama Bank', 'pembayaran'),
('rekening_nomor', '1234567890', 'Nomor Rekening', 'pembayaran'),
('rekening_nama', 'Waterboom Cahaya Abadi', 'Atas Nama Rekening', 'pembayaran'),
('pajak_persen', '10', 'Besar Pajak (PB1/PPN) %', 'pembayaran')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ─── 7. Dummy data FAQ ───
INSERT INTO faq (pertanyaan, jawaban, urutan, aktif) VALUES
('Apakah Waterboom Cahaya Abadi buka setiap hari?', 'Ya, Waterboom Cahaya Abadi buka setiap hari dari jam 08.00 sampai 17.00 WIB. Namun pada hari-hari tertentu bisa ada penyesuaian, silakan hubungi kami untuk info terbaru.', 1, 'Ya'),
('Apakah anak di bawah 3 tahun dikenakan biaya tiket?', 'Anak di bawah 3 tahun gratis masuk dengan pendampingan orang tua. Namun tetap wajib didampingi oleh orang dewasa.', 2, 'Ya'),
('Bolehkah membawa makanan dari luar?', 'Pengunjung diperbolehkan membawa makanan dan minuman dari luar. Kami juga menyediakan kantin dan saung untuk bersantai.', 3, 'Ya'),
('Apakah ada fasilitas locker dan kamar ganti?', 'Ya, tersedia kamar ganti dan area bilas. Untuk keamanan barang, kami sarankan tidak membawa barang berharga berlebihan.', 4, 'Ya'),
('Bagaimana cara melakukan reservasi online?', 'Anda bisa melakukan reservasi melalui halaman Reservasi di website ini. Pilih tanggal kunjungan, jumlah tiket, lalu pilih metode pembayaran. Kode booking akan langsung diberikan setelah reservasi selesai.', 5, 'Ya');

-- ─── 8. Dummy data berita ───
INSERT INTO berita (judul, isi, aktif) VALUES
('Promo Spesial Akhir Pekan!', 'Nikmati diskon spesial untuk kunjungan grup minimal 10 orang di akhir pekan! Dapatkan potongan harga hingga 20% untuk tiket masuk. Promo berlaku setiap Sabtu dan Minggu sepanjang bulan ini. Yuk ajak keluarga dan teman-teman untuk seru-seruan bareng!', 'Ya'),
('Wahana Baru: Water Slide Extreme', 'Waterboom Cahaya Abadi kini hadir dengan wahana terbaru — Water Slide Extreme! Perosotan sepanjang 50 meter dengan liku-liku yang menantang adrenalin. Cocok untuk kamu yang suka tantangan. Wahana ini sudah bisa dinikmati mulai bulan ini. Ayo cobain!', 'Ya');
