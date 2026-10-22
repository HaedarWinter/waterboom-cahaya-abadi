# G2 Restore Checkpoint
**Date:** 2026-10-22  
**Gate:** G2 restore approved. Migration application remains stopped pending corrected rehearsal.  
**Target:** `db_ujikom_test` on `127.0.0.1:3307`.

## Target and dump verification

```text
TARGET: db=db_ujikom_test port=3307 host=Laptop-Haedar.local
261a778eca326ff0e7c4fb7b42ef16ed6fb47c6e42e581500e643a59201a27f6  /Users/haedarwezzer/waterboom-dumps/db_ujikom_test-g1.sql
```

## Restore output

```text
DATABASE() @@port @@hostname
db_ujikom_test 3307 Laptop-Haedar.local
DATABASE() @@port @@hostname
db_ujikom_test 3307 Laptop-Haedar.local
```

## Post-restore verification

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

Counts match the pre-G2 G1 snapshot. Partial migration state was removed by restore. No migration, account seed, or booking seed ran after restore.

## Next gate

Migration application remains blocked until corrected migration SQL passes scratch rehearsal. G3 remains closed.
