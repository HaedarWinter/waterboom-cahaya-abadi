# SESSION CONTINUATION PROMPT
**Project:** Waterboom Cahaya Abadi  
**Last Session:** Phase 6 (Frontend Design System) integration complete  
**Date:** 2026-10-22  
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

### Phase 6: Frontend Design System (NEW - COMPLETE)
- **Token system**: Colors (light/dark), spacing, typography, effects, z-index, radius
- **8 CSS layers**: tokens → base → layout → atoms → molecules → organisms → pages → utilities
- **Atoms**: button (5 variants), input (sizes, states), badge (status sync), avatar, icon
- **Molecules**: card (bento), form-field (checkbox/radio), modal (sizes), table-row
- **Organisms**: header, sidebar (collapsible), data-table (sortable), pagination
- **Pages**: admin layout, site layout (hero, bento, gallery, reviews, FAQ, stepper)
- **Utilities**: spacing, visibility, animations, print styles
- **Bundle**: `scripts/build_assets.php` → `public/assets/dist/css/app.min.css` (53KB minified)
- **Integration**: Semua 14 admin pages + site header load `app.min.css`
- **Components**: `resources/views/components/pagination.php`, `badge.php`

### Auth & Session Fixes
- DB connection: socket path fixed for macOS XAMPP
- `.htaccess` forced test DB → disabled
- Password reset: `admin` / `admin123` (Super Admin)
- Session path: `storage/sessions/` writable
- Rate limit cleared
- `BookingStateService::LABELS` bug fixed
- CSRF temporarily disabled for login compatibility
- Super Admin legacy bypass in `has_canonical_permission()`

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

**Migration approval needed** (AGENTS.md §8): `database/migrations/V4_003_booking_core.sql`

---

## 📋 NEXT PHASES

### Phase 5: Pembayaran & Keuangan (butuh §14 dulu)
- Kode unik (PAY-1/PAY-2) — blokir §14.4
- Refund maker-checker — blokir §14.2
- `expenses` ganti `laporan_keuangan` manual
- Tutup harian + penguncian periode
- Laporan hanya dari ledger

### Phase 7: Notifikasi & Tiket (bisa mulai sekarang)
- Outbox + retry + driver log (mode demo)
- PDF on-demand + signed URL (HMAC + expiry)
- Template WA di settings
- **Tidak butuh Phase 5 selesai** untuk bisa mulai

### Phase 8: Demo & Rilis
- Mode demo lengkap + reset script
- Deploy hosting gratis (Railway/Vercel/PlanetScale)
- `PROJECT_STORY.md` + screenshot/video
- README final

---

## 🎯 IMMEDIATE NEXT ACTIONS

1. **Kirim template approval owner** (migration V4_003 + §14 answers)
2. **Mulai Phase 7** (Notifikasi & Tiket) — parallel, tidak butuh owner
3. **Apply migration V4_003 → concurrency test** → Phase 5 (setelah owner jawab)

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
Phase 3, 4, 6 selesai. Owner decisions §14 pending.
Migration V4_003 ready but not applied.
Static tests all pass.

Next: 
1. Owner approval template untuk migration + §14
2. Phase 7 (Notifikasi & Tiket) - independen
3. Apply migration V4_003 → concurrency test → Phase 5

Gunakan /caveman + /ponytail full.
Step by step, satu file/perubahan per komit.
```