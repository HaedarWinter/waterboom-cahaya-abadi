# DEPLOYMENT.md: Deploy Gratis untuk Demo Portfolio

**Tujuan:** menaruh Waterboom Cahaya Abadi di internet tanpa biaya, supaya bisa dibuka dari card portfolio.
**Terakhir diverifikasi:** 22 Oktober 2026. Syarat free tier berubah cepat, jadi **cek ulang halaman resmi penyedia sebelum mendaftar**.

> Singkatnya: **ya, perlu di-deploy** agar pengunjung portfolio bisa mencobanya langsung. Cadangannya: video demo + screenshot di card, dan link live sebagai tombol tambahan.

---

## 1. Pilihan yang Realistis

| Opsi | Cocok untuk | Catatan penting |
|---|---|---|
| **A. InfinityFree** (PHP + MySQL satu paket) | Cara tercepat & benar-benar gratis untuk PHP native | Lihat batasan di bagian 2 |
| **B. Hosting PHP gratis lain** (mis. ByetHost, FreeHosting) | Cadangan bila A bermasalah | Batas berbeda-beda (disk, DB, versi PHP); cek versi PHP **≥ 8.1** |
| **C. DB MySQL-compatible terpisah** (mis. TiDB Cloud Starter) + hosting PHP | Bila ingin DB lebih longgar | TLS wajib; **tidak mendukung procedure/trigger**; perlu uji ulang tes konkurensi; belum tentu bisa dijangkau dari hosting gratis (port & firewall) |
| **D. Container gratis** (Docker PHP di platform yang punya free allowance) | Bila siap membuat `Dockerfile` | Syarat free tier berubah-ubah dan bisa *sleep*; verifikasi dulu |
| **E. Lokal + video** | Cadangan terakhir | Tidak bisa dicoba pengunjung |

**Rekomendasi:** mulai dari **A** untuk demo portfolio. Siapkan video/screenshot sebagai cadangan.

## 2. Batasan InfinityFree yang Memengaruhi Desain

Berdasarkan dokumentasi/forum resmi InfinityFree (diperiksa Okt 2026):

| Batasan | Dampak ke projek | Solusi di PRD |
|---|---|---|
| **Cron dinonaktifkan** di paket gratis | Job kedaluwarsa tidak bisa terjadwal | *Lazy expiry* + endpoint bertoken (FLOW-4) |
| **Akses masuk otomatis (API/webhook/curl ke situs) diblokir** oleh sistem keamanan browser | Pemicu cron eksternal & webhook masuk tidak bisa diandalkan | Jangan bergantung pada webhook masuk; demo tanpa payment gateway |
| **Batas 50.000 hits/hari** per akun (situs offline 24 jam jika terlampaui) | Setiap file CSS/JS/gambar bisa menghitung sebagai hit | Bundel CSS/JS, cache, kompres gambar, pakai Cloudflare bila memungkinkan |
| **Batas proses PHP bersamaan** | Lonjakan trafik bisa menolak request | Ringan di tiap request; rate limit; jangan taruh link demo di tempat viral |
| Request **keluar** dari situs ke API eksternal umumnya diperbolehkan, tetapi pernah ada pembatasan cURL | Pengiriman WA/ API luar bisa gagal | **Mode demo memakai driver log** (tanpa Fonnte) |
| Tidak ada akses root, tidak ada kontrol hak DB granular | Tidak bisa mencabut `UPDATE/DELETE` pada ledger di level DB | Perlindungan di level aplikasi + tes (5.3 PRD) |
| Struktur `htdocs` sebagai akar | Tidak bisa menaruh kode **di luar** web root | Lihat bagian 3 |

## 3. Struktur di Hosting Bersama (tidak bisa di luar web root)

Idealnya hanya `public/` yang terbuka. Jika host hanya punya satu folder `htdocs/`:

```
htdocs/
├── index.php            # = isi public/index.php (path require disesuaikan)
├── assets/              # = public/assets (hasil bundel)
├── .htaccess            # rewrite ke index.php
└── _app/                # SEMUA kode lain di sini
    ├── .htaccess        # Require all denied  (atau: deny from all)
    ├── app/  config/  resources/  routes/  database/  storage/  scripts/
    └── .env             # tidak dapat diakses web karena _app dilarang
```

- `_app/.htaccess` wajib berisi aturan **tolak semua**. Uji dengan membuka `https://situsmu/_app/.env`, harus **403/404**.
- `storage/` berisi bukti bayar & log: pastikan tidak bisa diakses langsung lewat URL.
- Atur `APP_ENV=demo`, `APP_DEBUG=0`, dan kredensial DB hosting di `.env` (jangan commit).

## 4. Langkah Deploy (Opsi A, garis besar)

1. **Daftar** akun hosting gratis, buat situs (subdomain bawaan atau domain sendiri), buat database MySQL (catat host, nama DB, user, password).
2. **Siapkan build lokal:** `php scripts/build_assets.php` → hasilkan `app.min.css`/`app.min.js`.
3. **Impor DB:** lewat phpMyAdmin hosting: jalankan migrasi berurutan (`database/migrations/V4_*.sql`) lalu seeder `roles`, `permissions`, `cash_accounts`, `demo_data`.
4. **Unggah file** (FTP/File Manager) sesuai struktur bagian 3.
5. **Buat `.env`** di `_app/` dengan `APP_ENV=demo`, kredensial DB, `APP_URL`, secret acak untuk signed URL & token cron.
6. **Aktifkan HTTPS** (SSL gratis dari panel hosting) lalu pastikan cookie sesi `Secure`.
7. **Uji pasca-deploy:** `/health` OK; `/_app/.env` ditolak; alur penuh (booking → simulasi bayar → verifikasi → tiket → scan); login tiap peran demo; reset demo.
8. **Pasang link** di card portfolio, plus badge "Demo Mode".

## 5. Checklist Keamanan Sebelum Link Dipublikasikan

- [ ] `APP_ENV=demo`; WA memakai driver log (tidak memanggil Fonnte)
- [ ] Tidak ada data pengunjung asli, rekening asli, atau secret di repo & DB
- [ ] Password seed default **bukan** yang dipakai di produksi apa pun; akun demo hanya untuk demo
- [ ] `_app/`, `.env`, `storage/` tidak dapat diakses via URL
- [ ] Debug/error detail mati; log tidak berisi PII
- [ ] Rate limit aktif pada login, booking, upload, cek booking
- [ ] Unggahan dibatasi ukuran & dibersihkan saat reset
- [ ] Fitur berisiko dimatikan (ekspor massal, ubah rekening, hapus akun)
- [ ] Banner "Mode Demo" tampil; peringatan jangan isi data pribadi asli
- [ ] Reset otomatis data demo berjalan (lazy) dan tombol reset tersedia untuk super_admin demo

## 6. Bila Memakai DB Terpisah (Opsi C)

- TiDB Cloud Starter kompatibel dengan protokol MySQL dan menyediakan kuota gratis bulanan; koneksi **wajib TLS**. Kuota dan syarat dapat berubah, jadi verifikasi di situs resminya.
- **Tidak ada procedure/trigger** → sejalan dengan ARCH-6 (logika di PHP). Hapus semua view/procedure dari `migration_v3_state_machine.sql`.
- Perilaku transaksi & penguncian bisa berbeda dari MySQL biasa → **jalankan ulang seluruh tes konkurensi** di DB tersebut sebelum dipakai.
- Pastikan hosting PHP bisa membuat koneksi **keluar** ke host/port DB tersebut (hosting gratis sering membatasi port non-standar). Uji sederhana dengan skrip koneksi sebelum memigrasikan semuanya.
- Latensi antar-region memperlambat query; pilih region terdekat yang tersedia (mis. Singapura bila ada).

## 7. Backup & Pemeliharaan Demo

- Ekspor DB berkala lewat phpMyAdmin dan simpan salinan lokal (bukan di repo).
- Simpan `seeders/demo_data` di repo agar demo bisa dipulihkan kapan saja.
- Pantau penggunaan hits/proses di panel hosting; jika mendekati batas, kurangi aset atau aktifkan cache/CDN.
- Catat tanggal terakhir verifikasi syarat hosting di dokumen ini.

## 8. Cara Menampilkannya di Portfolio

| Elemen card | Isi |
|---|---|
| **Tombol utama** | "Coba Demo" → URL live (dengan petunjuk akun demo per peran) |
| **Tombol kedua** | "Lihat Repo" |
| **Cadangan** | Video demo 60–90 detik + 4–6 screenshot (alur pengunjung, verifikasi, scanner, buku kas) |
| **Keterangan** | "Mode demo: data dummy, pesan WhatsApp disimulasikan" |
| **Sumber data card** | Blok frontmatter `PROJECT_STORY.md` (judul, tagline, tags, metrik, tautan) |
| **Nilai tambah** | Badge CI (tes lolos), link `docs/` (audit, ERD, ADR) |

Hindari menanam (iframe) demo langsung di portfolio: situs gratis sering memakai pemeriksaan browser dan batas trafik, sehingga lebih aman dibuka di tab baru.
