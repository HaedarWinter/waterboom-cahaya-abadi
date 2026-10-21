# Laporan Audit — ujikom1 (Fase 1)

Tanggal: 21-10-2026 · Branch: `audit/hardening` · Metode: telaah manual (file+baris) sesuai PRD §9.
Baseline: commit `0672084` ("baseline: snapshot before audit hardening").

## Ringkasan

| ID | Severity | Kategori | Lokasi | Status |
|---|---|---|---|---|
| CR-01 | Critical | AUTHZ | `admin/index.php:2-4` | Open |
| HI-01 | High | INP/RACE | `cek_booking.php:14-67` | Open |
| HI-02 | High | INP | `admin/audit_keuangan.php:319` | Open |
| HI-03 | High | INP | `admin/logs.php:132-143` | Open |
| HI-04 | High | AUTH | `admin/handlers/cek_login.php:66-79` | Open |
| HI-05 | High | RACE | `admin/handlers/reservasi_handler.php` + `app/Models/Reservasi.php` | Open |
| HI-06 | High | RACE | `cek_booking.php:141-143` | Open |
| HI-07 | High | INP | `cek_booking.php` (injeksi `$error` ke JS via `addslashes`) | Open |
| ME-01 | Medium | API/CSRF | `admin/handlers/hapus.php` (get-delete; endpoint dead, prkatik CORS) | Open |
| ME-02 | Medium | DATA/ARCH | `config/koneksi.php:8` + `app/Core/Database.php:11` (duplikasi + socket hardcode) | Open |
| ME-03 | Medium | DATA | `admin/handlers/cek_login.php:4-5,55` (display_errors + detail internal) | Open |

## Detail

> Update Fase 3/Fase 4: semua temuan sudah Fixed — status disalin di `docs/audit/03-verification.md`. Koreksi lokasi HI-06: logika hapus booking ada di `admin/handlers/reservasi_handler.php:141-143` (bukan cek_booking.php).

### [CR-01] Dashboard admin dapat diakses tanpa login
- **Severity:** Critical · **Kategori:** AUTHZ · **Lokasi:** `admin/index.php:2-4`
- **Deskripsi:** Hanya `check_session_timeout()` + `enforce_active_session()`. Kedua fungsi no-op jika tamu belum login (tak ada `$_SESSION['admin_id']`).
- **Dampak:** Statistik pendapatan, grafik, feed, dan seluruh data buku tamu (nama, no HP, email) terbaca publik tanpa login.
- **Bukti:** `config/security.php:29-44` — blok `if (isset($_SESSION['admin_id']))` kosong jalannya untuk tamu.
- **Rekomendasi:** Tambah guard eksplisit: redirect ke login bila `$_SESSION['admin_id']` tidak ada.
- **Estimasi:** S · **Rujukan:** OWASP A01:2021 (Broken Access Control), CWE-862.
- Halaman admin lain (reservasi, galeri, faq, dst.) aman karena `has_permission()` mengembalikan `false` untuk tamu.

### [HI-01] Upload bukti bayar tervalidasi lembar ekstensi
- **Severity:** High · **Kategori:** INP · **Lokasi:** `cek_booking.php:14-67`
- **Deskripsi:** MIME dicek via finfo (ok) namun ekstensi asli file user dipertahankan apa adanya; nama file simpan tidak sepenuhnya di-generate ulang; folder `assets/bukti_bayar/` web-accessible.
- **Dampak:** Kemungkinan file berlangkam `.php`-look-alike dsb pada web-server yang pelonggar ekstensinya.
- **Rekomendasi:** Whitelist ekstensi aplikasi (jpg/png/webp) + nama file final 100% di-generate ulang dari kode booking.
- **Estimasi:** S · **Rujukan:** CWE-434 (Unrestricted File Upload).

### [HI-02][HI-03] Stored XSS di halaman admin
- **Kategori:** INP · **Lokasi:** `admin/audit_keuangan.php:319` (nama file lampiran pada atribut onclick, tanpa escape) dan `admin/logs.php:132-143` (`target_table`, `ip_address`, `action` di-echo mentah).
- **Dampak:** Eksekusi JS di sesi admin dari input yang maintainer tak sengaja bisa berisi script.
- **Rekomendasi:** Escape dengan `e()` / `htmlspecialchars` (helper `e()` sudah tersedia di `app/init.php`).
- **Estimasi:** S · **Rujukan:** CWE-79 (XSS).

### [HI-04] Fallback password plaintext
- **Kategori:** AUTH · **Lokasi:** `admin/handlers/cek_login.php:66-79`
- **Deskripsi:** `$password === $db_pass` diterima sebagai password sah + auto-upgrade ke hash.
- **Dampak:** Jika ada kolom password di tabel yang belum ter-hash, lemah bisa login; pola plaintext bertahan.
- **Bukti:** sudah hash bcrypt di `sql/db.sql:29` → fallback tak diperlukan.
- **Rekomendasi:** Hapus fallback + blok auto-upgrade, paksa hanya `password_verify()`.
- **Estimasi:** S · **Rujukan:** CWE-256.

### [HI-05] Kuota tiket check-then-act (race condition)
- **Kategori:** RACE · **Lokasi:** cek kuota di handler + insert `Reservasi::create()` tidak dilakukan atomik.
- **Dampak:** Dua request paralel bisa lolos cek kuota bersamaan → oversell (penjualan melebihi kuota harian).
- **Rekomendasi:** Tambah kolam atomic guard `UPDATE kuota_harian SET terpakai = terpakai + n WHERE tanggal = ? AND terpakai + n <= jumlah` lalu cek `affected_rows`. (Pola contoh §10 PRD.)
- **Estimasi:** M · **Rujukan:** RACE-1/RACE-2.

### [HI-06] Double-decrement kuota
- **Kategori:** RACE · **Lokasi:** `cek_booking.php:141-143`
- **Deskripsi:** Hapus booking hanya cek `status !== 'Ditolak'`; status `Dibatalkan` lolos → kuota terpakai berkurangi 2x (sekali di `Reservasi::updateStatus()`, sekali di sini).
- **Rekomendasi:** Tambah `Dibatalkan` ke daftar status yang tidak mengurangi kuota.
- **Estimasi:** S · **Rujukan:** RACE-5.

### [HI-07] Injeksi data user ke konteks JS via `addslashes()`
- **Kategori:** INP · **Lokasi:** `cek_booking.php` (pesan error berisi kode booking mentah di-inject ke `<script>`).
- **Rekomendasi:** Gunakan `json_encode()` (safe JS escaping).
- **Estimasi:** S · **Rujukan:** CWE-79.

### [ME-01] Endpoint hapus via GET tanpa CSRF (dead, tapi berbahaya bila dipanggil langsung)
- **Kategori:** API/CSRF · **Lokasi:** `admin/handlers/hapus.php`
- **Deskripsi:** Delete buku tamu bisa dipicu via `GET ?id=` tanpa CSRF. Grep menunjukkan tak ada UI yang memanggilinya — dead endpoint, tapi tetap accessible URL.
- **Rekomendasi:** POST-only + CSRF token (pola yang sama dipakai semua handler lain).
- **Estimasi:** S · **Rujukan:** API-2, INP-4, CWE-352.

### [ME-02] Duplikasi koneksi + path socket hardcode
- **Kategori:** DATA/ARCH · **Lokasi:** `config/koneksi.php:8` vs `app/Core/Database.php:11`
- **Deskripsi:** Kedua file mendeklarasikan koneksi mysql identik dengan path socket `"Applications/XAMPP/.../mysql.sock"` hardcode → pindah server akan gagal.
- **Rekomendasi:** `koneksi.php` delegasi ke singleton `App\Core\Database` (hapus duplikasi).
- **Estimasi:** S · **Rujukan:** ARCH-3, DATA-10.

### [ME-03] Detail error internal ke browser
- **Kategori:** DATA · **Lokasi:** `admin/handlers/cek_login.php:4-5,55` dan `config/koneksi.php:12`
- **Rekomendasi:** Matikan `display_errors`; pesan error koneksi generik; detail cukup di log server.
- **Estimasi:** S · **Rujukan:** DATA-6.

## Temuan yang TIDAK jadi dibebaskan (won't fix / backlog)
- Hardcode `Location: /ujikom1/...` di banyak handler (pakai `base_url()` lewat kami tak berubah perilaku; backlog ARCH-4).
- JWT/MFA/CORS/OpenAPI — tidak berlaku (session-based, XAMPP, tidak ada konsumen API eksternal). Tercatat sesuai PRD Non-Goals.
- Rate limiter session-based bypassable — mitigasi sudah di DB (`login_attempts`) untuk login; sisanya backlog.
- `api/` (root) folder kosong — dihapus saat Fase 3 (ARCH-4).
