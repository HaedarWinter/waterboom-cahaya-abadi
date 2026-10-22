CREATE TABLE IF NOT EXISTS ledger_reconciliations (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ledger_entry_id BIGINT NOT NULL,
    status ENUM('RECONCILED', 'REVERTED') NOT NULL DEFAULT 'RECONCILED',
    reconciled_by INT NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ledger_reconciliations_entry FOREIGN KEY (ledger_entry_id) REFERENCES ledger_entries(id),
    CONSTRAINT fk_ledger_reconciliations_admin FOREIGN KEY (reconciled_by) REFERENCES admin(id),
    INDEX idx_ledger_reconciliations_entry (ledger_entry_id),
    INDEX idx_ledger_reconciliations_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
