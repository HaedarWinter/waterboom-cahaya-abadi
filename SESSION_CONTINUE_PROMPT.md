# SESSION CONTINUATION PROMPT
**Project:** Waterboom Cahaya Abadi  
**Last Session:** Phase 3 (RBAC) + Phase 4 (Booking Core) — 100% complete  
**Date:** 2026-10-22  
**Stack:** PHP 8.x native, MySQL/MariaDB, SweetAlert2, Chart.js, Lucide  
**Env:** Test DB `db_ujikom_test` @ `127.0.0.1:3307` — **jangan sentuh `db_ujikom` port 3306**

---

## STATUS CEKLIS

### ✅ SELESAI 100%

**Phase 3: RBAC & Akses**
- 28 route admin guarded dengan canonical permission (PRD §3.2)
- Middleware centralized: `config/routes_rbac.php`, `app/Http/RouteRegistry.php`, `app/Http/Middleware/Bootstrap.php`
- Default-deny: route tanpa mapping = 403
- Granular guards: `payment.verify`, `payment.reject`, `refund.request`, `refund.approve`, `ledger.view`, `reconciliation.manage`, `user.manage`, `role.manage`, `activity.view`, `checkin.scan`, `content.news.manage`, `content.gallery.manage`, `content.faq.manage`, `review.moderate`, `guestbook.moderate`, `settings.manage`, `dashboard.view`, `reservation.view`, `report.finance.view`
- Legacy JSON bridge aktif (kompatibilitas)

**Phase 4: Inti Booking**
- `config/states.php` — 11 status + 19 transisi (PRD §4.2 table 4.2)
- `BookingStateService` refactor — config-driven, QR token hash (sha256)
- Migration `V4_003_booking_core.sql` + rollback (siap apply):
  - `reservasi`: `payment_deadline_at`, `channel` (ONLINE/COUNTER), `visit_date`
  - `booking_items` — snapshot harga per tiket
  - `ticket_types`, `ticket_prices` — harga efektif per tanggal
- `Reservasi::create()` — simpan deadline, channel, visit_date, insert snapshot ke `booking_items`
- `Reservasi::getByTokenHash()`, `getByKodeAndHpLast4()` — lookup aman
- `cek_booking.php` — form kode + 4 digit HP (TKT-5)
- Scanner handler — pakai hash lookup
- `TicketService::generate($id, $rawToken)` — QR pakai raw token
- Cron endpoint `/cron/expire.php?token=...` — idempoten, token-based (FLOW-4)

**Static Tests: ALL PASS**
```
PASS canonical RBAC route guards
PASS append-only reconciliation contract
PASS reconciliation integrity check contract
```

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
| 14.10 | Rencana QRIS dinamis / payment gateway? (kolom `provider_ref`, webhook) | Payment infrastructure |

**Migration approval needed** (AGENTS.md §8): `database/migrations/V4_003_booking_core.sql`

---

## 📋 NEXT PHASES (step by step)

### Phase 5: Pembayaran & Keuangan (butuh §14 dulu)
- Kode unik (PAY-1/PAY-2) — blokir §14.4
- Refund maker-checker — blokir §14.2
- `expenses` ganti `laporan_keuangan` manual
- Tutup harian + penguncian periode
- Laporan hanya dari ledger

### Phase 6: Frontend Design System (independen, bisa mulai sekarang)
1. Token CSS (`0-tokens.css`) — warna, spacing, radius, font size
2. Base (`1-base.css`) — reset, typography
3. Layout (`2-layout.css`) — grid, container, sidebar
4. Atoms (`3-atoms/`) — button, input, badge, icon, avatar
5. Molecules (`4-molecules/`) — form-field, card, dropdown, modal, table-row
6. Organisms (`5-organisms/`) — header, sidebar, data-table, pagination, stepper
7. Pages (`6-pages/`) — dashboard, reservasi-list, scanner, cek-booking
8. Utilities (`7-utilities.css`) — spacing, visibility, print
9. Bundle via `scripts/build_assets.php` → `public/assets/css/app.min.css`, `app.min.js`

### Phase 7: Notifikasi & Tiket
- Outbox + retry + driver log (mode demo)
- PDF on-demand + signed URL (HMAC + expiry)
- Template WA di setting

### Phase 8: Demo & Rilis
- Mode demo lengkap + reset script
- Deploy hosting gratis (Railway/Vercel/PlanetScale)
- `PROJECT_STORY.md` + screenshot/video
- README final

---

## 🎯 IMMEDIATE NEXT ACTIONS (prioritas)

1. **Kirim template approval owner** (migration V4_003 + §14 answers)
2. **Backup test DB → apply migration V4_003** → run concurrency test
3. **Mulai Phase 6** (Frontend) — parallel, tidak butuh owner

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

**Akun test (password di file luar repo):**
- `test_frontoffice` → role `front_office` (legacy Admin)
- `test_finance` → role `finance` (legacy Finance)  
- `test_gate` → role `gate` (legacy Gate)

**Fixture booking test:**
- `TEST-E2E-1` — MENUNGGU_BAYAR
- `TEST-E2E-2` — MENUNGGU_VERIFIKASI
- `TEST-E2E-3` — LUNAS (1 payment TERVERIFIKASI + 1 ledger IN/TIKET)
- `TEST-E2E-4` — DIBATALKAN

**Config key:**
- `config/states.php` — state machine
- `config/permissions.php` — 32 canonical permissions
- `config/roles.php` — 6 roles
- `config/routes_rbac.php` — 26 route → permission mapping

---

## 📝 KETIKA LANJUT

**Prompt untuk agent berikutnya:**
```
Baca AGENTS.md, PRD.md, docs/audit/rbac-phase3-progress.md, SESSION_CONTINUE_PROMPT.md.
Phase 3 & 4 selesai. Owner decisions §14 pending.
Migration V4_003 ready but not applied.
Static tests all pass.

Next: 
1. Owner approval template untuk migration + §14
2. Phase 6 (Frontend design system) - independen
3. Apply migration V4_003 → concurrency test → Phase 5

Gunakan /caveman + /ponytail full.
Step by step, satu file/perubahan per komit.
```

---

## 📂 FILE PENTING TERBARU

```
/config/states.php                    # State machine config
/config/routes_rbac.php               # Route → permission mapping
/app/Http/RouteRegistry.php           # Registry class
/app/Http/Middleware/RequirePermission.php
/app/Http/Middleware/Bootstrap.php
/app/Services/BookingStateService.php # Refactored
/app/Models/Reservasi.php             # create() snapshot, getByTokenHash, getByKodeAndHpLast4
/app/Services/TicketService.php       # generate($id, $rawToken)
/public/cron/expire.php               # Cron endpoint
/cek_booking.php                      # Updated form (kode + HP last4)
/database/migrations/V4_003_booking_core.sql
/database/rollbacks/V4_003_booking_core.sql
/tests/test_rbac_static.php           # Updated
/docs/audit/rbac-phase3-progress.md   # Updated
```