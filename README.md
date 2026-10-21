# AquaTix Cahaya Abadi

> Reservasi tiket & buku tamu digital untuk **Waterboom Cahaya Abadi** (Kiangroke, Banjaran) — dari form online sampai QR check-in di gerbang.
> Online ticketing & guest book for the waterpark — from online form to QR gate check-in.
>
> Status: `prototype` · Repo: https://github.com/HaedarWinter/ujikom-projek · Cerita lengkap: [`PROJECT_STORY.md`](PROJECT_STORY.md)

## 🎯 Apa Ini / What Is It

| | 🇮🇩 | 🇬🇧 |
|---|---|---|
| **Apa** | Sistem reservasi tiket online + buku tamu digital | Online ticket reservation + digital guest book |
| **Fitur inti** | Reservasi berkuota · upload bukti transfer · verifikasi admin · tiket PDF+QR via WhatsApp · scanner check-in · dashboard statistik & audit log | Quota-tracked reservation · transfer proof upload · admin verification · PDF+QR tickets via WhatsApp · check-in scanner · stats & audit-log dashboard |
| **Keamanan** | Audit & hardening terdokumentasi: **0 temuan Critical/High terbuka** | Documented audit & hardening: **0 open Critical/High findings** |

**🌱 Awam:** Pengunjung pesan tiket dari HP, dapat nomor booking di WhatsApp, upload bukti transfer, dan menerima tiket PDF ber-QR. Operator memverifikasi + scan QR di gerbang — buku tamu kertas tidak lagi dibutuhkan.
**🌱 Plain:** Visitors book from their phone, get a booking code on WhatsApp, upload transfer proof, and receive a QR PDF ticket. Operators verify and scan QR at the gate — no paper guest book needed.

## 🛠️ Tech Stack

PHP 8 (native, tanpa framework) · MySQL (prepared statements) · FPDF + QR · HTML5 QR scanner · SweetAlert2 · Fonnte WA API · Vanilla CSS.

```
Public (index, reservasi, cek_booking) ─┐
Admin  (dashboard, CRUD, scanner)  ─────┼─→ handlers (POST + CSRF + permission)
                  │                     │         ↓
                  └─ app/init.php (autoloader, session hardening, lazy cron)
                             ↓
                      app/Models/*.php → App\Core\Database (mysqli singleton)
                                       → App\Services\TicketService (FPDF + QR)
```

## 📁 Struktur / Structure

```
ujikom/
├── index.php           # landing + buku tamu + review
├── reservasi.php       # form reservasi (kuota real-time)
├── cek_booking.php     # status booking + upload bukti + tiket QR
├── login.php           # login admin
├── admin/              # dashboard, CRUD, scanner, kelola admin, logs
│   └── handlers/       # POST handler (CSRF + permission guard)
├── app/                # autoloader, Database singleton, Models, TicketService
├── config/             # koneksi (delegasi Database) + security helpers (CSRF, rate limit)
├── sql/                # db.sql (skema + seed) & migration_v2.sql
├── tests/              # verify_hardening.php (CLI assertion)
└── docs/audit/         # laporan audit & hasil verifikasi
```

## ▶️ Menjalankan Lokal / Run Locally

```bash
# 1. Clone
git clone https://github.com/HaedarWinter/ujikom-projek.git ujikom
cd ujikom

# 2. Start XAMPP: Apache + MySQL

# 3. Import database
mysql -u root < sql/db.sql
# + migrasi keamanan bila tabel belum ada:
# mysql -u root < sql/migration_v2.sql

# 4. Akses
#   Public: http://localhost/ujikom/
#   Admin:  http://localhost/ujikom/login.php
```

Prasyarat / Requirements: PHP ≥ 8, MySQL/MariaDB, XAMPP; ekstensi `fileinfo` + `curl`; internet untuk QR API & WhatsApp.
Akun seed ada di `sql/db.sql` — **segera ganti password default.**

## 🧪 Verifikasi / Verification

```bash
php tests/verify_hardening.php   # 7 PASS / 0 FAIL (akses negatif + race kuota)
```

- Laporan audit: `docs/audit/01-findings.md` (11 temuan, format per PRD)
- Hasil verifikasi: `docs/audit/03-verification.md`

## 🗺️ Status & Backlog

Prototype berjalan penuh di XAMPP lokal. Backlog: guard sesi scanner/API, CSRF logout/export, pengambilan ulang slot kuota setelah penolakan pembayaran, QRIS dinamis.

## 🙏 Kredit / Credits

Waterboom Cahaya Abadi (venue klien) · FPDF · SweetAlert2 · html5-qrcode · Fonnte.
Lisensi / License: All rights reserved.
