/*!40101 SET @saved_cs_client     = @@character_set_client */;
SET FOREIGN_KEY_CHECKS=0;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Super Admin','Admin','Finance','Gate','Akuntan') NOT NULL DEFAULT 'Admin',
  `status` enum('Aktif','Nonaktif') DEFAULT 'Aktif',
  `permissions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `admin_username` varchar(50) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `target_table` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_admin` (`admin_id`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `berita` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `judul` varchar(200) NOT NULL,
  `isi` text NOT NULL,
  `gambar` varchar(255) DEFAULT NULL,
  `aktif` enum('Ya','Tidak') DEFAULT 'Ya',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `booking_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `ticket_type_id` bigint(20) unsigned NOT NULL,
  `qty` int(10) unsigned NOT NULL,
  `unit_price` bigint(20) unsigned NOT NULL,
  `subtotal` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_booking_items_booking` (`booking_id`),
  CONSTRAINT `fk_booking_items_booking` FOREIGN KEY (`booking_id`) REFERENCES `reservasi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `booking_status_history` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `from_status` varchar(30) DEFAULT NULL,
  `to_status` varchar(30) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `actor_role` varchar(30) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_history_booking` (`booking_id`),
  KEY `idx_history_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `buku_tamu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `no_hp` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `tanggal` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cash_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('CASH','BANK','EWALLET') NOT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `account_holder` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cash_account_code` (`code`),
  KEY `idx_cash_account_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_closings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `closing_date` date NOT NULL,
  `cash_account_id` int(11) NOT NULL,
  `total_in` bigint(20) NOT NULL DEFAULT 0,
  `total_out` bigint(20) NOT NULL DEFAULT 0,
  `system_balance` bigint(20) NOT NULL DEFAULT 0,
  `physical_balance` bigint(20) DEFAULT NULL,
  `difference` bigint(20) NOT NULL DEFAULT 0,
  `status` enum('OPEN','CLOSED') NOT NULL DEFAULT 'CLOSED',
  `closed_by` int(11) DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `reopened_by` int(11) DEFAULT NULL,
  `reopened_at` datetime DEFAULT NULL,
  `reopen_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_closing_day_account` (`closing_date`,`cash_account_id`),
  KEY `idx_closing_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `expense_date` date NOT NULL,
  `cash_account_id` int(11) NOT NULL,
  `category` enum('OPERASIONAL','MAINTENANCE','UTILITIES','PAYROLL','MARKETING','LAINNYA') NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` bigint(20) unsigned NOT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `receipt_path` varchar(500) DEFAULT NULL,
  `status` enum('DRAFT','POSTED','VOIDED') NOT NULL DEFAULT 'DRAFT',
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `voided_by` int(11) DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `void_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_expenses_cash_account` (`cash_account_id`),
  KEY `idx_expenses_date_account` (`expense_date`,`cash_account_id`),
  KEY `idx_expenses_status` (`status`),
  KEY `idx_expenses_created_by` (`created_by`),
  CONSTRAINT `fk_expenses_cash_account` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faq` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pertanyaan` varchar(255) NOT NULL,
  `jawaban` text NOT NULL,
  `urutan` int(11) DEFAULT 0,
  `aktif` enum('Ya','Tidak') DEFAULT 'Ya',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `galeri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `judul` varchar(100) NOT NULL,
  `deskripsi` varchar(255) DEFAULT NULL,
  `file_gambar` varchar(255) NOT NULL,
  `urutan` int(11) DEFAULT 0,
  `aktif` enum('Ya','Tidak') DEFAULT 'Ya',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kuota_harian` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `kuota_max` int(11) DEFAULT 200,
  `terisi` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tanggal` (`tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `laporan_keuangan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `tipe` enum('Masuk','Keluar') NOT NULL,
  `kategori` varchar(50) NOT NULL,
  `nominal` decimal(15,2) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `lampiran` varchar(255) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `status` enum('Final','Draft') DEFAULT 'Final',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_laporan_tanggal` (`tanggal`),
  KEY `idx_laporan_tipe` (`tipe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ledger_entries` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `entry_no` varchar(30) NOT NULL,
  `entry_date` date NOT NULL,
  `posted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cash_account_id` int(11) NOT NULL,
  `direction` enum('IN','OUT') NOT NULL,
  `category` enum('TIKET','KODE_UNIK','REFUND','BIAYA_OPERASIONAL','KOREKSI','LAINNYA','SALDO_AWAL','BACKFILL') NOT NULL,
  `amount` bigint(20) NOT NULL,
  `source_type` enum('PAYMENT','REFUND','EXPENSE','ADJUSTMENT','BACKFILL') NOT NULL,
  `source_id` int(11) DEFAULT NULL,
  `reverses_entry_id` bigint(20) DEFAULT NULL,
  `reconciled_at` datetime DEFAULT NULL,
  `reconciliation_id` int(11) DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `reverses_entry_key` bigint(20) GENERATED ALWAYS AS (coalesce(`reverses_entry_id`,0)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ledger_entry_no` (`entry_no`),
  UNIQUE KEY `uq_ledger_source` (`source_type`,`source_id`,`category`,`reverses_entry_key`),
  KEY `idx_ledger_account_date` (`cash_account_id`,`entry_date`),
  KEY `idx_ledger_reconciled` (`reconciled_at`),
  KEY `idx_ledger_entries_locked` (`is_locked`,`entry_date`),
  CONSTRAINT `chk_ledger_amount_positive` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ledger_reconciliations` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `ledger_entry_id` bigint(20) NOT NULL,
  `status` enum('RECONCILED','REVERTED') NOT NULL DEFAULT 'RECONCILED',
  `reconciled_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_ledger_reconciliations_admin` (`reconciled_by`),
  KEY `idx_ledger_reconciliations_entry` (`ledger_entry_id`),
  KEY `idx_ledger_reconciliations_status` (`status`),
  CONSTRAINT `fk_ledger_reconciliations_admin` FOREIGN KEY (`reconciled_by`) REFERENCES `admin` (`id`),
  CONSTRAINT `fk_ledger_reconciliations_entry` FOREIGN KEY (`ledger_entry_id`) REFERENCES `ledger_entries` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ip_address` (`ip_address`),
  KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_outbox` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `channel` enum('WHATSAPP','EMAIL','SMS') NOT NULL DEFAULT 'WHATSAPP',
  `recipient` varchar(100) NOT NULL,
  `template_key` varchar(50) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `status` enum('PENDING','SENT','FAILED','DEAD') NOT NULL DEFAULT 'PENDING',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `max_attempts` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `last_error` text DEFAULT NULL,
  `scheduled_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  `idempotency_key` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_outbox_idempotency` (`idempotency_key`),
  KEY `idx_outbox_status_scheduled` (`status`,`scheduled_at`),
  KEY `idx_outbox_channel_recipient` (`channel`,`recipient`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_proofs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `storage_path` varchar(255) NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `sha256` char(64) DEFAULT NULL,
  `is_legacy` tinyint(1) NOT NULL DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_proof_payment` (`payment_id`),
  KEY `idx_proof_sha256` (`sha256`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `attempt_no` int(11) NOT NULL DEFAULT 1,
  `method` enum('TRANSFER_BANK','CASH_COUNTER','QRIS','LAINNYA') NOT NULL DEFAULT 'TRANSFER_BANK',
  `provider_ref` varchar(100) DEFAULT NULL,
  `amount` bigint(20) NOT NULL,
  `unique_code` int(11) NOT NULL DEFAULT 0,
  `amount_received` bigint(20) DEFAULT NULL,
  `transfer_at` datetime DEFAULT NULL,
  `destination_account_id` int(11) DEFAULT NULL,
  `status` enum('BELUM_ADA_BUKTI','MENUNGGU_VERIFIKASI','TERVERIFIKASI','DITOLAK','DIREFUND_SEBAGIAN','DIREFUND_PENUH') NOT NULL DEFAULT 'BELUM_ADA_BUKTI',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_attempt` (`booking_id`,`attempt_no`),
  KEY `idx_payment_booking` (`booking_id`),
  KEY `idx_payment_status` (`status`),
  KEY `idx_payment_destination` (`destination_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `permission_key` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `permission_key` (`permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `refunds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `amount` bigint(20) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('DIAJUKAN','DISETUJUI','DITOLAK','DIREFUND') NOT NULL DEFAULT 'DIAJUKAN',
  `requested_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_refund_booking` (`booking_id`),
  KEY `idx_refund_status` (`status`),
  CONSTRAINT `chk_refund_amount_positive` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reservasi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_booking` varchar(10) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `no_hp` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `tanggal_kunjungan` date NOT NULL,
  `visit_date` date DEFAULT NULL,
  `jenis_tiket` enum('Weekday','Weekend') NOT NULL,
  `jumlah_dewasa` int(11) DEFAULT 1,
  `jumlah_anak` int(11) DEFAULT 0,
  `total_harga` int(11) NOT NULL,
  `status` enum('MENUNGGU_BAYAR','MENUNGGU_VERIFIKASI','LUNAS','DITOLAK','KEDALUWARSA','DIBATALKAN','SELESAI','REFUND_DIAJUKAN','DIREFUND','TIDAK_HADIR') NOT NULL DEFAULT 'MENUNGGU_BAYAR',
  `catatan` text DEFAULT NULL,
  `catatan_tolak` text DEFAULT NULL,
  `metode_bayar` enum('Bayar di Lokasi','Transfer Bank') DEFAULT 'Bayar di Lokasi',
  `channel` enum('ONLINE','COUNTER') NOT NULL DEFAULT 'ONLINE',
  `bukti_bayar` varchar(255) DEFAULT NULL,
  `qr_token` varchar(64) DEFAULT NULL,
  `is_checkin` tinyint(1) DEFAULT 0,
  `waktu_checkin` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_deadline_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_booking` (`kode_booking`),
  KEY `status` (`status`),
  KEY `tanggal_kunjungan` (`tanggal_kunjungan`),
  KEY `idx_reservasi_status` (`status`),
  KEY `idx_reservasi_status_deadline` (`status`,`payment_deadline_at`),
  KEY `idx_reservasi_visit_date` (`visit_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `ulasan` text DEFAULT NULL,
  `status` enum('Pending','Approved','Ditolak') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_role_permissions_permission` (`permission_id`),
  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`),
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `label` varchar(100) DEFAULT NULL,
  `kategori` varchar(50) DEFAULT 'umum',
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_prices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_type_id` bigint(20) unsigned NOT NULL,
  `price` bigint(20) unsigned NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ticket_prices_type_effective` (`ticket_type_id`,`effective_from`,`effective_to`),
  CONSTRAINT `fk_ticket_prices_type` FOREIGN KEY (`ticket_type_id`) REFERENCES `ticket_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_types_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wa_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wa_templates_key` (`key`),
  KEY `idx_wa_templates_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
SET FOREIGN_KEY_CHECKS=1;
