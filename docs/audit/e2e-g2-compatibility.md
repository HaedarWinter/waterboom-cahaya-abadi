# G2 Compatibility Migration Evidence
**Date:** 2026-10-22  
**Target:** `db_ujikom_test` on `127.0.0.1:3307`  
**G2:** approved and completed. **G3:** closed.

## Compatibility migration

Created:
- `sql/migration_v3_e2e_mysql94_compat.sql`
- `sql/rollback_v3_e2e_mysql94_compat.sql`

The compatibility migration avoids unsupported MySQL 9.4 `ADD INDEX IF NOT EXISTS` syntax and includes required v3 tables plus `login_attempts`. Original migrations remain unchanged.

## Scratch rehearsal

First rehearsal found unsupported multi-column `ADD COLUMN IF NOT EXISTS` syntax. The compatibility migration was corrected by defining `is_checkin` and `waktu_checkin` directly in `reservasi`; the redundant ALTER was removed from the new compatibility file.

Final two runs:

```text
=== COMPAT RUN 1 ===
Warning (Code 1681): Integer display width is deprecated and will be removed in a future release.
Warning (Code 1287): 'VALUES function' is deprecated and will be removed in a future release.
EXIT=0

=== COMPAT RUN 2 ===
Note (Code 1050): Table 'buku_tamu' already exists
Note (Code 1050): Table 'galeri' already exists
Note (Code 1050): Table 'reservasi' already exists
Note (Code 1050): Table 'kuota_harian' already exists
Note (Code 1050): Table 'reviews' already exists
Note (Code 1050): Table 'berita' already exists
Note (Code 1050): Table 'faq' already exists
Note (Code 1050): Table 'laporan_keuangan' already exists
Note (Code 1050): Table 'payment_proofs' already exists
Note (Code 1050): Table 'refunds' already exists
Note (Code 1050): Table 'daily_closings' already exists
Note (Code 1050): Table 'login_attempts' already exists
Warning (Code 1287): 'VALUES function' is deprecated and will be removed in a future release.
EXIT=0
```

Both scratch runs completed with exit code 0.

## Application to db_ujikom_test

Target confirmation:

```text
TARGET: db=db_ujikom_test port=3307 host=Laptop-Haedar.local
```

Migration output:

```text
Warning (Code 1681): Integer display width is deprecated and will be removed in a future release.
Warning (Code 1287): 'VALUES function' is deprecated and will be removed in a future release.
MIGRATION_EXIT=0
```

Post-migration target and counts:

```text
TARGET: db=db_ujikom_test port=3307
admin: 1
audit_logs: 0
berita: 0
booking_status_history: 2
buku_tamu: 0
cash_accounts: 1
daily_closings: 0
faq: 0
galeri: 0
kuota_harian: 0
laporan_keuangan: 0
ledger_entries: 5
login_attempts: 0
payment_proofs: 0
payments: 1
refunds: 0
reservasi: 0
reviews: 0
settings: 5
```

## G2 result

**PASS for schema preparation.** Required walkthrough tables now exist. No accounts or booking scenarios were seeded. G3 remains closed.

Warnings remain:
- MySQL 9.4 deprecates integer display width.
- MySQL 9.4 deprecates `VALUES()` in duplicate-key updates.

These warnings did not cause migration failure. A later cleanup can replace them in a new migration; original migrations remain unchanged.
