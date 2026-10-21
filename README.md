# Waterboom Cahaya Abadi — Reservasi Tiket & Buku Tamu Digital

> **ID:** Sistem reservasi tiket & buku tamu digital untuk Waterboom Cahaya Abadi (Kiangroke, Banjaran, Bandung) — dari form online sampai QR check-in di gerbang.
> **EN:** Online ticketing & guest book for Waterboom Cahaya Abadi (Banjaran, Indonesia) — from online form to QR gate check-in.
>
> Status: `prototype` · Repo: https://github.com/HaedarWinter/waterboom-cahaya-abadi · Cerita lengkap: [`PROJECT_STORY.md`](PROJECT_STORY.md) · Laporan audit: [`docs/audit/`](docs/audit/)

---

## 🎯 1. Ringkasan Cepat / Quick Summary

| | 🇮🇩 Indonesia | 🇬🇧 English |
|---|---|---|
| **Apa ini? / What is it?** | Sistem reservasi tiket online + buku tamu digital | Online ticket reservation + digital guest book |
| **Untuk siapa? / For whom?** | Pengunjung waterpark & operator venue | Waterpark visitors & venue operators |
| **Hasil utama / Key result** | Booking berkuota, tiket QR otomatis, dashboard lengkap, **0 temuan Critical/High terbuka** pasca-audit | Quota-tracked booking, automatic QR tickets, full dashboard, **0 open Critical/High findings** post-audit |

**🌱 Awam:** Pengunjung mengisi form di HP, dapat kode booking di WhatsApp, upload bukti transfer, lalu menerima tiket PDF bertanda QR. Operator memverifikasi dan scan QR di gerbang — buku tamu kertas tidak dibutuhkan lagi.
**🌱 Plain:** Visitors fill a form on their phone, get a booking code via WhatsApp, upload transfer proof, and receive a PDF ticket with QR. Operators verify payments and scan QR at the gate — no more paper guest book.

**🔧 Pro:** PHP 8 native tanpa framework, pola MVC-lite (autoloader → Model → Service) di atas MySQL via prepared statement `mysqli`; kuota atomik (transaksi + `FOR UPDATE`), PDF tiket FPDF + QR, scanner HTML5 QR, notifikasi WhatsApp Fonnte.
**🔧 Pro:** Framework-less PHP 8 with MVC-lite (autoloader → Model → Service) on MySQL via `mysqli` prepared statements; atomic quotas (transaction + `FOR UPDATE`), FPDF+QR tickets, HTML5 QR scanner, WhatsApp notifications via Fonnte.

---

## 👤 2. Untuk Siapa / Who Is This For

**Pengguna utama / Primary user:** Keluarga pengunjung akhir pekan yang ingin aman dari tempat penuh dan antre loket. / Weekend family visitors who want certainty about availability, no counter queues.

| Dimensi / Dimension | 🇮🇩 | 🇬🇧 |
|---|---|---|
| **Melihat / Sees** | Info venue, harga tiket, sisa kuota hari ini di landing page | Venue info, ticket prices, today's remaining quota on the landing page |
| **Mendengar / Hears** | "Tempatnya selalu penuh di akhir pekan" | "It's always packed on weekends" |
| **Berpikir & merasa / Thinks & feels** | Cemas slot habis, malas antre di loket | Fear of sold-out slots, reluctance to queue |
| **Melakukan / Does** | Datang langsung, menebak kapasitas, bayar tunai | Walk-in visits, guessing capacity, paying cash |

**Pengguna sekunder / Secondary users:** Petugas (verifikasi bayar, scanner check-in), Super Admin (kelola akun & berita). / Staff (payment verification, check-in scanning), Super Admin (accounts & news).
**Bukan untuk / Not for:** Payment gateway otomatis — verifikasi transfer sengaja manual sesuai skala venue. / Automated payment gateway — transfer verification is intentionally manual for venue scale.

---

## 🔥 3. Masalah / The Problem

| | 🇮🇩 | 🇬🇧 |
|---|---|---|
| **Situasi** | Jam puncak akhir pekan: antrean loket memanjang, tamu dicatat di buku kertas | Weekend peak: long counter queues, guests logged in a paper book |
| **Rasa sakit** | Waktu terbuang di jalan, kapasitas tak pasti, data tak bisa dianalisa | Wasted travel time, uncertain capacity, unanalysable data |
| **Cara lama** | Buku tamu kertas + tiket dijual di loket | Paper guest book + counter-only ticket sales |

**Akar masalah / Root cause:** tiket hanya dijual fisik → tak ada kanal reservasi dini → pencatatan manual tak real-time maupun atomik. / Physical-only ticket sales → no pre-booking channel → manual recording that is neither real-time nor atomic.

**Bukti / Evidence:** TODO: tanya owner (observasi lapangan belum didokumentasikan). / TODO: ask owner.

---

## 💡 4. Solusi / The Solution

**Cara kerja / How it works:**
1. Pengunjung isi form; jenis tiket (weekday/weekend) ditentukan otomatis dari tanggal; kuota harian **dikunci di dalam satu transaksi database**.
2. Transfer + upload bukti → admin verifikasi → sistem generate PDF + QR dan kirim via WhatsApp.
3. Di gerbang, petugas scan QR — status `Selesai`, waktu check-in tercatat otomatis.

**Fitur & manfaat / Features & benefits:**

| Fitur / Feature | Manfaat / User benefit |
|---|---|
| Reservasi online berkuota atomik | Slot terjamin; oversell dibuat mustahil di level SQL, bukan hanya PHP |
| Upload bukti + verifikasi admin | Pembayaran transfer divalidasi manusia; status tak bisa dimanipulasi |
| Tiket PDF + QR via WhatsApp | Tiket digital tak bisa hilang; tidak perlu cetak |
| Scanner check-in HTML5 | Satu gesekan per grup; tiket invalid ditolak server, bukan petugas |
| Dashboard statistik + audit log | Setiap aksi admin terekam; keputusan harga berbasis data |

- ✅ Buku tamu kertas → data terstruktur di database
- ✅ Antre loket → reservasi sebelum datang
- ✅ Tiket fisik rentan hilang → QR digital
- ⏳ **Belum terselesaikan / Not yet solved:** payment gateway otomatis masih manual-verified; slot kuota belum diambil kembali saat pembayaran ditolak lalu di-upload ulang (menunggu keputusan produk).

---

## 🧠 5. Keputusan Desain / Design Decisions

| Keputusan / Decision | Prinsip / Principle |
|---|---|
| Data form tetap terisi saat redirect error (flash session) | Fogg Behavior Model — kesalahan validasi tak memaksa mengetik ulang |
| Sisa kuota real-time di landing page | Goal-Gradient + scarcity — slot terbatas mempercepat keputusan |
| Konfirmasi SweetAlert sebelum delete | Loss Aversion / Error Prevention — aksi perusak butuh keputusan eksplisit |
| Jenis tiket dipilih otomatis dari tanggal | Nudge / Default Effect — tidak bisa salah kelas harga |
| Glassmorphism + bento grid | Aesthetic-Usability Effect — kontras dengan proses kertas lama |

---

## 🌍 6. Dampak / Impact

Metrik dikuasai data, bukan klaim: **TODO: tanya owner** untuk angka sebelum/sesudah. Dampak yang *diharapkan* tapi belum terbukti: antrean menurun, oversell tak terjadi.
Metrics rely on data, not claims: **TODO: ask owner** for before/after numbers. *Expected* but unproven: shorter queues, zero oversell.

---

## 🧗 7. Tantangan & Pelajaran / Challenges & Lessons

| Tantangan / Challenge | Solusi / Fix | Pelajaran / Lesson |
|---|---|---|
| Race condition kuota (oversell paralel) | Transaksi + `SELECT ... FOR UPDATE` | Batas data dikunci di SQL, bukan di PHP — check-then-act adalah bug klasik |
| Upload berpotensi ekstensi berbahaya | `finfo` MIME + whitelist ekstensi + nama di-generate ulang | Tak pernah percaya nama asli file user |
| Input "dari admin" tetap bisa jadi payload XSS | Semua output lewat helper escape | Data dari orang internal pun harus di-escape |
| Brute-force login | Rate limit tabel `login_attempts` + bcrypt | Keamanan berlapis: DB, sesi, hash — bukan satu benteng |
| Audit tanpa framework test | `tests/verify_hardening.php` CLI assertion | Tes kecil deterministic mengalahkan "sudah dites manual" |

---

## 🛠️ 8. Detail Teknis / Technical Details

**Tech stack:** PHP 8 native · MySQL/MariaDB (prepared statement `mysqli`) · FPDF + QR · HTML5 QR scanner · SweetAlert2 · Fonnte WA API · Vanilla CSS.

**Arsitektur / Architecture:**
```
Public (index, reservasi, cek_booking) ─┐
Admin  (dashboard, CRUD, scanner)  ─────┼─→ handlers (POST + CSRF + permission)
                  │                     │         ↓
                  └─ app/init.php (autoloader, session hardening, lazy cron)
                             ↓
                      app/Models/*.php → App\Core\Database (mysqli singleton)
                                       → App\Services\TicketService (FPDF + QR)
```

**Keputusan teknis penting / Key technical decisions:**
- PHP native → kode terbaca penuh asesor/employer; abstraksi inti (autoloader `App\`, Database singleton, Model layer) ditulis sendiri.
- WhatsApp sebagai kanal notifikasi → deliverability ditanggap lebih kuat dari email untuk persona pengunjung venue ini.
- Verifikasi pembayaran manual → dana tidak melewati third-party; risiko finansial rendah sesuai skala venue.
- Audit & hardening terdokumentasi → 11 temuan; semua Critical/High ditutup; bukti di `docs/audit/` dan `tests/verify_hardening.php` (**7 PASS / 0 FAIL**).

### ▶️ Menjalankan Lokal / Run Locally

```bash
# 1. Clone
git clone https://github.com/HaedarWinter/waterboom-cahaya-abadi.git ujikom
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

**Prasyarat / Requirements:** PHP ≥ 8, MySQL/MariaDB, XAMPP (atau LAMP); ekstensi `fileinfo` + `curl`; internet untuk QR API & WhatsApp.
Akun seed: lihat `sql/db.sql` — **segera ganti password default.**
Path socket XAMPP macOS sudah jadi fallback otomatis di `App\Core\Database` — deploy ke Linux tanpa ubah konfigurasi.

**Struktur / Structure:**

```
ujikom/
├── index.php           # landing + buku tamu + review
├── reservasi.php       # form reservasi (kuota real-time)
├── cek_booking.php     # status booking + upload bukti + tiket QR
├── login.php           # login admin
├── admin/              # dashboard, CRUD, scanner, kelola admin, logs
│   └── handlers/       # POST handler (CSRF + permission guard)
├── app/                # autoloader, Database singleton, Models, TicketService
├── config/             # koneksi (delegasi Database) + security helpers
├── sql/                # db.sql (skema + seed) & migration_v2.sql
├── tests/              # verify_hardening.php (CLI assertion)
└── docs/audit/         # laporan audit & hasil verifikasi
```

---

## 🗺️ 9. Status & Backlog / Status & Next Steps

Prototype berjalan penuh di XAMPP lokal; hasil audit: 0 temuan Critical/High terbuka.
Prototype fully running on local XAMPP; audit result: 0 open Critical/High findings.

- [ ] Guard `enforce_active_session` di scanner handler & API notifications
- [ ] CSRF di logout & export
- [ ] Keputusan produk: pengambilan ulang slot kuota setelah pembayaran ditolak
- [ ] Opsional: QRIS dinamis / payment gateway
- [ ] Tes paralel HTTP (50 concurrent) dengan tooling antrean

---

## 🎬 10. Cerita Singkat / Short Story

**🇮🇩** *(~100 kata)*
Pengunjung Waterboom Cahaya Abadi dulu datang tanpa yakin ada slot sisa: petugas mencatat tamu di buku kertas, tiket hanya dijual di loket, dan kapasitas kolam tak pernah jadi bilangan yang pasti. Sistem reservasi ini mengubah alurnya: reservasi kecil di form online, kuota harian dikunci di dalam satu transaksi database, pembayaran lewat transfer dengan bukti diunggah, lalu tiket PDF ber-QR muncul langsung di WhatsApp pengunjung. Di gerbang, petugas scan QR sekali; di dashboard, operator melihat kuota, pendapatan bulanan, dan jejak audit setiap aksi. Buku kertas hilang, antrean loket menipis, dan setiap kunjungan kini meninggalkan data yang bisa dinilai ulang.

**🇬🇧** *(~100 words)*
Waterboom Cahaya Abadi's visitors used to arrive without knowing whether slots remained: staff logged guests in a paper book, tickets were sold only at the counter, and pool capacity was never a number anyone could trust. This booking system rewired the flow: a short online reservation form, a daily quota locked inside a single database transaction, bank transfer with uploaded proof, and a QR-carrying PDF ticket delivered straight to the visitor's WhatsApp. At the gate, staff scan the QR once. On the dashboard, operators see quotas, monthly revenue, and an audit trail of every action. The paper book is gone, queues shrink, and every visit now leaves measurable data.

---

## 🙏 11. Tautan & Kredit / Links & Credits

- 🌐 Live: TODO (belum di-hosting)
- 💻 Repo: https://github.com/HaedarWinter/waterboom-cahaya-abadi
- 🎥 Demo: TODO
- 🙏 Kredit / Credits: Waterboom Cahaya Abadi (venue klien) · FPDF · SweetAlert2 · html5-qrcode · Fonnte
- 📄 Lisensi / License: All rights reserved
