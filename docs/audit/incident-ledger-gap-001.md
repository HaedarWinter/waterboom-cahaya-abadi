# Incident: Ledger Entry Sequence Gap
**ID:** incident-ledger-gap-001  
**Date investigated:** 2026-10-22T04:11:32Z  
**Investigator:** opencode (forensic mode)  
**Database:** db_ujikom (port 3306)  
**Severity:** Medium (integrity concern, not yet confirmed loss)
**Status:** OPEN — NOT CLOSED. No KOREKSI entry created. Bank statement check pending.

---

## Owner Directives (2026-10-22T05:00:00Z)

1. **Keep OPEN.** Do not close. Do not create KOREKSI yet.
2. **Bank statement check 22 October 2026, 08:00–09:30 WIB.** This is the only way to answer whether an unrecorded transfer arrived. Finance must match against the venue's bank account and record the result here.
3. **Front office and customer outreach.** Check whether anyone reported failed, stuck, or unconfirmed payments in that window. Customers who fail to pay usually contact via WhatsApp; check chat history.
4. **Document cleanup.** Remaining "rolled back" lines marked `[SUPERSEDED]`. The word "confirmed" is used only for facts that are actually proven, not for causes.
5. **No KOREKSI for id=6 before the bank check.** A KOREKSI entry without money behind it creates a false record that is hard to clean up. If "no gaps" is still required formally, wait until the bank result is clear.
6. **Enable binary logging going forward, but not as a solution to this incident.** Binlog cannot prove an event that has already passed. Without it, future similar incidents cannot be investigated. This is a server configuration change requiring approval and scheduling outside operational hours.
7. **Add rollback logging to BookingStateService as a separate code change.** This provides direct evidence when a rollback occurs again. Do after the bank check, not before, so improvements are not mixed with the investigation.
8. **Schedule integrity_check.php regularly, but only after that script is safe to run.** The script is not yet guarded and it is not yet clear whether it is safe against the dev DB. Add the guard first.

---

## Executive Summary

A gap in the `ledger_entries` table entry_no sequence was discovered during read-only audit. The numeric ID sequence on 2026-10-22 is: 5, 7, 8, 9. ID 6 is missing. This indicates either a transaction rollback or a data deletion event. No write operation was performed during this investigation.

---

## STEP 1: Code Generation Logic (Read-Only Analysis)

**File:** `app/Services/BookingStateService.php` lines 215, 265

**Payment entry generation (TIKET category, line 215):**
```php
$entryNo = 'LED-' . date('Ymd') . '-' . str_pad($paymentId, 6, '0', STR_PAD_LEFT);
```

**Refund entry generation (REFUND category, line 265):**
```php
$entryNo = 'LED-REF-' . date('Ymd') . '-' . str_pad($refundId, 6, '0', STR_PAD_LEFT);
```

**Observed entries (2026-10-22):**
- LED-20261022-000005 (id=5, posted_at 08:49:05)
- LED-20261022-000007 (id=7, posted_at 08:54:41)
- LED-20261022-000008 (id=8, posted_at 09:03:08)
- LED-20261022-000009 (id=9, posted_at 09:07:33)

**Logic classification:**
- a) **Not sequence-based.** entry_no is derived from the payment or refund primary key (`$paymentId` or `$refundId`), obtained from `$conn->insert_id` immediately after INSERT.
- b) **Inside implicit transaction.** Payment/refund INSERT → get insert_id → build entry_no → ledger INSERT all executed on same connection with no explicit ROLLBACK or COMMIT visible in the code path.
- c) **Concurrent gap possible.** If a payment INSERT succeeds (id=6), but the ledger INSERT fails or the transaction is rolled back, payment id=6 will be consumed but no ledger row created.

**Classification:** ROLLBACK GAP suspected (transaction committed payment but rolled back or failed ledger insert, leaving payment id=6 orphaned).

---

## STEP 2: Binary Logs (Read-Only Inspection)

**Query:**
```sql
SHOW VARIABLES LIKE 'log_bin';
SHOW VARIABLES LIKE 'binlog_format';
SHOW BINARY LOGS;
```

**Output:**
```text
log_bin | OFF
binlog_format | MIXED
(SHOW BINARY LOGS error: You are not using binary logging)
```

**Conclusion:** Binary logging is disabled. No transaction history available for forensic analysis.

---

## STEP 3: Backups (Read-Only Inspection)

**Search locations checked:**
- `/Users/haedarwezzer/mysql_test_data/` (test server datadir)
- `/tmp`, `/var/backups`
- Filesystem search for `.sql`, `.sql.gz`, `.bak`, `.dump`, `.tar` files

**Result:** No backup files found outside the repository with timestamps between 2026-10-22 00:00:00 and current time.

**Conclusion:** No baseline snapshot available to compare ledger state before and after the gap.

---

## STEP 4: Row-Level Evidence in db_ujikom

**Query:**
```sql
SELECT id, entry_no, posted_at FROM ledger_entries ORDER BY id;
SELECT AUTO_INCREMENT FROM INFORMATION_SCHEMA.TABLES 
  WHERE TABLE_SCHEMA='db_ujikom' AND TABLE_NAME='ledger_entries';
```

**Output:**
```text
ids: 5,7,8,9
auto_increment: 10
max(id)+1: 10
id=5 entry_no=LED-20261022-000005 posted_at=2026-10-22 08:49:05
id=7 entry_no=LED-20261022-000007 posted_at=2026-10-22 08:54:41
id=8 entry_no=LED-20261022-000008 posted_at=2026-10-22 09:03:08
id=9 entry_no=LED-20261022-000009 posted_at=2026-10-22 09:07:33
```

**Interpretation:**
- **ID gap confirmed:** 5 → 7 (id=6 missing). `[SUPERSEDED]` applies only to later causal conclusions; this remains a confirmed database fact.
- **AUTO_INCREMENT = 10:** Next insert will use id=10, confirming 6 and one more (9 is last existing, so id should be 10).
- **No entry_no for id=6:** If a ledger row with id=6 ever existed, its entry_no would be `LED-20261022-000006` (format: LED-YYYYMMDD-NNNNNN where NNNNNN = payment id padded). No such entry exists.
- **No corresponding payment found:** Query in prior audit (Step C3) confirmed zero rows in payments table with booking linked to `INV%` kode_booking.

**Conclusion:** ID 6 was never saved to ledger_entries. Either:
1. Payment or refund insert_id reached 6, but the ledger insert failed or rolled back.
2. Row with id=6 was deleted after creation (violates append-only rule per PRD §5.3).
3. Database replication lag or partial transaction commit (unlikely without binlog evidence).

---

## Classification & Confidence (historical — superseded by Follow-Up section)

| Factor | Evidence | Confidence |
|--------|----------|------------|
| Sequence gap exists | id=5, 7, 8, 9 (no 6) | **High** (verified via SELECT) |
| Likely cause: rollback | Payment id=6 consumed but no ledger row | **HYPOTHESIS** (code logic supports, no binlog proof) |
| Likely cause: DELETE | Ledger row deleted after creation | **HYPOTHESIS** (no audit log, append-only violation not observed elsewhere) |
| Impact: data loss | Unknown; depends on whether payment id=6 was ever created | **Unknown** (no payments table audit done; prior test showed 0 INV% payments) |
| Repairable: KOREKSI | PRD §5.3 allows reversal entry | **Yes** (if payment row found) |

---

## What Remains Unproven (historical — superseded by Follow-Up section)

1. **Was payment id=6 ever created?** Requires SELECT from payments table with `WHERE id = 6` (not done in this investigation due to scope). If no row exists, id=6 was allocated but payment insert rolled back. If row exists, ledger insert failed.
2. **Why did ledger insert fail?** Without binlog, cannot see error. Possible causes: cash_accounts subquery failed, constraint violation, network timeout, explicit ROLLBACK, or DB crash.
3. **When did the gap occur?** No timestamp evidence. Inferred from other entries on 2026-10-22, suggesting same-day incident.
4. **Are other dates affected?** Only 2026-10-22 ledger rows checked. Earlier dates not audited.

---

## Proof Requirements (for owner to approve next steps)

To confirm root cause, one of:
- **Option A:** Execute `SELECT * FROM payments WHERE id = 6` and report result. If row exists, ledger insert failed (needs investigation). If no row exists, transaction rolled back (less critical, app is self-protecting).
- **Option B:** Enable binary logging and reproduce the sequence (test environment only), capture transaction events.
- **Option C:** Restore from backup taken before incident (none available currently).

---

## Recommended Next Action (Decision Left to Owner) — HISTORICAL, SEE FOLLOW-UP

### If payment id=6 row exists:
1. Investigate why ledger insert failed (check app logs if available).
2. Verify payment was not verified and charged to customer (if so, customer is owed a refund).
3. Create a KOREKSI reversal entry per PRD §5.3 to restore audit trail (owner approval required before execution).

### If payment id=6 row does NOT exist:
1. Transaction rolled back correctly; gap has no ledger impact (payment was never confirmed).
2. No action needed for ledger integrity (app behaved correctly per code path).

### If cannot determine:
1. Enable binary logging on db_ujikom going forward for forensic capability.
2. Implement transaction logging in PHP service layer to capture rollback events.
3. Schedule regular `integrity_check.php` runs to detect future gaps early.

---

## Appendix: Commands Run

**C2 binlog check:**
```bash
php -r '$c = new mysqli(...); $r = $c->query("SHOW VARIABLES LIKE \"log_bin\"");'
# Result: OFF
```

**C3 backup search:**
```bash
find / -maxdepth 4 -type f \( -name "*.sql" -o -name "*.sql.gz" \) 2>/dev/null | ...
# Result: No backups found
```

**C4 ledger row check:**
```bash
php check_ledger.php
# Result: ids 5,7,8,9; auto_increment 10
```

---

## Compliance Notes

- **AGENTS.md §2.3:** `ledger_entries` append-only rule. No UPDATE/DELETE observed in code; gap suggests transaction issue, not policy violation.
- **PRD §5.3:** `entry_no` must be sequential per year without gaps. Gap is a deviation.
- **PRD §5.6 AUD-1:** Activity log not implemented in db_ujikom yet (table missing). Cannot trace who/when for this incident.

---

**Status:** Investigation complete. No fixes applied. Awaiting owner decision on next steps.

---

## FOLLOW-UP (2026-10-22T04:28:00Z)

### Step 1: Payment id=6 Existence (SELECT only)

**Queries run (all on confirmed db_ujikom / 3306):**
```sql
SELECT * FROM payments WHERE id = 6;
SELECT id, booking_id, status, amount, amount_received, method, created_at, verified_at, verified_by, provider_ref FROM payments ORDER BY id;
SELECT id, booking_id, status, amount, amount_received, method, created_at, verified_at, verified_by, provider_ref FROM payments WHERE id IN (5,7,8,9);
SELECT p.id AS payment_id, p.status, p.amount, p.amount_received, r.kode_booking, r.total_harga, r.status AS booking_status, r.created_at AS booking_created FROM payments p LEFT JOIN reservasi r ON r.id = p.booking_id WHERE p.id IN (5,7,8,9) ORDER BY p.id;
SELECT id, kode_booking, status, total_harga, created_at FROM reservasi WHERE id BETWEEN 1 AND 12 ORDER BY id;
```

**Output:**
```
=== S1-1 PAYMENTS WHERE id=6 ===
SQL: SELECT * FROM payments WHERE id = 6
id | booking_id | attempt_no | method | provider_ref | amount | unique_code | amount_received | transfer_at | destination_account_id | status | verified_by | verified_at | rejection_reason | created_at
(rows: 0)

=== S1-2 ALL PAYMENTS ===
SQL: SELECT id, booking_id, status, amount, amount_received, method, created_at, verified_at, verified_by, provider_ref FROM payments ORDER BY id
id | booking_id | status | amount | amount_received | method | created_at | verified_at | verified_by | provider_ref
1 | 37 | BELUM_ADA_BUKTI | 60000 | NULL | TRANSFER_BANK | 2026-10-21 21:12:29 | NULL | NULL | NULL
5 | 83 | TERVERIFIKASI | 40000 | 40000 | TRANSFER_BANK | 2026-10-22 08:49:05 | 2026-10-22 08:49:05 | NULL | NULL
7 | 96 | TERVERIFIKASI | 40000 | 40000 | TRANSFER_BANK | 2026-10-22 08:54:41 | 2026-10-22 08:54:41 | NULL | NULL
8 | 103 | TERVERIFIKASI | 40000 | 40000 | TRANSFER_BANK | 2026-10-22 09:03:08 | 2026-10-22 09:03:08 | NULL | NULL
9 | 110 | TERVERIFIKASI | 40000 | 40000 | TRANSFER_BANK | 2026-10-22 09:07:33 | 2026-10-22 09:07:33 | NULL | NULL
(rows: 5)

=== S1-3 PAYMENTS 5,7,8,9 ===
(payment rows with TERVERIFIKASI, amounts 40000, method TRANSFER_BANK)

=== S1-4 PAYMENTS 5,7,8,9 JOIN reservasi ===
payment_id | status | amount | amount_received | kode_booking | total_harga | booking_status | booking_created
5 | TERVERIFIKASI | 40000 | 40000 | NULL | NULL | NULL | NULL
7 | TERVERIFIKASI | 40000 | 40000 | NULL | NULL | NULL | NULL
8 | TERVERIFIKASI | 40000 | 40000 | NULL | NULL | NULL | NULL
9 | TERVERIFIKASI | 40000 | 40000 | NULL | NULL | NULL | NULL
(rows: 4)

=== S1-5 RESERVASI IDs AROUND 6 ===
id | kode_booking | status | total_harga | created_at
(rows: 0)
```

**Classification (Step 1): (A) payment id=6 never existed.**

**Evidence:**
- Zero rows for `WHERE id = 6`
- Payment IDs in table: 1, 5, 7, 8, 9 (no 6)
- All four TERVERIFIKASI payments have matching ledger rows
- Payment id=1 is BELUM_ADA_BUKTI (unverified) — no ledger expected
- No reservasi rows exist with IDs 1-12 (likely deleted or different numbering)

---

### Step 2: Ledger Trace for Payments 5, 7, 8, 9 (SELECT only)

**Queries run:**
```sql
SELECT id, source_type, source_id, direction, category, amount, posted_at FROM ledger_entries WHERE source_type = 'PAYMENT' ORDER BY source_id;
SELECT p.id AS payment_id, p.status, p.amount FROM payments p WHERE p.status = 'TERVERIFIKASI' AND NOT EXISTS (SELECT 1 FROM ledger_entries le WHERE le.source_type = 'PAYMENT' AND le.source_id = p.id) ORDER BY p.id;
SELECT p.id AS payment_id, p.status, (SELECT COUNT(*) FROM ledger_entries le WHERE le.source_type = 'PAYMENT' AND le.source_id = p.id) AS ledger_rows FROM payments p ORDER BY p.id;
SELECT id, entry_no, entry_date, source_type, source_id, direction, category, amount, posted_at FROM ledger_entries ORDER BY id;
SELECT le.id, le.entry_no, le.source_id, le.amount FROM ledger_entries le WHERE le.source_type = 'PAYMENT' AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.id = le.source_id) ORDER BY le.id;
SELECT * FROM daily_closings ORDER BY id;
SELECT * FROM cash_accounts ORDER BY id;
```

**Output:**
```
=== S2-1 LEDGER PAYMENT TRACE ===
id | source_type | source_id | direction | category | amount | posted_at
5 | PAYMENT | 5 | IN | TIKET | 40000 | 2026-10-22 08:49:05
7 | PAYMENT | 7 | IN | TIKET | 40000 | 2026-10-22 08:54:41
8 | PAYMENT | 8 | IN | TIKET | 40000 | 2026-10-22 09:03:08
9 | PAYMENT | 9 | IN | TIKET | 40000 | 2026-10-22 09:07:33
(rows: 4)

=== S2-2 TERVERIFIKASI PAYMENTS WITHOUT LEDGER ===
payment_id | status | amount
(rows: 0)

=== S2-3 LEDGER ROWS PER PAYMENT (all payments) ===
payment_id | status | ledger_rows
1 | BELUM_ADA_BUKTI | 0
5 | TERVERIFIKASI | 1
7 | TERVERIFIKASI | 1
8 | TERVERIFIKASI | 1
9 | TERVERIFIKASI | 1
(rows: 5)

=== S2-4 ALL LEDGER ROWS ===
id | entry_no | entry_date | source_type | source_id | direction | category | amount | posted_at
5 | LED-20261022-000005 | 2026-10-22 | PAYMENT | 5 | IN | TIKET | 40000 | 2026-10-22 08:49:05
7 | LED-20261022-000007 | 2026-10-22 | PAYMENT | 7 | IN | TIKET | 40000 | 2026-10-22 08:54:41
8 | LED-20261022-000008 | 2026-10-22 | PAYMENT | 8 | IN | TIKET | 40000 | 2026-10-22 09:03:08
9 | LED-20261022-000009 | 2026-10-22 | PAYMENT | 9 | IN | TIKET | 40000 | 2026-10-22 09:07:33
(rows: 4)

=== S2-5 LEDGER ORPHANS (no matching payment) ===
id | entry_no | source_id | amount
(rows: 0)

=== S2-6 DAILY CLOSINGS ===
(rows: 0)

=== S2-7 CASH ACCOUNTS ===
id | code | name | type | account_number | account_holder | is_active | created_at
1 | KAS_001 | Kas Utama | CASH | NULL | NULL | 1 | 2026-10-22 01:44:16
(rows: 1)
```

**Match Table (payment_id | status | ledger_rows | match):**
| payment_id | status | ledger_rows | match |
|------------|--------|-------------|-------|
| 1 | BELUM_ADA_BUKTI | 0 | YES (unverified, no ledger expected) |
| 5 | TERVERIFIKASI | 1 | YES |
| 7 | TERVERIFIKASI | 1 | YES |
| 8 | TERVERIFIKASI | 1 | YES |
| 9 | TERVERIFIKASI | 1 | YES |

**No mismatches found.** Every TERVERIFIKASI payment has exactly one ledger IN/TIKET row.

---

### Step 3: Logs (read-only)

**Commands run:**
```bash
ls -la storage/logs/ 2>&1
grep -n "2026-10-22 0[89]:" storage/logs/app.log 2>&1
ls -la /Applications/XAMPP/xamppfiles/logs/
grep -n "2026-10-22 0[89]:" /Applications/XAMPP/xamppfiles/logs/*.log 2>&1
```

**Output:**
```
storage/logs/app.log: No such file or directory
/Applications/XAMPP/xamppfiles/logs/: access_log, error_log, php_error_log present
grep XAMPP logs: (no matches for 2026-10-22 08:xx or 09:xx)
```

**Conclusion:** Application log directory does not exist. XAMPP error/access/PHP logs contain no entries for the incident window (2026-10-22 08:00–09:30).

---

### Step 4: Transaction Shape in BookingStateService.php

**grep output (line numbers):**
```
114:                $db->rollback();
122:                $db->rollback();
127:                $db->rollback();
137:                    $db->rollback();
156:                    $db->rollback();
200:                        "UPDATE payments SET amount_received = ?, status = 'TERVERIFIKASI', verified_by = ?, verified_at = NOW(), provider_ref = ?, method = ?
217:                    "INSERT INTO ledger_entries (
218:                        entry_no, entry_date, cash_account_id, direction, category,
261:                    $db->query("UPDATE payments SET status = 'DIREFUND_PENUH' WHERE id = ?", [$paymentId], "i");
267:                    "INSERT INTO ledger_entries (
268:                        entry_no, entry_date, cash_account_id, direction, category,
295:            $db->commit();
303:            $db->rollback();
```

**Answers:**

a) **Are the ledger INSERT and the payments UPDATE in the same transaction?**  
**YES.** Line 109: `$db->begin();` opens a transaction. All operations (reservasi UPDATE, payments INSERT/UPDATE, ledger INSERT, booking_status_history INSERT) execute within that transaction. Commit at line 295; rollback at line 303 on any exception.

b) **Where is COMMIT, and where is ROLLBACK?**  
- COMMIT: line 295 (`$db->commit();`)
- ROLLBACK: lines 114, 122, 127, 137, 156 (early validation failures before payment/ledger work), line 303 (catch-all for any Throwable).

c) **If the ledger INSERT fails, is the payment still set to TERVERIFIKASI?**  
**NO (committed state).** If ledger INSERT (lines 216-230) throws, the catch block (line 302-305) executes `$db->rollback();` — the entire transaction rolls back. The payment row is undone. No committed TERVERIFIKASI payment without ledger can exist from this code path.

d) **Can a payment reach TERVERIFIKASI without a ledger insert? Show the path, or state that none exists.**  
**None exists in the committed state.** The atomic transaction guarantees either both payment and ledger commit, or neither does.

**However (theoretical edge case):** If a payment INSERT succeeds and obtains `insert_id=6`, but the ledger INSERT fails (constraint, timeout, crash), the transaction rolls back. The payment row is gone from the committed state, but the auto-increment counter has advanced to 7. This **ROLLBACK GAP** is consistent with:
- Payment IDs observed: 1, 5, 7, 8, 9 (id=6 missing)
- No payment id=6 row in committed state
- Ledger IDs: 5, 7, 8, 9 (no 6)

The atomic transaction structure shows that a *committed* TERVERIFIKASI payment cannot exist without its ledger row through the application code path. That structural fact does **not** identify which of H1, H2, or H3 produced the observed id gap, and it does not cover activity outside the application.

---

### Updated Classification

| Factor | Evidence | Confidence |
|--------|----------|------------|
| Sequence gap exists | ledger IDs 5, 7, 8, 9 (no 6) | **High** |
| Payment id=6 existed? | **No row in committed state** | **High** |
| Gap cause: rollback | Consistent with the id gap; no binlog proof | **HYPOTHESIS** (not a finding) |
| Gap cause: DELETE | Consistent with the id gap; not excluded by current evidence | **HYPOTHESIS** (not a finding) |
| Impact (B case) | **Not observed** — no TERVERIFIKASI payment without ledger in current data | **High** (limited to current DB state) |
| Impact (A case) | Payment id=6 absent from current database | **High** (limited to current DB state) |
| Repairable: KOREKSI | Not decided; see "Open items" | **N/A** |

**Status: OPEN.** No evidence that money was received without a ledger entry for any existing payment. Payment id=6 is absent from the current database. Cause not proven (rollback, cancellation before transaction, or deletion). External verification with bank statements is required before closure.

### Hypotheses not yet excluded

- **H1: INSERT was rolled back** (consistent with the id gap).
- **H2: Request aborted before the transaction began** (no row ever written).
- **H3: Row was created and later deleted outside the application** (consistent with the id gap; not excluded by the current evidence).

---

### Not covered by this investigation

- Bank transfers received outside the system in the window 2026-10-22 08:00 to 09:30.
- Any customer reports of failed payment in that window.

---

### What Remains Unproven

1. **Exact cause of the gap** — H1, H2, and H3 remain possible; no logs or binlog evidence identify one.
2. **Whether other dates have similar gaps** — only 2026-10-22 audited.
3. **Whether any bank transfer was received outside the system** — bank statement check is pending.
4. **Whether a customer reported a failed or stuck payment** — front-office check is pending.

### Open items

| # | Item | Why it matters | Owner | Status |
|---|---|---|---|---|
| 1 | Bank statement check for 2026-10-22 08:00-09:30 | Tests whether money arrived without a ledger trace | finance | OPEN |
| 2 | Customer reports of failed payment in that window | Tests for an affected payment attempt not represented in current data | front office | OPEN |
| 3 | Explanation for auto-increment jumps 2, 3, 4, 6 in payments; check test-data history | Distinguishes historical test cleanup, rollback, cancellation, or deletion hypotheses | dev | OPEN |
| 4 | Decision on binary logging | Future transaction evidence requires binary logs | owner | OPEN |
| 5 | Decision on logging ROLLBACK reasons in BookingStateService | Captures future failure causes; requires separate code change | owner | OPEN |
| 6 | Decision on KOREKSI for id=6 | A KOREKSI entry without money behind it creates a false record; wait for bank check | owner | OPEN |

---

### Questions for Owner

1. Who will check bank statements for 2026-10-22 08:00-09:30, and when?
2. Has any customer reported a failed or stuck payment on that date?
3. Approve adding ROLLBACK logging to BookingStateService as a separate code change?
4. Approve enabling binary logging, and by whom?
