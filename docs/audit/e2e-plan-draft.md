# E2E Walkthrough Readiness Plan (Draft)
**Date:** 2026-10-22  
**Status:** DRAFT — no gates open; no DB writes performed.

---

## 1. Classification of Missing Tables (from Phase 1 & Step 2)

### A. WALKTHROUGH-BLOCKING (3 tables)
Needed for the payment → check-in → refund flow the owner will test manually.

| Table | Used by (evidence) | Migration coverage |
|-------|-------------------|-------------------|
| `reservasi` | admin/index.php, admin/reservasi.php, admin/includes/header.php, admin/includes/sidebar.php, admin/scanner.php, app/Models/Reservasi.php, app/Services/BookingStateService.php | **COVERED** — db.sql CREATE + ALTERs in v2, v3_foundation, v3_state_machine |
| `kuota_harian` | app/Models/Reservasi.php (create, lock, quota check), app/Services/BookingStateService.php (quota release) | **COVERED** — db.sql CREATE + migration_v2.sql CREATE IF NOT EXISTS |
| `refunds` | app/Services/BookingStateService.php:240 (DIREFUND flow) | **COVERED** — migration_v3_foundation.sql CREATE IF NOT EXISTS |

### B. CONTENT / NON-BLOCKING (5 tables)
Only needed for public/admin content pages (news, gallery, FAQ, guestbook, reviews). The walkthrough does not require these to complete the payment/check-in flow.

| Table | Used by (evidence) | Migration coverage |
|-------|-------------------|-------------------|
| `reviews` | admin/index.php, admin/reviews.php, admin/handlers/review_handler.php | db.sql (check) |
| `buku_tamu` | admin/index.php, admin/edit.php, admin/handlers/export.php, admin/handlers/simpan.php, admin/handlers/hapus.php | db.sql (check) |
| `berita` | admin/index.php, admin/berita.php, admin/handlers/berita_handler.php | db.sql (check) |
| `galeri` | admin/galeri.php, admin/handlers/galeri_handler.php, app/Models/Gallery.php | db.sql (check) |
| `faq` | admin/faq.php, admin/handlers/faq_handler.php, app/Models/Faq.php | db.sql (check) |

> **Note:** These tables likely exist in `db.sql` but have not been applied to db_ujikom_test. They are not on the walkthrough blocking path.

### C. FINANCE REPORT (1 table)
| Table | Used by (evidence) | Migration coverage |
|-------|-------------------|-------------------|
| `laporan_keuangan` | admin/audit_keuangan.php, admin/handlers/keuangan_handler.php | migration_v3_foundation.sql (CREATE TABLE IF NOT EXISTS) |

---

## 2. Open Question: `login_attempts` Table

**Step 1 finding:** `login_attempts` is used in `admin/handlers/cek_login.php`:
- Line 22: `SELECT COUNT(*) FROM login_attempts ...`
- Line 72: `DELETE FROM login_attempts ...`
- Line 101: `INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)`

**No `CREATE TABLE login_attempts` exists in any .php file or in `sql/`.**

**Created at runtime: NO.**

**Risk:** Login will fail on any database without this table. The table must be created before the walkthrough can reach admin pages.

**Proposed fix (requires G2):** Add a small delta migration or include in the foundation migration. Decision pending owner approval.

---

## 3. Gates and What They Allow

| Gate | Trigger | Allows |
|------|---------|--------|
| **G1** | Owner writes `APPROVE G1` | **Phase 3 (Scratch rehearsal):** Create `db_ujikom_scratch_test` on port 3307, dump current `db_ujikom_test`, restore to scratch, apply migration files in order (foundation → state_machine → cash_account), verify idempotency and row counts. **Phase 4 (Dump):** mysqldump of real `db_ujikom_test` to `~/waterboom-dumps/` before any write. |
| **G2** | Owner writes `APPROVE G2` (requires G1 completed) | **Phase 5 (Apply to test DB):** Apply the verified migrations to `db_ujikom_test` in the same order. Create `login_attempts` table if not covered. |
| **G3** | Owner writes `APPROVE G3` (requires G2 completed) | **Phase 6 (Seed accounts & bookings):** Create 3 admin accounts (front_office, finance, gate) with password hashes; insert 4 booking scenarios (MENUNGGU_BAYAR, MENUNGGU_VERIFIKASI, LUNAS, DIBATALKAN) with correct invariants. |

> **Current state:** G1= CLOSED, G2= CLOSED, G3= CLOSED.  
> **No DB writes until the respective gate is explicitly opened by the owner.**

---

## 4. Scratch Rehearsal Must Confirm Idempotency (Note)

Before G2 is considered, the scratch rehearsal (Phase 3, requires G1) must verify:

1. Each migration file runs without error on a copy of the current test DB.
2. Re-running the same file a second time produces no error (idempotent: `IF NOT EXISTS`, or guarded INSERTs).
3. After all three migrations: all 12+ required tables exist, row counts match expectations, foreign keys are intact.

If any file is NOT idempotent and fails on existing tables, a **new delta migration** (`sql/migration_v3_e2e_delta.sql`) will be written with only `IF NOT EXISTS` additions. The original migration files will NOT be edited.

---

## 5. No Commands That Write to Any DB

This plan contains **zero commands that write to any database**. All steps above are read-only analysis.

---

## 6. Questions for the Owner (answer in writing)

1. **Approve G1 (scratch rehearsal + dump)?** — Reply `APPROVE G1` to enable Phases 3–4.
2. **Approve G2 (apply migrations to db_ujikom_test)?** — Reply `APPROVE G2` after G1 report is reviewed. Includes creating `login_attempts` table.
3. **Approve G3 (seed 3 accounts + 4 bookings)?** — Reply `APPROVE G3` after G2 report is reviewed.
4. **Content tables (reviews, buku_tamu, berita, galeri, faq) — seed with dummy data?** Not required for the payment/check-in walkthrough but may be desired for a full demo. If yes, specify which ones and how many rows each.
5. **Finance legacy table `laporan_keuangan` — seed or leave empty?** Not on the blocking path; only affects the legacy audit page.

---

## 7. Login Rate-Limit Column Contract and Delta Migration

`admin/handlers/cek_login.php` uses `login_attempts` as follows:

| Column | Code reference | SQL use | Required type |
|---|---|---|---|
| `ip_address` | :17, :22, :72, :101 | SELECT predicate, DELETE predicate, INSERT | `VARCHAR(45) NOT NULL` — supports IPv4 and IPv6 text |
| `username` | :18, :22, :72, :101 | SELECT predicate, DELETE predicate, INSERT | `VARCHAR(50) NOT NULL` — matches `admin.username` in `sql/db.sql:18` |
| `attempt_time` | :19, :22 | compared to a PHP `Y-m-d H:i:s` cutoff | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |

**Assumption:** `attempt_time` is `DATETIME`, not `TIMESTAMP`, because code compares it with a PHP-formatted local date-time string and does not require automatic update behavior.

Created files, not executed:
- `sql/migration_v3_e2e_delta.sql` — `CREATE TABLE IF NOT EXISTS login_attempts` with indexes `(ip_address, attempt_time)` and `(username, attempt_time)`.
- `sql/rollback_v3_e2e_delta.sql` — `DROP TABLE IF EXISTS login_attempts`; warning: deletes rate-limit history and must not run on a database with production login data.

---

## 8. Walkthrough Table Column Coverage

| Table | Referenced columns verified in CREATE/ALTER chain | Unmigrated referenced columns |
|---|---|---|
| `reservasi` | `id`, `kode_booking`, `nama`, `no_hp`, `email`, `tanggal_kunjungan`, `jenis_tiket`, `jumlah_dewasa`, `jumlah_anak`, `total_harga`, `metode_bayar`, `bukti_bayar`, `status`, `qr_token`, `catatan`, `catatan_tolak`, `created_at` from `sql/db.sql:65-83`; `is_checkin`, `waktu_checkin` from `sql/migration_v3_foundation.sql:60-62` | **UNVERIFIED** — `admin/reservasi.php:42,49` injects dynamic `$where`; no statically extracted column is missing |
| `kuota_harian` | `id`, `tanggal`, `kuota_max`, `terisi` from `sql/db.sql:86-92` and `sql/migration_v2.sql:14-19` | **OK** — no missing or dropped column found |
| `refunds` | `id`, `booking_id`, `payment_id`, `amount`, `reason`, `status`, `requested_by`, `approved_by`, `approved_at`, `created_at` from `sql/migration_v3_foundation.sql:192-207` | **OK** — no missing or dropped column found |

Migration ordering review: `docs/audit/e2e-migration-order.md`.

**Content tables and `laporan_keuangan` are NOT COVERED by this walkthrough.**

**Gate status update:** G1 is open for scratch rehearsal. G2=CLOSED and G3=CLOSED. No migration was applied to `db_ujikom_test`.

G1 rehearsal evidence: `docs/audit/e2e-g1-scratch-rehearsal.md`.

G1 blockers found:
- `sql/db.sql` rerun fails on duplicate `settings.kuota_default`.
- `sql/migration_v3_foundation.sql` fails at line 50 because MySQL 9.4 rejects `ADD INDEX IF NOT EXISTS`.
- `sql/migration_v3_state_machine.sql` fails at line 48 for the same syntax.
- `sql/migration_v3_cash_account.sql` then fails because `cash_accounts` was not created.
- `sql/migration_v3_e2e_delta.sql` succeeds on scratch, but was not rerun for idempotency.

G2 compatibility result: `sql/migration_v3_e2e_mysql94_compat.sql` passed scratch rehearsal twice with exit 0 and was applied to `db_ujikom_test` on port 3307 with exit 0. Evidence: `docs/audit/e2e-g2-compatibility.md`. G3 remains CLOSED. Content tables and `laporan_keuangan` remain NOT COVERED by this walkthrough.

---

## 9. Known minor issues (not fixed)

- `app/Services/BookingStateService.php:234` — trailing whitespace.
- `css/admin_style.css:33` — trailing whitespace.
- `css/admin_style.css:62` — trailing whitespace.
- `sql/db.sql:27` — trailing whitespace in comment.
- `sql/db.sql:27-29` — comment says default admin rows were removed, and no admin INSERT appears in this file. Confirm before seeding that no other source creates an unwanted account. The comment cites PRD section 14, but PRD v4.0 has no section 14.
- `sql/db.sql:79` — `qr_token` stores a plain token value. PRD v4 TKT-1 requires a hash. This is a PRD difference, not a walkthrough blocker.
- `admin/reservasi.php:42,49` — dynamic `$where` SQL prevents complete static column-contract verification; `reservasi` remains UNVERIFIED.

**Walkthrough scope:** Content tables and `laporan_keuangan` are NOT COVERED by this walkthrough.