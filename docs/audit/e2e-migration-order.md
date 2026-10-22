# E2E Migration Order
**Status:** planning only; G1, G2, G3 CLOSED. No database command run.

## Required order

1. `sql/db.sql`
   - **Expected idempotent:** NO / UNVERIFIED.
   - `CREATE DATABASE IF NOT EXISTS` and `CREATE TABLE IF NOT EXISTS` are guarded.
   - Settings `INSERT` at lines 51–62 is not guarded and can duplicate or fail on rerun.
   - Must not run directly until scratch rehearsal reviews its database-selection behavior.

2. `sql/migration_v2.sql`
   - **Expected idempotent:** NO / UNVERIFIED.
   - `ALTER TABLE reservasi ADD COLUMN` at lines 8–11 has no `IF NOT EXISTS`.
   - Table creates at lines 14, 22, 32, 42 use `IF NOT EXISTS`.
   - Settings insert at lines 52–58 is guarded by `ON DUPLICATE KEY UPDATE`.
   - FAQ/news inserts at lines 61–71 are not guarded and can duplicate on rerun.

3. `sql/migration_v3_foundation.sql`
   - **Expected idempotent:** NO / UNVERIFIED.
   - Table creates at lines 20, 35, 73, 90, 120, 138, 155, 192, 213 use `IF NOT EXISTS`.
   - Index alters at lines 50–57 use `ADD INDEX IF NOT EXISTS`.
   - Column alters at lines 60–62 use `ADD COLUMN IF NOT EXISTS`.
   - Role alter at lines 65–67 is unguarded and may fail or change an existing schema.
   - Settings insert at lines 237–242 is guarded by `ON DUPLICATE KEY UPDATE`.
   - Payment backfill at lines 251–295 and proof backfill at lines 299–315 use `NOT EXISTS`, but depend on earlier schema and are not proven idempotent until rehearsal.

4. `sql/migration_v3_state_machine.sql`
   - **Expected idempotent:** NO / UNVERIFIED.
   - File header claims idempotent, but `ALTER TABLE ... MODIFY COLUMN` at lines 20–21 and 34–46 has no state guard.
   - Data `UPDATE reservasi` at lines 23–32 is not conditional on migration state and must be tested for rerun behavior.
   - Index add at lines 48–49 uses `ADD INDEX IF NOT EXISTS`.

5. `sql/migration_v3_cash_account.sql`
   - **Expected idempotent:** YES by source review, but UNVERIFIED by execution.
   - Account seed at lines 10–12 uses `ON DUPLICATE KEY UPDATE`.
   - Ledger backfill at lines 16–48 uses `NOT EXISTS` for existing payment ledger rows.

6. `sql/migration_v3_e2e_delta.sql`
   - **Expected idempotent:** YES by source review, but UNVERIFIED by execution.
   - `CREATE TABLE IF NOT EXISTS login_attempts` is guarded.
   - No `USE` statement; caller must select target database explicitly.

## Required rehearsal gate

G1 must authorize scratch rehearsal before G2. Rehearsal must run the source files against a scratch `_test` database, then rerun them, and record every error. Do not patch original migrations. If rerun fails, create a new delta migration only after owner review.

## Gate status

- G1: CLOSED — exact owner message `APPROVE G1` required.
- G2: CLOSED — exact owner message `APPROVE G2` required after G1 review.
- G3: CLOSED — exact owner message `APPROVE G3` required after G2 review.
