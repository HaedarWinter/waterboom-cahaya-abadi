# AGENTS.md: Aturan Kerja untuk Agent

Berlaku untuk semua agent (Claude Code, Codex, Cursor, dll.) yang mengerjakan projek **Waterboom Cahaya Abadi**.
Baca file ini **sebelum menyentuh kode**. Sumber kebenaran produk ada di `PRD.md`; file ini adalah cara kerjanya.

---

## 1. Konteks Singkat

- **Apa:** sistem reservasi tiket, check-in QR, dan keuangan (buku kas) untuk waterpark.
- **Stack:** PHP 8.x native (tanpa framework), MySQL/MariaDB, SweetAlert2, Chart.js, Lucide.
- **Bahasa:** UI & pesan untuk pengguna dalam **Bahasa Indonesia**; kode, nama class/fungsi/tabel dalam **Inggris**; komentar dan dokumen boleh Indonesia atau Inggris, tapi konsisten per file.
- **Tujuan:** aman, rapi, skalabel, dan layak sebagai **demo portfolio** yang bisa di-deploy gratis.
- **Status struktur:** sedang direkonstruksi bertahap (*strangler*). Halaman lama dan modul baru bisa hidup berdampingan. Cek `PRD.md` bagian 11 untuk fase yang sedang berjalan.

## 2. Aturan Emas (tidak boleh dilanggar)

1. **Status booking hanya diubah oleh `BookingStateMachine::transition()`.** Jangan `UPDATE ... SET status` di tempat lain.
2. **Setiap uang masuk/keluar = satu entri `ledger_entries`**, dibuat oleh `LedgerService` di dalam transaksi yang sama dengan perubahan sumbernya.
3. **`ledger_entries`, `activity_logs`, dan bukti pembayaran tidak pernah di-UPDATE atau di-DELETE.** Koreksi = entri pembalik. Rekonsiliasi memakai tabel terpisah.
4. **Semua query memakai prepared statement.** Tidak ada penggabungan string ke SQL.
5. **Semua output di-escape** (`e()`); tidak ada `innerHTML` dari input pengguna.
6. **Setiap route tulis (POST/PUT/DELETE) wajib melewati middleware berurutan:** sesi aktif → CSRF → izin → validasi. Default **tolak**.
7. **Cek izin di server**, bukan hanya menyembunyikan tombol.
8. **Tidak ada logika bisnis di DB** (procedure/trigger/view berlogika) dan **tidak ada logika bisnis di controller atau view**.
9. **Jangan mengarang data.** Jika informasi tidak ada, tulis `TODO: tanya owner` dan lanjutkan hal lain.
10. **Jangan pernah mencetak/commit secret, token, password asli, atau data pengunjung asli.** Gunakan `[REDACTED]` dan data dummy.

## 3. Yang Harus Ditanyakan ke Owner Dulu (jangan diputuskan sendiri)

- Kebijakan refund/reschedule, batas waktu bayar, perlakuan kode unik.
- Aturan **pajak** dan pengakuan pendapatan (`TODO: konfirmasi akuntan`).
- Menghapus data produksi, `DROP/TRUNCATE`, rotasi kredensial, `force push`.
- Menambah **dependensi baru** (Composer/npm/CDN).
- Mengubah kontrak yang dipakai pihak luar (mis. format pesan WA, URL tiket).
- Mengubah hak akses suatu peran di luar `PRD.md` bagian 3.2.

## 4. Peta Folder & Tanggung Jawab

```
public/            satu-satunya web root (index.php, assets/)
app/Core/          Router, Request, Response, View, Database, Session, Csrf, Validator, Env
app/Http/Controllers/{Site,Admin}/   tipis: input → validasi → service → respons
app/Http/Middleware/                 Auth, ActiveSession, Csrf, Permission, RateLimit, SecurityHeaders
app/Http/Requests/                   validasi per use case
app/Domain/{Booking,Payment,Refund,Finance,Access,Notification,Audit,Content}/
                   aturan bisnis (Service) + SQL (Repository)
app/Support/       Money, Pagination, Csv, Clock, Str, SignedUrl
resources/views/   layouts, components, partials, site, admin (tanpa logika bisnis)
routes/            site.php, admin.php, internal.php
config/            app, database, permissions, roles, states  (data, bukan kode)
database/          migrations/, rollbacks/, seeders/
storage/           proofs, cache, logs  (TIDAK web-accessible)
scripts/           cron_expire, integrity_check, build_assets, reset_demo
tests/             Unit, Integration, Concurrency, Permission, Static, run.php
docs/              ARCHITECTURE, DEPLOYMENT, ERD, adr/, audit/
```

**Tempat menaruh sesuatu:**

| Jika Anda menulis… | Letakkan di… |
|---|---|
| Aturan bisnis / transaksi | `app/Domain/<Area>/*Service.php` |
| Query SQL | `app/Domain/<Area>/*Repository.php` |
| Validasi input form | `app/Http/Requests/` |
| Halaman / potongan HTML | `resources/views/` (komponen kecil di `components/`) |
| Aturan status & izin | `config/states.php`, `config/permissions.php` |
| Skema DB | file baru di `database/migrations/` **+** rollback |
| Gaya tampilan | `public/assets/css/` sesuai lapisan atomic (lihat §7) |

## 5. Batas Ukuran & Kerapian (penegak anti-"file menumpuk")

| Jenis | Batas |
|---|---|
| Class PHP | ≤ **300 baris** |
| Method | ≤ **40 baris** (di controller idealnya ≤ 25) |
| File view | ≤ **200 baris** (pecah ke `components/`/`partials/`) |
| File CSS | ≤ **200 baris**, satu komponen per file |
| Modul JS | ≤ **250 baris**, tanpa JS inline di view |
| Satu PR/komit | **satu tema**, idealnya < 400 baris diff |

Melebihi batas = pecah dulu, baru lanjut. Jika terpaksa melebihi, jelaskan alasannya di deskripsi PR.

## 6. Konvensi Kode

- `declare(strict_types=1);` di semua file PHP baru; namespace `App\...` sesuai path (PSR-4 gaya sendiri).
- Penamaan: class `PascalCase`, method/variabel `camelCase`, tabel & kolom `snake_case`, konstanta `UPPER_SNAKE`.
- Uang: **integer rupiah** (`Money` helper). Dilarang `float` untuk uang.
- Waktu: gunakan `Clock` (bisa di-*freeze* di tes), simpan UTC atau zona yang konsisten sesuai `config/app.php`.
- Error: lempar exception domain (`DomainException` turunan); controller menerjemahkan ke respons. Pesan ke pengguna generik; detail ke log.
- Tidak ada `die()/exit()` di luar `Core/Response` dan skrip CLI. Tidak ada `echo` di service.
- Pagination: pakai `Support\Pagination` + komponen view `pagination`; jangan menulis ulang manual.
- Komentar menjelaskan **kenapa**, bukan **apa**.

## 7. Aturan Frontend (CSS Atomic & Design System)

Lapisan: `0-tokens → 1-base → 2-layout → 3-atoms → 4-molecules → 5-organisms → 6-pages → 7-utilities`, diurutkan dengan `@layer` di `app.css`.

1. **Gunakan token.** Warna, spasi, radius, ukuran font dari CSS variable. Dilarang hex/px sembarang di komponen.
2. **Satu komponen = satu file**, penamaan BEM-lite berprefiks: `.c-button`, `.c-button--primary`, `.c-button__icon`; layout `.l-*`; utilitas `.u-*`; state `.is-*`/`.has-*`.
3. **Tanpa `!important`**, tanpa selector > 3 tingkat.
4. **Cek komponen yang ada** sebelum membuat yang baru. Buat komponen baru hanya bila pola dipakai ≥ 2 tempat.
5. **Dark mode lewat token tema** (`[data-theme="dark"]`); komponen tidak boleh punya warna sendiri.
6. **Mobile-first** (`min-width`). Tombol/target sentuh ≥ 44px.
7. **Aksesibilitas:** kontras ≥ 4.5:1, fokus terlihat, label pada input, `aria-live` untuk toast.
8. Warna & label status dibaca dari `config/states.php` lewat komponen `status-badge`. Jangan hardcode.
9. Produksi memakai bundel hasil `scripts/build_assets.php` (jumlah request kecil). Jangan menambah `<link>`/`<script>` per file di layout produksi.
10. JS: modul ES, dimuat lewat `data-module`, tanpa inline script, library di-*pin* versinya (SRI) atau di-host sendiri.

## 8. Database & Migrasi

- Skema hanya berubah lewat **file migrasi baru** `database/migrations/V4_NNN_deskripsi.sql` + `database/rollbacks/V4_NNN_*.sql`.
- Migrasi harus **idempoten** (`IF NOT EXISTS`, cek kolom) dan aman dijalankan ulang.
- **Backup dulu**, uji di salinan DB, baru terapkan.
- Jangan mengedit migrasi lama yang sudah dipakai; buat migrasi baru.
- Uang `BIGINT`; `InnoDB`; `utf8mb4`; FK & indeks eksplisit; unique constraint sebagai pengaman terakhir (mis. `uq_ledger_source`).
- Jangan menulis procedure/trigger/view berlogika. Kompatibilitas dengan hosting gratis & TiDB bergantung pada ini.
- Seeder `demo_data` hanya berisi data **dummy**.

## 9. Keamanan & Konsistensi (checklist sebelum setiap PR)

- [ ] Input divalidasi di server (tipe, panjang, enum), upload divalidasi isi file
- [ ] Query prepared; output ter-escape
- [ ] POST punya CSRF + izin + sesi aktif; logout & ekspor juga
- [ ] Cek kepemilikan/izin pada resource (cegah IDOR); data pribadi dimasking sesuai peran
- [ ] Operasi kritis **atomik** (transaksi + `FOR UPDATE` atau update kondisional); idempoten bila bisa diulang
- [ ] Efek ganda dicegah (unique constraint; kunci idempotensi untuk notifikasi/job)
- [ ] Aksi sensitif tercatat di `activity_logs` dengan nilai sebelum/sesudah
- [ ] Tidak ada secret, path absolut OS, atau data asli di kode/log/komit
- [ ] Respons error generik; tidak ada stack trace ke pengguna
- [ ] Rate limit pada rute publik sensitif

## 10. Pengujian

- Tidak ada fitur aturan-bisnis tanpa tes. **Tulis tes yang gagal dulu**, lalu perbaiki, lalu pastikan lolos.
- Jalankan: `php tests/run.php` (semua), `php tests/run.php --suite=Concurrency`, `php scripts/integrity_check.php`.
- Perubahan pada: state machine, ledger, kuota, izin, atau scan **wajib** menjalankan suite terkait (Unit + Concurrency + Permission) dan `integrity_check.php`.
- Tes konkurensi tidak boleh di-skip untuk membuat CI hijau; jika flaky, perbaiki penyebabnya.
- Data tes = dummy; jangan menyentuh DB produksi/demo publik.

## 11. Alur Kerja Agent

1. **Baca** `PRD.md` (bagian relevan), `docs/audit/gap-analysis.md`, dan kode terkait.
2. **Verifikasi** klaim di dokumen terhadap kode; jika berbeda, **laporkan selisih**, jangan asumsikan.
3. **Rencanakan** singkat: file yang akan diubah, risiko, tes yang akan ditulis.
4. **Tulis tes** → **implementasi minimal** → **jalankan tes** → **refactor kecil**.
5. **Komit kecil** dengan format di bawah; satu tema per komit.
6. **Perbarui** dokumen terkait (PRD jika aturan berubah, `CHANGELOG.md`, ERD bila skema berubah).
7. **Laporkan**: apa yang diubah, bukti tes, risiko tersisa, pertanyaan untuk owner.

**Format komit:** `tipe(ID-kebutuhan): ringkasan`
Contoh: `fix(R-02): pindahkan rekonsiliasi ke ledger_reconciliations`, `feat(AUTHZ-1): tabel roles & permissions`, `test(FLOW-3): tes expire paralel`, `refactor(ARCH-1): pecah ReservationController`, `docs(PRD): perjelas T11`.

## 12. Strategi Rekonstruksi (Strangler)

- Jangan menulis ulang semuanya sekaligus. Pindahkan **satu modul per fase** ke struktur baru dengan **tes paritas** (perilaku lama = baru).
- Halaman lama dipertahankan di belakang route map sampai versi baru lolos tes, lalu dihapus (dengan komit terpisah).
- Hapus handler generik (`hapus.php`, `simpan.php`, `export.php`) hanya setelah semua pemanggilnya dipindah ke controller per resource.
- Tandai kode peninggalan dengan `// LEGACY(R-xx): alasan` agar mudah dilacak.

## 13. Resep Umum

**Menambah modul admin baru**
1. Tambah izin di `config/permissions.php` (+ seed ke peran terkait).
2. Buat `Repository` → `Service` → `Controller` → `Request` → view → route (dengan middleware izin).
3. Tambah entri menu sesuai izin.
4. Tulis tes izin (matriks) + tes fungsional.
5. Catat aksi sensitif ke `ActivityLogger`.

**Menambah status/transisi booking**
1. Ubah `config/states.php` (pelaku, izin, guard, efek).
2. Perbarui tabel 4.2 di `PRD.md`.
3. Perbarui `status-badge` (warna & label).
4. Tes tabel transisi + tes alur + `integrity_check`.

**Menambah komponen UI**
1. Cek komponen yang ada; jika belum ada dan dipakai ≥ 2 tempat → buat file di lapisan yang tepat (atom/molekul/organisme).
2. Hanya token; BEM-lite; ≤ 200 baris.
3. Buat partial di `resources/views/components/` dan dokumentasikan contoh pemakaian di komentar kepala file.

**Menambah migrasi**
1. `database/migrations/V4_NNN_*.sql` + `rollbacks/` pasangannya; idempoten.
2. Uji di salinan DB; jalankan `integrity_check`.
3. Perbarui `docs/ERD.md`.

## 14. Mode Demo (`APP_ENV=demo`)

- WhatsApp memakai **driver log**; dilarang memanggil Fonnte.
- Banner "Mode Demo", data dummy, akun demo per peran, reset otomatis berkala.
- Fitur berisiko dimatikan (ekspor massal, ubah rekening tujuan, hapus akun).
- Jangan bergantung pada **cron** atau **webhook masuk** (hosting gratis sering memblokirnya): gunakan lazy expiry dan endpoint bertoken.

## 15. Larangan Tegas

- ❌ Mengubah status booking selain lewat `BookingStateMachine`
- ❌ UPDATE/DELETE pada ledger, activity log, atau bukti bayar
- ❌ Logika bisnis di controller, view, atau database
- ❌ Hardcode warna/ukuran di CSS komponen; `!important`
- ❌ JS inline; `innerHTML` dengan input pengguna
- ❌ Menambah dependensi tanpa persetujuan
- ❌ Menjalankan perintah destruktif tanpa konfirmasi dan backup
- ❌ Mengarang angka, testimoni, atau fakta pajak/hukum
- ❌ Memasukkan data pribadi/secret asli ke repo, log, laporan, atau demo
- ❌ Mem-bypass tes (skip/komentar) agar lolos

## 16. Definition of Done (per tugas)

- [ ] Perilaku sesuai `PRD.md` (sebutkan ID kebutuhan)
- [ ] Tes baru/terkait lolos; suite relevan + `integrity_check` hijau
- [ ] Checklist keamanan (§9) terpenuhi
- [ ] Batas ukuran (§5) & aturan frontend (§7) terpenuhi
- [ ] Dokumen/CHANGELOG diperbarui
- [ ] Tidak ada data/secret asli; tidak ada kode mati atau `TODO` tak berpemilik
- [ ] Laporan akhir menyebut risiko tersisa & pertanyaan untuk owner

## 16b. Template Perintah ke Agent (salin-tempel)

```
Baca AGENTS.md dan PRD.md. Kerjakan: <tugas / fase>.
Ikuti §11 (alur kerja). Verifikasi dulu ke kode, laporkan selisih dengan PRD.
Mulai dengan tes yang gagal, komit kecil per tema, rujuk ID kebutuhan.
Jangan ubah di luar lingkup; tanyakan hal di §3 sebelum memutuskan.
Di akhir: ringkasan perubahan, bukti tes, risiko tersisa, pertanyaan untuk saya.
```
