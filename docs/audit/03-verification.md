# Plan Remediasi & Verifikasi — ujikom1 (Fase 2–4)

## Paket perbaikan (urutan eksekusi sesuai PRD §)

| # | Temuan | Fix | Estimasi |
|---|---|---|---|
| 1 | CR-01 | `require_admin()` guard di `config/security.php` + dipanggil `admin/index.php` | S |
| 2 | ME-01 | `hapus.php` POST-only + CSRF + `id > 0` | S |
| 3 | HI-02/03 | escape `e()`/`htmlspecialchars` di `audit_keuangan.php`, `logs.php` | S |
| 4 | HI-01 | whitelist MIME→ekstensi + nama file di-generate ulang via kode booking | S |
| 5 | HI-07 | `json_encode()` untuk pesan ke JS (ganti `addslashes`) | S |
| 6 | HI-04 | fallback plaintext password + auto-upgrade dihapus (`cek_login.php`) | S |
| 7 | HI-05 | `Reservasi::create()` jadi transaksi + `FOR UPDATE` (anti oversell) | M |
| 8 | HI-06 | hapus reservasi: `Dibatalkan` ikut dikecualikan (anti double-decrement) | S |
| 9 | ME-02 | `koneksi.php` delegasi ke `App\Core\Database` + fallback socket portabel | S |
| 10 | ME-03 | `display_errors` mati; pesan error generik, detail ke `error_log` | S |
| 11 | ARCH-4 | folder `api/` (kosong) dihapus | S |

## Hasil verifikasi (Fase 4)

`php tests/verify_hardening.php` → **7 PASS / 0 FAIL**:
- CR-01 dashboard tanpa login → redirect, data tak bocor
- ME-01 GET-delete → 302, tolak
- RACE-2 booking hingga kuota habis sukses; request ke-9 dicegah (null); counter tak over
- HI-06 kuota balik ke 0 setelah Dibatalkan; idempotent (update ulang tak mengurangi lagi)
- Cleanup data tes otomatis (tabel kuota_harian + reservasi tanggal `2099-01-01`)

Regresi halaman publik (HTTP): `index.php`, `reservasi.php`, `cek_booking.php`, `login.php` → semua 200.
`php -l` lulus untuk 11 file yang diubah.

## Risiko sisa (residual)

1. Booking Ditolak → user upload ulang bukti → status balik Pending, kuota TIDAK diambil kembali. Slot bisa terlepas ke booking lain; konfirmasi admin bisa melampaui kuota. Perbaikan backlog (butuh keputusan produk).
2. `Location: /ujikom1/...` hardcode masih ada di handler lama — backlog ARCH-4.
3. Grep UI dashboard tidak memanggil `hapus.php` lagi (JS `.swal-hapus` orphan) — file dipertahankan sesuai README; siapkan form POST bila sukses digunakan.
4. Scanner handler & notifications API tak memanggil `enforce_active_session` (admin nonaktif masih akses sampai session habis) — backlog.
5. `.DS_Store`/`.vscode` sudah masuk `.gitignore`.
