-- Demo data seeder (dummy bookings, payments, ledger, closings)
-- Run after demo_accounts_seeder.sql

START TRANSACTION;

-- 1. Dummy booking items (if needed for Phase 5)
INSERT IGNORE INTO `booking_items` (`booking_id`, `ticket_type_id`, `qty`, `unit_price`, `subtotal`) VALUES
(1, 1, 2, 150000, 300000),
(1, 2, 1, 100000, 100000),
(2, 3, 1, 200000, 200000),
(3, 1, 3, 150000, 450000);

-- 2. Dummy payments (MENUNGGU_VERIFIKASI, TERVERIFIKASI, DITOLAK)
-- Use IDs that match existing ledger entries (1000001, 3, 4) + new one (2)
INSERT IGNORE INTO `payments` (`id`, `booking_id`, `attempt_no`, `method`, `amount`, `amount_received`, `status`, `verified_by`, `verified_at`) VALUES
(2, 2, 1, 'TRANSFER_BANK', 200000, NULL, 'MENUNGGU_VERIFIKASI', NULL, NULL),
(1000006, 1, 1, 'TRANSFER_BANK', 400000, 400000, 'TERVERIFIKASI', 6, NOW()),
(1000007, 3, 1, 'TRANSFER_BANK', 450000, 450000, 'TERVERIFIKASI', 6, NOW()),
(1000008, 4, 1, 'TRANSFER_BANK', 250000, 250000, 'TERVERIFIKASI', 6, NOW());

-- 3. Dummy ledger entries - use matching payment IDs
INSERT IGNORE INTO `ledger_entries` (`entry_no`, `entry_date`, `cash_account_id`, `direction`, `category`, `amount`, `source_type`, `source_id`, `created_by`, `note`) VALUES
('LED-20261022-1000005', '2026-10-20', 1, 'IN', 'TIKET', 400000, 'PAYMENT', 1000006, 6, 'Booking demo payment verified'),
('LED-20261022-1000006', '2026-10-20', 1, 'IN', 'TIKET', 450000, 'PAYMENT', 1000007, 6, 'Booking demo payment verified'),
('LED-20261022-1000007', '2026-10-20', 1, 'IN', 'TIKET', 250000, 'PAYMENT', 1000008, 6, 'Booking demo payment verified');

-- 4. Dummy daily closing (for demo - legacy schema)
INSERT IGNORE INTO `daily_closings` (`closing_date`, `cash_account_id`, `total_in`, `total_out`, `system_balance`, `physical_balance`, `difference`, `status`, `closed_by`, `closed_at`) VALUES
('2026-10-20', 1, 400000, 150000, 450000, 450000, 0, 'CLOSED', 6, NOW()),
('2026-10-21', 1, 450000, 50000, 850000, 850000, 0, 'CLOSED', 6, NOW());

COMMIT;

-- Verify
SELECT 'Booking items:' as info, COUNT(*) as count FROM booking_items;
SELECT 'Payments:' as info, COUNT(*) as count FROM payments;
SELECT 'Ledger entries:' as info, COUNT(*) as count FROM ledger_entries WHERE source_type IN ('PAYMENT', 'EXPENSE', 'ADJUSTMENT');
SELECT 'Daily closings:' as info, COUNT(*) as count FROM daily_closings WHERE status = 'CLOSED';