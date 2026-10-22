# G2 Migration Attempt
**Date:** 2026-10-22  
**Target:** `db_ujikom_test` on `127.0.0.1:3307`  
**Gate:** G2 approved. G3 remains closed.

## Target confirmation

```text
TARGET: db=db_ujikom_test port=3307 host=Laptop-Haedar.local
```

## Preflight schema finding

```text
=== admin ===
id int
username varchar(50)
email varchar(100)
password varchar(255)
role enum('Super Admin','Admin','Finance','Gate','Akuntan')
status enum('Aktif','Nonaktif')
permissions text
created_at timestamp
=== settings ===
setting_key varchar(50)
setting_value text
label varchar(100)
kategori varchar(50)
created_at timestamp
PHP Fatal error: Uncaught mysqli_sql_exception: Table 'db_ujikom_test.reservasi' doesn't exist
```

`db_ujikom_test` had no `reservasi` table before this attempt.

## Applied sequence

Temporary target-adjusted copies were created outside the repository. Original SQL files were not modified.

### 01 `db.sql` base

```text
db | port
db_ujikom_test | 3307
Note (Code 1050): Table 'admin' already exists
Note (Code 1050): Table 'settings' already exists
EXIT=0
```

### 02 `migration_v2.sql`

```text
db | port
db_ujikom_test | 3307
ERROR 1060 (42S21) at line 8: Duplicate column name 'bukti_bayar'
EXIT=1
```

## Stop condition

Stopped immediately after `migration_v2.sql` failure. No v3 foundation, state-machine, cash-account, or delta migration ran. No accounts or booking fixtures were seeded. G3 remains closed.

The G1 dump remains available outside the repository:
`/Users/haedarwezzer/waterboom-dumps/db_ujikom_test-g1.sql`

## Required next decision

The current `db_ujikom_test` may now contain partial changes from `db.sql`. Do not continue blindly. Owner must approve restoring the pre-G2 dump before a corrected migration attempt, or approve a read-only schema assessment followed by a new corrected sequence.
