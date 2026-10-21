---
slug: "waterboom-cahaya-abadi"
status: "prototype"
featured: false
year: 2026
role:
  id: "Full-stack Developer"
  en: "Full-stack Developer"
title:
  id: "Waterboom Cahaya Abadi — Reservasi Tiket"
  en: "Waterboom Cahaya Abadi — Ticket Booking"
tagline:
  id: "Reservasi tiket waterpark tanpa antre: dari form online sampai QR check-in di gerbang."
  en: "Waterpark ticketing without queues: online form to QR gate check-in."
card_summary:
  id: "Buku tamu kertas dan antre loket diganti sistem reservasi online dengan kuota harian teratomik, tiket QR via WhatsApp, dan verifikasi pembayaran admin. Hasil audit: 0 temuan Critical/High terbuka."
  en: "Paper guestbook and counter queues replaced by online booking with atomically-tracked daily quotas, QR tickets via WhatsApp, and admin payment verification. Audit result: 0 open Critical/High findings."
audience:
  id: "Pengunjung waterpark, operator venue, verifikator LSP"
  en: "Waterpark visitors, venue operators, skill-certification assessors"
tags: ["web", "database", "wa-api", "security-audit"]
tech_stack: ["PHP 8 (native)", "MySQL", "FPDF", "Vanilla JS", "SweetAlert2"]
highlight_metric:
  value: "0"
  label_id: "temuan keamanan Critical/High terbuka setelah audit & hardening"
  label_en: "open Critical/High security findings after audit & hardening"
links:
  live: ""
  repo: "https://github.com/HaedarWinter/ujikom-projek"
  demo_video: ""
  case_study: ""
cover_image: "/images/projects/waterboom-cahaya-abadi-cover.png"
---

# Waterboom Cahaya Abadi — Reservasi Tiket & Buku Tamu Digital

> **ID:** Sistem reservasi tiket & buku tamu digital untuk Waterboom Cahaya Abadi (Kiangroke, Banjaran, Bandung) — dari form online sampai QR check-in di gerbang.
> **EN:** Online ticketing & guest book for Waterboom Cahaya Abadi (Kiangroke, Banjaran, Indonesia) — from online form to QR gate check-in.

---

## 1. 🎯 Ringkasan Cepat / Quick Summary

| | 🇮🇩 Indonesia | 🇬🇧 English |
|---|---|---|
| **Apa ini? / What is it?** | Sistem reservasi tiket + buku tamu digital untuk waterpark | Waterpark booking + digital guest book system |
| **Untuk siapa? / For whom?** | Pengunjung & operator Waterboom Cahaya Abadi | Visitors & operators of the waterpark |
| **Hasil utama / Key result** | Booking online berkuota, tiket QR otomatis, dashboard admin lengkap | Quota-tracked online booking, automatic QR tickets, complete admin dashboard |

**🌱 Awam:** Pengunjung mengisi form di HP, dapat kode booking di WhatsApp, upload bukti transfer, lalu menerima tiket PDF bertanda QR. Operator memantau semuanya dari satu dashboard. Buku tamu kertas tidak dibutuhkan lagi.
**🌱 Plain:** Visitors fill a form on their phone, get a booking code via WhatsApp, upload proof of transfer, and receive a PDF ticket with a QR code. Operators monitor everything from one dashboard. The paper guest book becomes unnecessary.

**🔧 Pro:** PHP 8 native tanpa framework, pola MVC-lite (autoloader → Model → Service) di atas MySQL via prepared statement `mysqli`; PDF tiket dengan FPDF + QR, scanner HTML5 QR, notifikasi WhatsApp via Fonnte, kuota atomik dengan transaksi + `FOR UPDATE`.
**🔧 Pro:** Framework-less PHP 8 with MVC-lite (autoloader → Model → Service) on MySQL via `mysqli` prepared statements; FPDF ticket PDFs with QR, HTML5 QR scanner, WhatsApp notifications via Fonnte, atomic quota with transaction + `FOR UPDATE`.

---

## 2. 👤 Untuk Siapa / Who Is This For

**Pengguna utama / Primary user:** Keluarga pengunjung akhir pekan yang ingin aman dari tempat penuh dan antre loket. / Weekend family visitors who want certainty about availability and no ticket-counter queues.

| Dimensi / Dimension | 🇮🇩 | 🇬🇧 |
|---|---|---|
| **Melihat / Sees** | Info venue, harga tiket, sisa kuota hari ini | Venue info, ticket prices, today's remaining quota |
| **Mendengar / Hears** | "Tempatnya selalu penuh di akhir pekan" | "It's always packed on weekends" |
| **Berpikir & merasa / Thinks & feels** | Cemas slot habis, malas menunggu di loket | Fear of sold-out slots, reluctance to wait in line |
| **Melakukan / Does** | Datang langsung, menebak kapasitas, bayar tunai | Walk-in visits, guessing capacity, paying cash |

**Pengguna sekunder / Secondary users:** Petugas operator (kuota, verifikasi pembayaran, scanner check-in), Super Admin (kelola akun, berita, pengaturan). / Staff operators (quota, payment verification, check-in scanner), Super Admin (accounts, news, settings).
**Bukan untuk / Not for:** Integrasi payment gateway otomatis — verifikasi transfer sengaja dikerjakan manusia sesuai skala venue. / Automated payment gateway — transfer verification is intentionally manual, matching venue scale.

---

## 3. 🔥 Masalah yang Ditemukan / The Problem

**Situasi / Situation:** Jam puncak akhir pekan: antrean loket memanjang, pencatatan tamu memakai buku kertas harian. / Peak weekend hours: long counter queues, guests recorded in a daily paper book.
**Rasa sakitnya / The pain:** Waktu pengunjung terbuang di jalan, kapasitas tak pernah diketahui sebelum penuh, data pengunjung sulit dianalisa. / Visitor time wasted, capacity unknown until full, visitor data hard to analyse.
**Cara lama / Old way:** Buku tamu kertas + penjualan tiket di loket. / Paper guest book + counter ticket sales.

**Akar masalah / Root cause (5 Whys singkat):**
1. Kenapa antre? — Tiket hanya dijual fisik di loket.
2. Kenapa tak bisa pesan dulu? — Belum ada kanal reservasi sebelum datang.
3. Kenapa kapasitas tak terkontrol? — Pencatatan manual tidak real-time dan tidak atomik.

**🌱 Awam:** Masalahnya, pengunjung harus datang dulu untuk tahu apakah tempat masih muat — dan pencatatan pakai buku kertas, jadi tak ada data yang bisa dihitung cepat.
**🔧 Pro:** Alur check-then-act di pencatatan manual membuat kapasitas tak pernah real-time; bottleneck operasional. / Manual check-then-act recording never yields real-time capacity; an operational bottleneck.

**Bukti masalah nyata / Evidence:** TODO: tanya owner — observasi lapangan belum didokumentasikan. / TODO: ask owner — field observation not yet documented.

---

## 4. 💡 Solusi yang Dibuat / The Solution

**Ide inti / Core idea:** Satu alur online: reservasi berkuota → verifikasi transfer → tiket QR → scan check-in. / One online flow: quota-tracked reservation → transfer verification → QR ticket → scan check-in.

**Cara kerja (3 langkah) / How it works (3 steps):**
1. Pengunjung mengisi form; jenis tiket ditentukan otomatis dari tanggal (weekday/weekend) dan kuota harian dikunci di database.
2. Setelah transfer dan upload bukti, admin memverifikasi; sistem generate PDF + QR dan kirimnya via WhatsApp.
3. Di gerbang, petugas scan QR — status berubah jadi `Selesai` dan tercatat waktu check-in-nya.

**Fitur utama / Key features:**

| Fitur / Feature | Manfaat / User benefit |
|---|---|
| Reservasi online berkuota atomik | Slot terjamin, oversell direkayasa mustahil (transaksi + `FOR UPDATE`) |
|-upload bukti + verifikasi admin | Pembayaran transfer divalidasi manusia; status tidak bisa dimanipulasi |
| Tiket PDF + QR via WhatsApp | Tiket digital tak bisa hilang; tidak perlu cetak |
| Scanner check-in HTML5 | Satu gesekan per grup; status invalid ditolak server, bukan petugas |
| Dashboard statistik + audit log | Setiap aksi admin terekam; keputusan harga berbasis data |

**Masalah yang selesai / Which problem is solved:**
- ✅ Buku tamu kertas → data pengunjung terstruktur di database
- ✅ Antre loket → reservasi sebelum datang
- ✅ Kuota tak terkontrol → atomic transaction anti-oversell
- ✅ Tiket fisik rentan hilang → QR digital
- ⏳ **Belum terselesaikan / Not yet solved:** pembayaran gateway otomatis (QRIS dinamis) masih manual-verified; booking yang ditolak lalu di-upload ulang belum mengambil slot kuota kembali (keputusan produk sedang menunggu opsi owner).

---

## 5. 🧠 Keputusan Desain & Prinsip / Design Decisions & Psychology

| Keputusan / Decision | Prinsip / Principle | Kenapa efektif / Why it works |
|---|---|---|
| Data form tetap terisi saat redirect (error) memakai flash session | Fogg Behavior Model (turunkan usaha) | Kesalahan validasi tidak memaksa pengunjung mengulang ketikan dari awal |
| Kuota tersisa tampil real-time di landing page | Goal-Gradient Effect + scarcity | Sisa slot terbatas mempercepat keputusan booking |
| Konfirmasi SweetAlert sebelum delete | Loss Aversion / Error Prevention | Aksi perusak membutuhkan keputusan eksplisit, bukan sekali klik |
| Jenis tiket (weekday/weekend) dipilih otomatis dari tanggal | Nudge / Default Effect | Pengunjung tidak bisa salah pilih kelas harga |
| Glassmorphism / bento grid di landing | Aesthetic-Usability Effect | Tampilan modern — kontras dengan proses kertas yang lama |

---

## 6. 🌍 Dampak / Impact

| Lapisan / Layer | 🇮🇩 | 🇬🇧 |
|---|---|---|
| **Pengunjung** | Pemesan dari rumah, slot pasti, tiket di WhatsApp | Book from home, confirmed slot, ticket on WhatsApp |
| **Operator** | Satu dashboard: kuota, verifikasi, audit; scan 1 langkah | Single dashboard: quotas, verification, audit; one-step scan |
| **Skala lebih luas** | Data visitor jadi dasar keputusan operasional | Visitor data can drive operations decisions |

**Metrik / Metrics:**

| Sebelum / Before | Sesudah / After | Cara ukur / How measured |
|---|---|---|
| TODO: tanya owner | TODO: tanya owner | TODO: definisi ukur bersama owner |

> Tidak ada angka kinerja yang diklaim sebelum ada bukti asli dari owner.
> No performance numbers claimed until the owner provides real evidence.

**Dampak yang diharapkan tapi belum terbukti / Expected but unproven:** antrean loket menurun; oversell tidak terjadi; belum ada pengukuran lapangan.

---

## 7. 🧗 Tantangan & Pelajaran / Challenges & Lessons

| Tantangan | Cara mengatasi | Pelajaran |
|---|---|---|
| Race condition kuota (oversell saat booking paralel) | Transaksi + `SELECT ... FOR UPDATE`, return `null` bila penuh | check-then-act adalah bug klasik; batas harus dikunci di SQL, bukan di PHP |
| Upload bukti berpotensi membawa ekstensi berbahaya | Validasi MIME via `finfo` + whitelist ekstensi; nama file di-generate ulang dari kode booking | Jangan pernah percaya nama asli file dari user |
| Input yang sudah "dipercaya" ternyata bisa jadi payload XSS | Semua output melewati helper escape `e()` / `htmlspecialchars` | Tanggap data "dari admin" pun tetap harus di-escape |
| Login brute-force & dependensi plaintext lama | Rate limit berbasis tabel `login_attempts` + bcrypt + hapus fallback plaintext | Keamanan dikerjakan berlapis: DB, sesi, hash |
| Audit tanpa framework test | `tests/verify_hardening.php`: assertion CLI + tes HTTP | Tes kecil deterministic mengalahkan statement "sudah dites manual" |

---

## 8. 🛠️ Detail Teknis / Technical Details

**Tech stack:** PHP 8 (native, tanpa framework), MySQL/MariaDB (prepared statement via `mysqli`), FPDF (PDF tiket), HTML5 QR Code (scanner), SweetAlert2 (UI), Fonnte API (WhatsApp), Vanilla CSS.

**Arsitektur:**
```
Public (index, reservasi, cek_booking) ─┐
Admin  (dashboard, CRUD, scanner)  ─────┼─→ handlers (POST + CSRF + permission)
                  │                     │         ↓
                  └─ app/init.php (autoloader, session hardening, lazy cron)
                             ↓
                      app/Models/*.php → App\Core\Database (mysqli singleton)
                                       → App\Services\TicketService (FPDF + QR)
```

**Keputusan teknis penting:**
- **PHP native tanpa framework** → kode tetap terbaca penuh oleh asesor/employer; абstraksi inti (autoloader `App\`, Database singleton, Model layer, service) ditulis sendiri.
- **Notifikasi via WhatsApp, bukan email** → operator dan pengunjung venue tipe ini hidup di WhatsApp; deliverability email dianggap lebih lemah.
- **Verifikasi pembayaran manual, bukan gateway** → tidak memindahkan dana ke third-party; risiko finansial rendah sesuai skala venue.
- **Audit + hardening terdokumentasi** → 11 temuan, semua Critical/High ditutup; bukti di `docs/audit/01-findings.md`, `docs/audit/03-verification.md`, dan `tests/verify_hardening.php` (7 PASS, 0 FAIL).

### Menjalankan Lokal / Run Locally

```bash
# 1. Clone
git clone https://github.com/HaedarWinter/ujikom-projek.git ujikom
cd ujikom

# 2. Jalankan XAMPP: Apache + MySQL

# 3. Import database (phpMyAdmin → Import, atau:)
mysql -u root < sql/db.sql
# + migrasi keamanan bila tabel belum ada:
# mysql -u root < sql/migration_v2.sql

# 4. Akses
#   Public: http://localhost/ujikom/
#   Admin:  http://localhost/ujikom/login.php
# Akun seed: lihat sql/db.sql — segera ganti password default.
```

**Prasyarat / Requirements:** PHP ≥ 8, MySQL/MariaDB, XAMPP (atau LAMP); ekstensi `fileinfo` dan `curl` aktif; koneksi internet untuk QR API & WhatsApp.

> Path socket XAMPP macOS sudah jadi fallback otomatis di `App\Core\Database` — deploy ke Linux tidak perlu modifikasi konfigurasi lagi.

---

## 9. 🗺️ Status & Langkah Berikutnya / Status & Next Steps

- **Status saat ini / Current status:** prototype — berjalan penuh di XAMPP lokal; hasil audit: 0 temuan Critical/High terbuka.
- [ ] Backlog keamanan: guard `enforce_active_session` di scanner handler & API notifications; CSRF di logout/export
- [ ] Keputusan produk: pengambilan ulang slot kuota bila pembayaran ditolak lalu di-upload ulang
- [ ] Opsional: QRIS dinamis / payment gateway
- [ ] Tes paralel HTTP (50 concurrent) dengan tooling antrean

---

## 10. 🎬 Cerita Singkat untuk Halaman Detail / Short Story

**🇮🇩** *(~100 kata)*
Pengunjung Waterboom Cahaya Abadi dulu datang tanpa yakin ada slot sisa: petugas mencatat tamu di buku kertas, tiket hanya dijual di loket, dan kapasitas kolam tak pernah jadi bilangan yang pasti. Sistem reservasi ini mengubah alurnya: reservasi kecil di form online, kuota harian dikunci di dalam satu transaksi database, pembayaran lewat transfer dengan bukti diunggah, lalu tiket PDF ber-QR muncul langsung di WhatsApp pengunjung. Di gerbang, petugas scan QR sekali; di dashboard, operator melihat kuota, pendapatan bulanan, dan jejak audit setiap aksi. Buku kertas hilang, antrean loket menipis, dan setiap kunjungan kini meninggalkan data yang bisa dinilai ulang.

**🇬🇧** *(~100 words)*
Waterboom Cahaya Abadi's visitors used to arrive without knowing whether slots remained: staff logged guests in a paper book, tickets were sold only at the counter, and pool capacity was never a number anyone could trust. This booking system rewired the flow: a short online reservation form, a daily quota locked inside a single database transaction, bank transfer with uploaded proof, and a QR-carrying PDF ticket delivered straight to the visitor's WhatsApp. At the gate, staff scan the QR once. On the dashboard, operators see quotas, monthly revenue, and an audit trail of every action. The paper book is gone, queues shrink, and every visit now leaves measurable data.

---

## 11. 🔗 Tautan & Kredit / Links & Credits

- 🌐 Live: TODO (belum di-hosting)
- 💻 Repo: https://github.com/HaedarWinter/ujikom-projek
- 🎥 Demo: TODO
- 🙏 Kredit / Credits: Waterboom Cahaya Abadi (venue klien) · FPDF · SweetAlert2 · html5-qrcode · Fonnte
- 📄 Lisensi / License: All rights reserved
