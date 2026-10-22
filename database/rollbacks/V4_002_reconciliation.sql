-- V4_002 reconciliation rollback.
-- WARNING: deletes reconciliation audit history.
-- Run only on an isolated database after owner approval.

DROP TABLE IF EXISTS ledger_reconciliations;
