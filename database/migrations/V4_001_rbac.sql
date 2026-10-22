CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    permission_key VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id),
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @has_role_id = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'admin' AND column_name = 'role_id'
);
SET @add_role_id = IF(@has_role_id = 0, 'ALTER TABLE admin ADD COLUMN role_id INT NULL', 'SELECT 1');
PREPARE add_role_id_statement FROM @add_role_id;
EXECUTE add_role_id_statement;
DEALLOCATE PREPARE add_role_id_statement;

INSERT INTO roles (code, name) VALUES
    ('super_admin', 'Super Admin'),
    ('manager', 'Manajer Operasional'),
    ('front_office', 'Front Office'),
    ('finance', 'Keuangan'),
    ('gate', 'Petugas Gerbang'),
    ('content', 'Admin Konten')
ON DUPLICATE KEY UPDATE name = VALUES(name), is_active = 1;

INSERT INTO permissions (permission_key, description) VALUES
    ('dashboard.view', 'Melihat dashboard'),
    ('reservation.view', 'Melihat reservasi'),
    ('reservation.create', 'Membuat reservasi walk-in'),
    ('reservation.update', 'Mengubah reservasi'),
    ('reservation.cancel', 'Membatalkan reservasi'),
    ('reservation.export', 'Mengekspor reservasi'),
    ('payment.view', 'Melihat pembayaran'),
    ('payment.verify', 'Memverifikasi pembayaran'),
    ('payment.reject', 'Menolak pembayaran'),
    ('checkin.scan', 'Memindai QR check-in'),
    ('checkin.view_log', 'Melihat log check-in'),
    ('checkin.override', 'Override check-in'),
    ('quota.view', 'Melihat kuota'),
    ('quota.manage', 'Mengelola kuota'),
    ('price.manage', 'Mengelola harga'),
    ('refund.request', 'Mengajukan refund'),
    ('refund.approve', 'Menyetujui refund'),
    ('ledger.view', 'Melihat buku kas'),
    ('ledger.entry_manual', 'Membuat entri manual ledger'),
    ('reconciliation.manage', 'Mengelola rekonsiliasi'),
    ('reconciliation.approve', 'Menyetujui rekonsiliasi'),
    ('closing.perform', 'Melakukan tutup harian'),
    ('closing.reopen', 'Membuka periode tertutup'),
    ('report.finance.view', 'Melihat laporan keuangan'),
    ('report.finance.export', 'Mengekspor laporan keuangan'),
    ('content.news.manage', 'Mengelola berita'),
    ('content.gallery.manage', 'Mengelola galeri'),
    ('content.faq.manage', 'Mengelola FAQ'),
    ('review.moderate', 'Memoderasi ulasan'),
    ('guestbook.moderate', 'Memoderasi buku tamu'),
    ('user.manage', 'Mengelola akun'),
    ('role.manage', 'Mengelola peran'),
    ('settings.manage', 'Mengelola pengaturan'),
    ('activity.view', 'Melihat log aktivitas'),
    ('system.backup', 'Melakukan backup'),
    ('system.health', 'Melihat health check')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'super_admin'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

UPDATE admin a JOIN roles r ON r.code = CASE a.role
    WHEN 'Super Admin' THEN 'super_admin'
    WHEN 'Admin' THEN 'front_office'
    WHEN 'Akuntan' THEN 'finance'
    WHEN 'Finance' THEN 'finance'
    WHEN 'Gate' THEN 'gate'
    ELSE NULL
END SET a.role_id = r.id
WHERE a.role_id IS NULL;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.permission_key IN (
    'dashboard.view', 'reservation.view', 'reservation.create', 'reservation.update',
    'reservation.cancel', 'payment.view', 'payment.verify', 'payment.reject',
    'checkin.scan', 'checkin.view_log', 'quota.view', 'refund.request'
) WHERE r.code = 'front_office'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.permission_key IN (
    'dashboard.view', 'reservation.view', 'payment.view', 'payment.verify', 'payment.reject',
    'quota.view', 'ledger.view', 'ledger.entry_manual', 'reconciliation.manage',
    'closing.perform', 'report.finance.view', 'report.finance.export', 'settings.manage'
) WHERE r.code = 'finance'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.permission_key IN (
    'dashboard.view', 'checkin.scan', 'checkin.view_log'
) WHERE r.code = 'gate'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.permission_key IN (
    'dashboard.view', 'reservation.view', 'reservation.create', 'reservation.update',
    'reservation.cancel', 'payment.view', 'checkin.scan', 'checkin.view_log',
    'checkin.override', 'quota.view', 'quota.manage', 'price.manage', 'refund.request',
    'refund.approve', 'report.finance.view'
) WHERE r.code = 'manager'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.permission_key IN (
    'dashboard.view', 'content.news.manage', 'content.gallery.manage', 'content.faq.manage',
    'review.moderate', 'guestbook.moderate'
) WHERE r.code = 'content'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);
