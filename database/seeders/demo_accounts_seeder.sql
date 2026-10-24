-- Demo accounts seeder for MariaDB/MySQL role-based authorization.
-- Shared demo password: demo123 (bcrypt hash; dummy account only).
-- Run after roles_permissions_seeder.sql

START TRANSACTION;

-- Get role IDs
SET @finance_id = (SELECT id FROM roles WHERE code = 'finance');
SET @front_office_id = (SELECT id FROM roles WHERE code = 'front_office');
SET @gate_id = (SELECT id FROM roles WHERE code = 'gate');
SET @manager_id = (SELECT id FROM roles WHERE code = 'manager');
SET @content_id = (SELECT id FROM roles WHERE code = 'content');

-- Insert demo users (legacy enum role + role_id reference)
INSERT IGNORE INTO `admin` (`username`, `email`, `password`, `role`, `role_id`, `permissions`, `status`) VALUES
('demo_finance', 'finance@demo.cahayaabadi.id', '$2y$12$.MuZHEqGookQ2R/FjsTIouPDpD4hYWfxoM5F0IpTw6Wv5kIq34CKi', 'Finance', @finance_id, '["dashboard.view","payment.view","payment.verify","payment.reject","quota.view","ledger.view","ledger.entry_manual","reconciliation.manage","closing.perform","report.finance.view","report.finance.export"]', 'Aktif'),
('demo_fo', 'fo@demo.cahayaabadi.id', '$2y$12$.MuZHEqGookQ2R/FjsTIouPDpD4hYWfxoM5F0IpTw6Wv5kIq34CKi', 'Admin', @front_office_id, '["dashboard.view","reservation.view","reservation.create","reservation.update","reservation.cancel","payment.view","payment.verify","payment.reject","checkin.scan","checkin.view_log","quota.view","refund.request","review.moderate"]', 'Aktif'),
('demo_gate', 'gate@demo.cahayaabadi.id', '$2y$12$.MuZHEqGookQ2R/FjsTIouPDpD4hYWfxoM5F0IpTw6Wv5kIq34CKi', 'Gate', @gate_id, '["dashboard.view","checkin.scan","checkin.view_log"]', 'Aktif'),
('demo_mgr', 'manager@demo.cahayaabadi.id', '$2y$12$.MuZHEqGookQ2R/FjsTIouPDpD4hYWfxoM5F0IpTw6Wv5kIq34CKi', 'Admin', @manager_id, '["dashboard.view","reservation.view","reservation.create","reservation.update","reservation.export","payment.view","checkin.scan","checkin.view_log","checkin.override","quota.view","quota.manage","price.manage","refund.approve","report.finance.view","review.moderate"]', 'Aktif'),
('demo_content', 'content@demo.cahayaabadi.id', '$2y$12$.MuZHEqGookQ2R/FjsTIouPDpD4hYWfxoM5F0IpTw6Wv5kIq34CKi', 'Admin', @content_id, '["dashboard.view","content.news.manage","content.gallery.manage","content.faq.manage","review.moderate","guestbook.moderate"]', 'Aktif');

COMMIT;

-- Verify
SELECT username, role, role_id, JSON_LENGTH(permissions) as perm_count FROM admin WHERE username LIKE 'demo_%';
