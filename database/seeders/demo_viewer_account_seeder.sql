-- Read-only demo account for portfolio deployments.
-- Username: demo_admin
-- Password: demo123 (bcrypt hash, dummy account only)
-- Run after roles_permissions_seeder.sql

START TRANSACTION;

-- 1. Dedicated read-only role
INSERT INTO `roles` (`code`, `name`, `is_active`) VALUES
('demo_viewer', 'Demo Read Only', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `is_active` = 1;

SET @demo_role_id = (SELECT id FROM roles WHERE code = 'demo_viewer');

-- 2. Ensure the read-only permissions exist, then replace role mappings
INSERT IGNORE INTO `permissions` (`permission_key`, `description`) VALUES
('dashboard.view', 'Lihat dashboard'),
('reservation.view', 'Lihat reservasi'),
('payment.view', 'Lihat pembayaran'),
('quota.view', 'Lihat kuota'),
('ledger.view', 'Lihat buku kas'),
('report.finance.view', 'Lihat laporan keuangan'),
('activity.view', 'Lihat log aktivitas');

DELETE FROM `role_permissions` WHERE `role_id` = @demo_role_id;

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT @demo_role_id, id FROM `permissions` WHERE `permission_key` IN (
    'dashboard.view',
    'reservation.view',
    'payment.view',
    'quota.view',
    'ledger.view',
    'report.finance.view',
    'activity.view'
);

-- 3. Demo account. role_id drives canonical authorization; permissions is the
-- legacy session fallback with the same read-only list.
INSERT INTO `admin` (`username`, `email`, `password`, `role`, `role_id`, `permissions`, `status`) VALUES
('demo_admin', 'demo_admin@demo.cahayaabadi.id', '$2y$12$.MuZHEqGookQ2R/FjsTIouPDpD4hYWfxoM5F0IpTw6Wv5kIq34CKi', 'Admin', @demo_role_id, '["dashboard.view","reservation.view","payment.view","quota.view","ledger.view","report.finance.view","activity.view"]', 'Aktif')
ON DUPLICATE KEY UPDATE
    `email` = VALUES(`email`),
    `password` = VALUES(`password`),
    `role_id` = VALUES(`role_id`),
    `permissions` = VALUES(`permissions`),
    `status` = 'Aktif';

COMMIT;

-- Verify
SELECT a.username, a.role, r.code AS role_code, a.status
FROM admin a
LEFT JOIN roles r ON r.id = a.role_id
WHERE a.username = 'demo_admin';
