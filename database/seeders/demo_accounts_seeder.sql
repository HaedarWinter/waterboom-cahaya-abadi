-- Demo accounts seeder (6 users with permissions per role)
-- Password: demo123 (bcrypt hash: $2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86AGR0Ifj/m)
-- Run after roles_permissions_seeder.sql

START TRANSACTION;

-- Helper: Get permission keys as JSON for each role
SET @super_admin_perms = (SELECT JSON_ARRAYAGG(permission_key) FROM permissions);
SET @manager_perms = (SELECT JSON_ARRAYAGG(permission_key) FROM permissions WHERE permission_key IN 
('dashboard.view', 'reservation.view', 'reservation.create', 'reservation.update', 'reservation.export', 
 'payment.view', 'checkin.scan', 'checkin.view_log', 'checkin.override', 'quota.view', 'quota.manage', 
 'price.manage', 'refund.approve', 'report.finance.view', 'review.moderate'));
SET @finance_perms = (SELECT JSON_ARRAYAGG(permission_key) FROM permissions WHERE permission_key IN 
('dashboard.view', 'payment.view', 'payment.verify', 'payment.reject', 'quota.view',
 'ledger.view', 'ledger.entry_manual', 'reconciliation.manage', 'closing.perform', 'report.finance.view', 'report.finance.export'));
SET @front_office_perms = (SELECT JSON_ARRAYAGG(permission_key) FROM permissions WHERE permission_key IN 
('dashboard.view', 'reservation.view', 'reservation.create', 'reservation.update', 'reservation.cancel',
 'payment.view', 'payment.verify', 'payment.reject', 'checkin.scan', 'checkin.view_log', 'quota.view', 'refund.request', 'review.moderate'));
SET @gate_perms = (SELECT JSON_ARRAYAGG(permission_key) FROM permissions WHERE permission_key IN 
('dashboard.view', 'checkin.scan', 'checkin.view_log'));
SET @content_perms = (SELECT JSON_ARRAYAGG(permission_key) FROM permissions WHERE permission_key IN 
('dashboard.view', 'content.news.manage', 'content.gallery.manage', 'content.faq.manage', 'review.moderate', 'guestbook.moderate'));

-- Get role IDs
SET @super_admin_id = (SELECT id FROM roles WHERE code = 'super_admin');
SET @finance_id = (SELECT id FROM roles WHERE code = 'finance');
SET @front_office_id = (SELECT id FROM roles WHERE code = 'front_office');
SET @gate_id = (SELECT id FROM roles WHERE code = 'gate');
SET @manager_id = (SELECT id FROM roles WHERE code = 'manager');
SET @content_id = (SELECT id FROM roles WHERE code = 'content');

-- Insert demo users (legacy enum role + role_id reference)
INSERT IGNORE INTO `admin` (`username`, `email`, `password`, `role`, `role_id`, `permissions`, `status`) VALUES
('demo_owner', 'owner@demo.cahayaabadi.id', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86AGR0Ifj/m', 'Super Admin', @super_admin_id, @super_admin_perms, 'Aktif'),
('demo_finance', 'finance@demo.cahayaabadi.id', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86AGR0Ifj/m', 'Finance', @finance_id, @finance_perms, 'Aktif'),
('demo_fo', 'fo@demo.cahayaabadi.id', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86AGR0Ifj/m', 'Admin', @front_office_id, @front_office_perms, 'Aktif'),
('demo_gate', 'gate@demo.cahayaabadi.id', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86AGR0Ifj/m', 'Gate', @gate_id, @gate_perms, 'Aktif'),
('demo_mgr', 'manager@demo.cahayaabadi.id', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86AGR0Ifj/m', 'Admin', @manager_id, @manager_perms, 'Aktif'),
('demo_content', 'content@demo.cahayaabadi.id', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86AGR0Ifj/m', 'Admin', @content_id, @content_perms, 'Aktif');

COMMIT;

-- Verify
SELECT username, role, role_id, JSON_LENGTH(permissions) as perm_count FROM admin WHERE username LIKE 'demo_%';