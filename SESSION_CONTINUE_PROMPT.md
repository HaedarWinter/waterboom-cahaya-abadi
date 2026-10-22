# SESSION CONTINUATION PROMPT
**Project:** Waterboom Cahaya Abadi  
**Last Session:** Phase 7 complete, Phase 8 (Demo Mode) in progress  
**Date:** 2026-10-23  
**Stack:** PHP 8.x native, MySQL/MariaDB, SweetAlert2, Chart.js, Lucide  
**Env:** Test DB `db_ujikom_test` @ `127.0.0.1:3307` — **jangan sentuh `db_ujikom` port 3306**

---

## ✅ SELESAI 100%

### Phase 3: RBAC & Akses
- 28 route admin guarded dengan canonical permission (PRD §3.2)
- Middleware centralized: `config/routes_rbac.php`, `app/Http/RouteRegistry.php`, `app/Http/Middleware/Bootstrap.php`
- Default-deny: route tanpa mapping = 403
- Granular guards: 26 izin (payment.verify, refund.approve, ledger.view, dll.)

### Phase 4: Inti Booking
- `config/states.php` — 11 status + 19 transisi (PRD §4.2 table 4.2)
- `BookingStateService` refactor — config-driven, QR token hash (sha256)
- Migration `V4_003_booking_core.sql` + rollback (applied):
  - `reservasi`: `payment_deadline_at`, `channel` (ONLINE/COUNTER), `visit_date`
  - `booking_items` — snapshot harga per tiket
  - `ticket_types`, `ticket_prices` — harga efektif per tanggal
- `Reservasi::create()` — simpan deadline, channel, visit_date, insert snapshot ke `booking_items`
- `Reservasi::getByTokenHash()`, `getByKodeAndHpLast4()` — lookup aman
- `cek_booking.php` — form kode + 4 digit HP (TKT-5)
- Scanner handler — pakai hash lookup
- `TicketService::generate($id, $rawToken)` — QR pakai raw token
- Cron endpoint `/cron/expire.php?token=...` — idempoten, token-based (FLOW-4)

### Phase 5: Pembayaran & Keuangan (unblocked parts)
- **V4_004_expenses.sql**: `expenses` table (DRAFT/POSTED/VOIDED), backfill dari `laporan_keuangan`
- **V4_005_daily_closings.sql**: `daily_closings` + `is_locked` pada ledger_entries
- Domain: `ExpenseService`, `ClosingService`, `ReportService`, `LedgerService`, `ActivityLogger`
- Controllers: `ExpenseController`, `ClosingController`, `ReportController`
- Views: `admin/expenses.php`, `admin/closings.php`, `admin/reports.php`
- Routes + RBAC di `config/routes_rbac.php`
- Reconciliation.php 500 error fixed (SQL prepare issue)

### Phase 6: Frontend Design System (COMPLETE)
- Token system: Colors (light/dark), spacing, typography, effects, z-index, radius
- 8 CSS layers: tokens → base → layout → atoms → molecules → organisms → pages → utilities
- Atoms: button (5 variants), input (sizes, states), badge (status sync), avatar, icon
- Molecules: card (bento), form-field (checkbox/radio), modal (sizes), table-row
- Organisms: header, sidebar (collapsible), data-table (sortable), pagination
- Pages: admin layout, site layout (hero, bento, gallery, reviews, FAQ, stepper)
- Bundle: `scripts/build_assets.php` → `public/assets/dist/css/app.min.css` (53KB)
- Integration: Semua 14 admin pages + site header load `app.min.css`

### Phase 7: Notifikasi & Tiket (COMPLETE)
- **V4_006_notifications.sql**: `notification_outbox` (retry queue, idempotent), `wa_templates` (5 seeded)
- Domain: `OutboxRepository/Service`, `WhatsAppClient` (log/Fonnte driver), `TemplateRepository/Service`, `TicketService` (signed URL + PDF on-demand)
- Controllers: `NotificationController`, `TemplateController`
- Views: `admin/notifications`, `admin/templates` (index + edit with live preview)
- Routes: notifications.php, templates.php → settings.manage
- Endpoint: `ticket.php?url=...` (HMAC signed URL, 24hr expiry)

---

## 🔄 IN PROGRESS: Phase 8 (Demo Mode) — 70% done

### ✅ Done
- Roles seeded (6): super_admin, manager, front_office, finance, gate, content
- Permissions seeded (36 canonical keys from PRD §3.2)
- Demo accounts seeded (6 users, password: demo123):
  - `demo_owner` → super_admin
  - `demo_mgr` → manager
  - `demo_fo` → front_office
  - `demo_finance` → finance
  - `demo_gate` → gate
  - `demo_content` → content
- Demo data seeder (WIP): booking_items, payments, ledger, daily_closings

### ❌ Remaining (need to finish)
| Task | File | Status |
|------|------|--------|
| Fix demo data integrity (payment IDs ↔ ledger) | `database/seeders/demo_data_seeder.sql` | In progress |
| `reset_demo.php` script (truncate + re-seed + log) | `scripts/reset_demo.php` | Pending |
| APP_ENV=demo check + banner di layout | `resources/views/layouts/admin.php` | Pending |
| Simulated payment button + dummy proof generator | `reservasi.php` / `upload_bukti.php` | Pending |
| Auto-reset endpoint (lazy) + UI reset button | `internal/reset_demo.php` | Pending |
| Full flow test: booking → sim payment → verify → check-in → close | Manual test | Pending |

---

## 🔴 BLOCKER: OWNER DECISIONS NEEDED (PRD §14)

| # | Pertanyaan | Bloker |
|---|-----------|--------|
| 14.1 | Batas waktu bayar default: 30 menit / 2 jam / 24 jam? | `payment_deadline_at` logic |
| 14.2 | Kebijakan refund & reschedule (syarat, potongan, batas hari)? | Refund flow, ledger OUT, kuota release |
| 14.3 | Ada penjualan tunai di loket (COUNTER) ikut sistem? | `channel` logic |
| 14.4 | **Kode unik**: dicatat pemasukan terpisah, dikembalikan refund, atau tidak dipakai? | `booking_items`, ledger IN/KODE_UNIK |
| 14.5 | Berapa peran dipakai nyata? 6 peran atau digabung? | Seed V4 roles, UI peran |
| 14.6 | Batas nominal koreksi/pengeluaran butuh approval super_admin? | `ledger.entry_manual` guard |
| 14.7 | **Pajak**: jenis, tarif, harga sudah termasuk pajak? *(konfirmasi akuntan)* | Laporan keuangan, expense, tax setting |
| 14.8 | Retensi data keuangan & log (berapa tahun)? | `activity_logs`, backup policy |
| 14.9 | Demo publik boleh nama venue asli atau generik/dummy? | Mode demo seeder |
| 14.10 | Rencana QRIS dinamis / payment gateway? | Payment infrastructure |

---

## 🎯 IMMEDIATE NEXT ACTIONS (Current Session)

1. **Fix demo data integrity** — align payment IDs with ledger source_id
2. **Build `scripts/reset_demo.php`** — one-command reset
3. **Add demo banner to admin layout** — show "MODE DEMO" badge
4. **Add simulated payment button** to booking status page
5. **Create auto-reset endpoint** + UI button for super_admin
6. **Test full demo flow** end-to-end
7. **Run integrity_check + all tests** → green

---

## 🛠️ SKILLS UNTUK SESI BERIKUTNYA

```bash
# Load skills di awal sesi
/caveman          # Terse mode: jawab dulu, reasoning kedua
/ponytail full    # Lazy senior: smallest complete change, stdlib first
```

**Aturan kerja (AGENTS.md):**
- Tulis tes gagal dulu → implement minimal → jalankan tes → refactor kecil
- Satu PR = satu tema < 400 baris diff
- Prepared statement wajib, output escape (`e()`)
- Business logic di service, bukan controller/view/DB
- Ledger append-only, tidak pernah UPDATE/DELETE

---

## 🔧 KONTEKS TEKNIS

**Akun test (password: demo123):**
- `demo_owner` → role `super_admin`
- `demo_finance` → role `finance`
- `demo_fo` → role `front_office`
- `demo_gate` → role `gate`
- `demo_mgr` → role `manager`
- `demo_content` → role `content`

**Fixture booking test (di DB):**
- `TEST-E2E-1` — MENUNGGU_BAYAR
- `TEST-E2E-2` — MENUNGGU_VERIFIKASI
- `TEST-E2E-3` — LUNAS (1 payment TERVERIFIKASI + 1 ledger IN/TIKET)
- `TEST-E2E-4` — DIBATALKAN

**Config key:**
- `config/states.php` — state machine
- `config/permissions.php` — 36 canonical permissions
- `config/roles.php` — 6 roles
- `config/routes_rbac.php` — 26 route → permission mapping

**Migration files (applied):**
- V4_001_rbac.sql (roles, permissions, role_permissions)
- V4_002_reconciliation.sql (ledger_reconciliations, reconciliation_batches)
- V4_003_booking_core.sql (payment_deadline_at, channel, visit_date, booking_items, ticket_types, ticket_prices)
- V4_004_expenses.sql (expenses table)
- V4_005_daily_closings.sql (daily_closings, ledger_entries.is_locked)
- V4_006_notifications.sql (notification_outbox, wa_templates)

---

## 📝 KETIKA LANJUT

**Prompt untuk agent berikutnya:**
```
Baca AGENTS.md, PRD.md, SESSION_CONTINUE_PROMPT.md.
Phase 3-7 selesai. Phase 8 (Demo Mode) 70% done.
Owner decisions §14 pending.

Current state:
- Demo data seeder has integrity issues (payment IDs ↔ ledger mismatch)
- Need: fix seeder, build reset_demo.php, add demo banner, simulated payment, auto-reset
- Then test full flow + run integrity_check

Gunakan /caveman + /ponytail full.
Step by step, satu file/perubahan per komit.
```