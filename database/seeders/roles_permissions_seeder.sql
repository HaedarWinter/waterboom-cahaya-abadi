-- Seeder: roles + permissions (production structure for test DB)
-- Run once on test DB to populate RBAC tables

START TRANSACTION;

-- 1. Seed roles table (if not exists)
INSERT IGNORE INTO `roles` (`code`, `name`, `is_active`) VALUES
('super_admin', 'Super Admin', 1),
('manager', 'Manager Operasional', 1),
('front_office', 'Front Office / Kasir', 1),
('finance', 'Keuangan / Akuntan', 1),
('gate', 'Petugas Gerbang', 1),
('content', 'Admin Konten', 1);

-- 2. Seed permissions table (32 canonical permissions from PRD §3.2)
INSERT IGNORE INTO `permissions` (`permission_key`, `description`) VALUES
('dashboard.view', 'Lihat dashboard'),
('reservation.view', 'Lihat reservasi'),
('reservation.create', 'Buat reservasi walk-in'),
('reservation.update', 'Ubah/batalkan reservasi'),
('reservation.cancel', 'Batalkan reservasi'),
('reservation.export', 'Ekspor reservasi'),
('payment.view', 'Lihat pembayaran'),
('payment.verify', 'Verifikasi bukti bayar'),
('payment.reject', 'Tolak bukti bayar'),
('checkin.scan', 'Scan QR check-in'),
('checkin.view_log', 'Lihat log check-in'),
('checkin.override', 'Override check-in manual'),
('quota.view', 'Lihat kuota'),
('quota.manage', 'Kelola kuota'),
('price.manage', 'Kelola harga tiket'),
('refund.request', 'Ajukan refund'),
('refund.approve', 'Setujui refund (maker-checker)'),
('ledger.view', 'Lihat buku kas'),
('ledger.entry_manual', 'Catat pengeluaran/koreksi manual'),
('reconciliation.manage', 'Kelola rekonsiliasi'),
('reconciliation.approve', 'Setujui rekonsiliasi (super_admin only)'),
('closing.perform', 'Tutup harian & lock periode'),
('closing.reopen', 'Buka periode tertutup'),
('report.finance.view', 'Lihat laporan keuangan'),
('report.finance.export', 'Ekspor laporan keuangan'),
('content.news.manage', 'Kelola berita'),
('content.gallery.manage', 'Kelola galeri'),
('content.faq.manage', 'Kelola FAQ'),
('review.moderate', 'Moderasi ulasan & buku tamu'),
('guestbook.moderate', 'Moderasi buku tamu'),
('user.manage', 'Kelola akun admin'),
('role.manage', 'Kelola peran & izin'),
('settings.manage', 'Kelola pengaturan sistem'),
('activity.view', 'Lihat log aktivitas'),
('system.backup', 'Backup sistem'),
('system.health', 'Health check sistem');

-- 3. Assign permissions to roles (role_permissions)
-- Get role IDs dynamically
SET @super_admin_id = (SELECT id FROM roles WHERE code = 'super_admin');
SET @manager_id = (SELECT id FROM roles WHERE code = 'manager');
SET @front_office_id = (SELECT id FROM roles WHERE code = 'front_office');
SET @finance_id = (SELECT id FROM roles WHERE code = 'finance');
SET @gate_id = (SELECT id FROM roles WHERE code = 'gate');
SET @content_id = (SELECT id FROM roles WHERE code = 'content');

-- super_admin: ALL
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT @super_admin_id, id FROM permissions;

-- manager: dashboard, reservations, payments (view), check-in, quota, price, refund approve, reports, reviews
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT @manager_id, id FROM permissions WHERE permission_key IN 
('dashboard.view', 'reservation.view', 'reservation.create', 'reservation.update', 'reservation.export', 
 'payment.view', 'checkin.scan', 'checkin.view_log', 'checkin.override', 'quota.view', 'quota.manage', 
 'price.manage', 'refund.approve', 'report.finance.view', 'review.moderate');

-- front_office: dashboard, reservations, payments (verify/reject), check-in, quota view, refund request, reviews
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT @front_office_id, id FROM permissions WHERE permission_key IN 
('dashboard.view', 'reservation.view', 'reservation.create', 'reservation.update', 'reservation.cancel',
 'payment.view', 'payment.verify', 'payment.reject', 'checkin.scan', 'checkin.view_log', 'quota.view', 'refund.request', 'review.moderate');

-- finance: dashboard, payments, ledger, reconciliation, closing, reports, quota view
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT @finance_id, id FROM permissions WHERE permission_key IN 
('dashboard.view', 'payment.view', 'payment.verify', 'payment.reject', 'quota.view',
 'ledger.view', 'ledger.entry_manual', 'reconciliation.manage', 'closing.perform', 'report.finance.view', 'report.finance.export');

-- gate: dashboard, check-in scan/view
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT @gate_id, id FROM permissions WHERE permission_key IN 
('dashboard.view', 'checkin.scan', 'checkin.view_log');

-- content: dashboard, content management, reviews/guestbook moderate
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT @content_id, id FROM permissions WHERE permission_key IN 
('dashboard.view', 'content.news.manage', 'content.gallery.manage', 'content.faq.manage', 'review.moderate', 'guestbook.moderate');

COMMIT;

-- Verify
SELECT 'Roles seeded:' as info, COUNT(*) as count FROM roles;
SELECT 'Permissions seeded:' as info, COUNT(*) as count FROM permissions;
SELECT 'Role-permission mappings:' as info, COUNT(*) as count FROM role_permissions;