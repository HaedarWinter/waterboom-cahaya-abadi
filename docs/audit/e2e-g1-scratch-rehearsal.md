# G1 Scratch Rehearsal Evidence
**Date:** 2026-10-22  
**Gate:** G1 approved. G2 and G3 remain closed.  
**Scope:** Test MySQL only (`127.0.0.1:3307`). No command targeted port 3306 or `db_ujikom`.

## Source snapshot

```text
TARGET: db=db_ujikom_test port=3307 host=Laptop-Haedar.local
admin: 1
audit_logs: 0
booking_status_history: 2
cash_accounts: 1
ledger_entries: 5
payments: 1
settings: 5
```

## External dump

```text
dump_path=/Users/haedarwezzer/waterboom-dumps/db_ujikom_test-g1.sql
dump_size=12301 bytes
sha256=261a778eca326ff0e7c4fb7b42ef16ed6fb47c6e42e581500e643a59201a27f6
```

## Clone creation

```text
DATABASE() @@port @@hostname
db_ujikom_test 3307 Laptop-Haedar.local
DATABASE() @@port @@hostname
db_ujikom_scratch_test 3307 Laptop-Haedar.local
```

## Result: `sql/db.sql` on cloned test data

```text
=== g1-db.sql ===
Note (Code 1050): Table 'admin' already exists
Note (Code 1050): Table 'settings' already exists
ERROR 1062 (23000) at line 51: Duplicate entry 'kuota_default' for key 'settings.PRIMARY'
```

**Finding:** `sql/db.sql` is not rerun-safe against the existing test schema/data. The unguarded settings INSERT at line 51 fails when `kuota_default` exists.

## Clean-base rehearsal

`sql/db.sql` completed on an empty test-only base database after the `USE` target was rewritten outside the repository:

```text
db.sql clean EXIT=0
Tables_in_db_ujikom_base_test
admin
berita
buku_tamu
faq
galeri
kuota_harian
reservasi
reviews
settings
```

## Sequential migration rehearsal after `db.sql`

### `sql/migration_v3_foundation.sql`

```text
ERROR 1064 (42000) at line 50: You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near 'IF NOT EXISTS idx_laporan_tanggal (tanggal),
    ADD INDEX IF NOT EXISTS idx_lap' at line 2
EXIT=1
```

**Finding:** MySQL 9.4 rejects `ALTER TABLE ... ADD INDEX IF NOT EXISTS` at lines 50–57. The migration stopped before creating later tables such as `cash_accounts`, `payments`, `refunds`, and `ledger_entries`.

### `sql/migration_v3_state_machine.sql`

```text
ERROR 1064 (42000) at line 48: You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near 'IF NOT EXISTS idx_reservasi_status (status)' at line 2
EXIT=1
```

**Finding:** MySQL 9.4 rejects `ALTER TABLE ... ADD INDEX IF NOT EXISTS` at line 48.

### `sql/migration_v3_cash_account.sql`

```text
ERROR 1146 (42S02) at line 10: Table 'db_ujikom_base_test.cash_accounts' doesn't exist
EXIT=1
```

**Finding:** This failure follows the incomplete foundation migration. `cash_accounts` was not created.

### `sql/migration_v3_e2e_delta.sql`

```text
EXIT=0
Tables_in_db_ujikom_base_test
admin
audit_logs
berita
buku_tamu
faq
galeri
kuota_harian
laporan_keuangan
login_attempts
reservasi
reviews
settings
```

**Finding:** Delta migration succeeds on the scratch schema. Its idempotency was not rerun after first success.

## G1 verdict

**BLOCKED.** Existing migration sequence cannot be applied to `db_ujikom_test` as-is. Do not open G2 until owner reviews the errors and approves a new delta migration strategy. No migration was applied to `db_ujikom_test`.

## Required owner decisions

1. Approve creation of a new compatibility delta migration that replaces unsupported guarded index statements for MySQL 9.4.
2. Decide whether `sql/db.sql` settings seeding should be made idempotent in a separate new migration rather than modifying the legacy file.
3. Confirm whether the out-of-repo scratch databases may be retained for diagnosis or should be removed. Removal is destructive and requires explicit approval.
