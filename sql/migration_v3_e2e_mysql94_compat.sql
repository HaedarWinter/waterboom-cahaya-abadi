CREATE TABLE IF NOT EXISTS buku_tamu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    no_hp VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS galeri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(100) NOT NULL,
    deskripsi VARCHAR(255),
    file_gambar VARCHAR(255) NOT NULL,
    urutan INT DEFAULT 0,
    aktif ENUM('Ya','Tidak') DEFAULT 'Ya',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
    status ENUM('MENUNGGU_BAYAR','MENUNGGU_VERIFIKASI','LUNAS','DITOLAK','KEDALUWARSA','DIBATALKAN','SELESAI','REFUND_DIAJUKAN','DIREFUND','TIDAK_HADIR') NOT NULL DEFAULT 'MENUNGGU_BAYAR',
    qr_token VARCHAR(100) DEFAULT NULL,
    catatan TEXT,
    catatan_tolak TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_checkin TINYINT(1) NOT NULL DEFAULT 0,
    waktu_checkin DATETIME NULL,
    INDEX idx_reservasi_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS kuota_harian (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL UNIQUE,
    kuota_max INT NOT NULL DEFAULT 200,
    terisi INT DEFAULT 0,
    keterangan VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    rating TINYINT NOT NULL,
    ulasan TEXT,
    status ENUM('Pending','Approved','Ditolak') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS berita (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(200) NOT NULL,
    isi TEXT NOT NULL,
    gambar VARCHAR(255) DEFAULT NULL,
    aktif ENUM('Ya','Tidak') DEFAULT 'Ya',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS faq (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pertanyaan VARCHAR(255) NOT NULL,
    jawaban TEXT NOT NULL,
    urutan INT DEFAULT 0,
    aktif ENUM('Ya','Tidak') DEFAULT 'Ya',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS laporan_keuangan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    tipe ENUM('Masuk', 'Keluar') NOT NULL DEFAULT 'Keluar',
    kategori VARCHAR(50) NOT NULL DEFAULT 'Operasional',
    nominal DECIMAL(15, 2) NOT NULL DEFAULT 0,
    keterangan TEXT NULL,
    lampiran VARCHAR(255) NULL,
    tanggal DATE NOT NULL,
    status ENUM('Final', 'Draft') DEFAULT 'Final',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_laporan_tanggal (tanggal),
    INDEX idx_laporan_tipe (tipe)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS payment_proofs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    storage_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NULL,
    mime_type VARCHAR(100) NULL,
    file_size INT UNSIGNED NULL,
    sha256 CHAR(64) NULL,
    is_legacy TINYINT(1) NOT NULL DEFAULT 0,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_proof_payment (payment_id),
    INDEX idx_proof_sha256 (sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS refunds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    payment_id INT NULL,
    amount BIGINT NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('DIAJUKAN', 'DISETUJUI', 'DITOLAK', 'DIREFUND') NOT NULL DEFAULT 'DIAJUKAN',
    requested_by INT NULL,
    approved_by INT NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_refund_booking (booking_id),
    INDEX idx_refund_status (status),
    CONSTRAINT chk_refund_amount_positive CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS daily_closings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    closing_date DATE NOT NULL,
    cash_account_id INT NOT NULL,
    total_in BIGINT NOT NULL DEFAULT 0,
    total_out BIGINT NOT NULL DEFAULT 0,
    system_balance BIGINT NOT NULL DEFAULT 0,
    physical_balance BIGINT NULL,
    difference BIGINT NOT NULL DEFAULT 0,
    status ENUM('OPEN', 'CLOSED') NOT NULL DEFAULT 'CLOSED',
    closed_by INT NULL,
    closed_at DATETIME NULL,
    reopened_by INT NULL,
    reopened_at DATETIME NULL,
    reopen_reason TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_closing_day_account (closing_date, cash_account_id),
    INDEX idx_closing_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(50) NOT NULL,
    attempt_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_ip_time (ip_address, attempt_time),
    INDEX idx_login_attempts_username_time (username, attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE admin
    MODIFY COLUMN role ENUM('Super Admin', 'Admin', 'Finance', 'Gate', 'Akuntan') NOT NULL DEFAULT 'Admin';

INSERT INTO settings (setting_key, setting_value, label, kategori) VALUES
    ('payment_deadline_minutes', '120', 'Batas waktu pembayaran (menit)', 'pembayaran'),
    ('unique_code_enabled', '1', 'Aktifkan kode unik transfer', 'pembayaran'),
    ('reversal_approval_threshold', '1000000', 'Batas nominal reversal yang perlu persetujuan Super Admin', 'pembayaran'),
    ('reconciliation_alert_days', '3', 'Peringatan transaksi belum terekonsiliasi (hari)', 'pembayaran')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

INSERT INTO cash_accounts (code, name, type, is_active)
VALUES ('KAS_001', 'Kas Utama', 'CASH', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), type = VALUES(type), is_active = VALUES(is_active);

INSERT INTO ledger_entries (
    entry_no,
    entry_date,
    cash_account_id,
    direction,
    category,
    amount,
    source_type,
    source_id,
    created_by,
    note
)
SELECT
    CONCAT('LED-', DATE_FORMAT(p.verified_at, '%Y%m%d'), '-', LPAD(p.id, 6, '0')),
    DATE(p.verified_at),
    ca.id,
    'IN',
    'TIKET',
    p.amount_received,
    'PAYMENT',
    p.id,
    p.verified_by,
    CONCAT('Backfill verifikasi payment #', p.id, ' booking #', p.booking_id)
FROM payments p
JOIN cash_accounts ca ON ca.code = 'KAS_001'
WHERE p.status = 'TERVERIFIKASI'
  AND p.amount_received IS NOT NULL
  AND p.amount_received > 0
  AND p.verified_at IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM ledger_entries le
      WHERE le.source_type = 'PAYMENT' AND le.source_id = p.id
  );
