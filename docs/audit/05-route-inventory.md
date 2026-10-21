# Route Inventory — Waterboom Cahaya Abadi

**Fase:** 0 (pemetaan)  
**Tanggal:** 22 Oktober 2026  
**Baseline commit:** `2192cf8`  
**Cakupan:** seluruh entry point PHP yang dapat diakses lewat HTTP, termasuk halaman, handler, API, dan route mati.

> Inventaris ini dibaca dari kode, bukan dari konfigurasi web server. Semua file PHP di root dan folder `admin/` dapat dipanggil langsung karena tidak ada front controller.

> Pembaruan 22 Oktober 2026: state machine v3 diterapkan. `update_status` aktif terbatas (batal/tidak hadir), `action=hapus` ditolak, dan verifikasi/tolak/check-in memakai `BookingStateService`. DDL runtime `laporan_keuangan` sudah dihapus dari `admin/audit_keuangan.php` dan `admin/handlers/keuangan_handler.php`. Skema kini berasal dari `sql/migration_v3_foundation.sql`.

---

## 1. Ringkasan

| Kelompok | Jumlah | Catatan |
|---|---|---|
| Halaman publik | 5 | `index.php`, `reservasi.php`, `cek_booking.php`, `login.php`, `upload_bukti.php` |
| Halaman admin | 14 | Dashboard, reservasi, edit tamu, galeri, berita, FAQ, ulasan, pengaturan, keuangan, scanner, log, kelola admin, logout |
| Handler | 12 | Sebagian melayani publik dan admin sekaligus |
| API | 1 | `admin/api/notifications.php` |
| Route mati/no-op | 3 | `hapus.php`, `upload_bukti.php`, JS `swal-hapus` |

---

## 2. Route Publik

| Method | Path | Aksi | Guard | CSRF | Handler/Model | Status |
|---|---|---|---|---|---|---|
| GET | `/index.php` | Landing page, daftar galeri/berita/FAQ/ulasan, form buku tamu | Tidak ada | Ya (form) | `App\Models\*` | Aktif |
| POST | `/admin/handlers/simpan.php` | Simpan buku tamu | Tidak ada; honeypot + rate limit | Ya | `buku_tamu` | Aktif (dipakai form publik) |
| POST | `/admin/handlers/review_handler.php` `action=simpan` | Kirim ulasan baru | Tidak ada; honeypot + rate limit | Ya | `reviews` | Aktif |
| GET | `/reservasi.php` | Form reservasi | Tidak ada | Ya | `App\Models\Setting` | Aktif |
| POST | `/admin/handlers/reservasi_handler.php` `action=simpan` | Buat reservasi | Tidak ada; honeypot + rate limit | Ya | `App\Models\Reservasi::create` | Aktif |
| POST | `/admin/handlers/reservasi_handler.php` `action=cek_kuota` | Cek sisa kuota (AJAX) | Tidak ada | Tidak | `Reservasi::cekKuota` | Aktif; mengembalikan JSON `success/max/terisi/sisa` |
| GET | `/cek_booking.php` | Cari booking berdasarkan kode | Tidak ada | Ya (form upload) | `Reservasi::getByKode` | Aktif; tanpa faktor kedua |
| POST | `/cek_booking.php` `action=upload_bukti` | Upload bukti transfer | Kode booking | Ya | `reservasi.bukti_bayar` | Aktif; menulis file sebelum update DB |
| GET | `/upload_bukti.php` | Redirect ke `cek_booking.php` | Tidak ada | Tidak | — | Redirect; tidak melakukan upload |
| GET | `/login.php` | Form login admin | Tidak ada | Ya | — | Aktif |
| POST | `/admin/handlers/cek_login.php` | Proses login | Rate limit DB + CSRF | Ya | `admin`, `login_attempts` | Aktif |

---

## 3. Halaman Admin

Semua halaman di bawah ini mengasumsikan session admin. Kolom “Guard” mencatat pemanggilan yang benar-benar ada di file.

| Method | Path | Guard | Permission/role | Fungsi | Catatan |
|---|---|---|---|---|---|
| GET | `/admin/index.php` | `require_admin()` | — | Dashboard, statistik, log buku tamu | Satu-satunya halaman yang memakai guard eksplisit |
| GET | `/admin/reservasi.php` | `check_session_timeout()` + `enforce_active_session()` | `reservasi.view` | Daftar/filter/cari reservasi | Form aksi dikirim ke handler |
| GET/POST | `/admin/edit.php?id=` | `check_session_timeout()` + `enforce_active_session()` | `content.edit` | Edit buku tamu | Tidak memakai `require_admin`; tamu tertahan oleh permission |
| GET | `/admin/galeri.php` | `enforce_active_session()` | `gallery.view` | Daftar galeri | Form tambah/edit/hapus |
| GET | `/admin/berita.php` | `check_session_timeout()` + `enforce_active_session()` | `news.view` | Daftar berita | Handler memakai `settings.manage` |
| GET | `/admin/faq.php` | `enforce_active_session()` + `check_session_timeout()` | `faq.view` | Daftar FAQ | Handler memakai `settings.manage` |
| GET | `/admin/reviews.php` | `enforce_active_session()` + `check_session_timeout()` | `reviews.view` | Moderasi ulasan | Handler tidak memeriksa permission |
| GET | `/admin/pengaturan.php` | `enforce_active_session()` | `settings.manage` | Edit settings | Termasuk rekening, kuota default, token WA |
| GET | `/admin/audit_keuangan.php` | `enforce_active_session()` | `finance.view` | Laporan keuangan, antrean verifikasi, CRUD transaksi manual | Skema dari migrasi v3 |
| GET | `/admin/scanner.php` | `check_session_timeout()` + `enforce_active_session()` | `reservasi.view` | Scanner QR | Fetch ke `scanner_handler.php` |
| GET | `/admin/logs.php` | `enforce_active_session()` | Role harus `Super Admin` | Audit log | Hardcode role |
| GET | `/admin/kelola_admin.php` | `enforce_active_session()` | Role harus `Super Admin` | CRUD akun admin | Hardcode role; hapus fisik |
| GET | `/admin/logout.php` | Tidak ada | — | Hapus session | GET, tanpa CSRF; mencatat audit logout |

---

## 4. Handler Admin

| Method | Path | Aksi | Guard | CSRF | Permission | Efek | Status |
|---|---|---|---|---|---|---|---|
| POST | `/admin/handlers/cek_login.php` | Login | Rate limit 5/15 menit | Ya | — | Set session, audit | Aktif |
| POST | `/admin/handlers/simpan.php` | Tambah buku tamu | Honeypot + rate limit | Ya | — | Insert `buku_tamu` | Aktif; endpoint publik di folder admin |
| POST | `/admin/handlers/hapus.php` | Hapus buku tamu | `isset($_SESSION['login'])` | Ya | `content.delete` | Delete fisik `buku_tamu` | **Mati**: tidak ada UI yang memanggil |
| GET | `/admin/handlers/export.php?type=csv\|pdf&q=` | Ekspor buku tamu | `isset($_SESSION['login'])` | **Tidak** | `system.export` | Stream CSV / halaman cetak | Aktif; GET tanpa CSRF |
| POST | `/admin/handlers/reservasi_handler.php` `action=simpan` | Buat reservasi | Honeypot + rate limit | Ya | — | Insert + kurangi kuota | Aktif; publik |
| POST | `/admin/handlers/reservasi_handler.php` `action=cek_kuota` | Cek kuota | — | Tidak | — | JSON | Aktif; publik |
| POST | `/admin/handlers/reservasi_handler.php` `action=verifikasi_bayar` | Verifikasi pembayaran | login + timeout | Ya | `reservasi.manage` | Set `LUNAS`, buat QR, kirim PDF lewat service | Aktif; atomik |
| POST | `/admin/handlers/reservasi_handler.php` `action=tolak_bayar` | Tolak pembayaran | login + timeout | Ya | `reservasi.manage` | Set `DITOLAK`, kuota tetap terkunci, WA | Aktif |
| POST | `/admin/handlers/reservasi_handler.php` `action=hapus` | Hapus reservasi | login + timeout | Ya | `reservasi.manage` | Ditolak; gunakan pembatalan | Aktif |
| POST | `/admin/handlers/reservasi_handler.php` `action=update_status` | Ubah status | login + timeout | Ya | `reservasi.manage` | Transisi `DIBATALKAN`/`TIDAK_HADIR` | Aktif; form batal di halaman reservasi |
| POST | `/admin/handlers/review_handler.php` `action=simpan` | Kirim ulasan | Honeypot + rate limit | Ya | — | Insert `reviews` | Aktif; publik |
| POST | `/admin/handlers/review_handler.php` `action=approve` | Setujui ulasan | login + timeout | Ya | **Tidak ada** | Update status | Aktif; celah otorisasi |
| POST | `/admin/handlers/review_handler.php` `action=tolak` | Tolak ulasan | login + timeout | Ya | **Tidak ada** | Update status | Aktif; celah otorisasi |
| POST | `/admin/handlers/review_handler.php` `action=hapus` | Hapus ulasan | login + timeout | Ya | **Tidak ada** | Delete fisik | Aktif; celah otorisasi |
| POST | `/admin/handlers/galeri_handler.php` `action=add` | Tambah galeri | login + timeout + active session | Ya | `gallery.manage` | Upload + insert | Aktif; MIME dari klien |
| POST | `/admin/handlers/galeri_handler.php` `action=edit` | Edit galeri | login + timeout + active session | Ya | `gallery.manage` | Update + ganti file | Aktif |
| POST | `/admin/handlers/galeri_handler.php` `action=delete` | Hapus galeri | login + timeout + active session | Ya | `gallery.manage` | Delete + hapus file | Aktif |
| POST | `/admin/handlers/berita_handler.php` `action=simpan` | Tambah berita | login + timeout | Ya | `settings.manage` | Insert + upload | Aktif; permission tidak tepat |
| POST | `/admin/handlers/berita_handler.php` `action=update` | Edit berita | login + timeout | Ya | `settings.manage` | Update | Aktif |
| POST | `/admin/handlers/berita_handler.php` `action=toggle_aktif` | Toggle berita | login + timeout | Ya | `settings.manage` | Update | Aktif |
| POST | `/admin/handlers/berita_handler.php` `action=hapus` | Hapus berita | login + timeout | Ya | `settings.manage` | Delete + hapus file | Aktif |
| POST | `/admin/handlers/faq_handler.php` `action=simpan` | Tambah FAQ | login + timeout | Ya | `settings.manage` | Insert | Aktif |
| POST | `/admin/handlers/faq_handler.php` `action=update` | Edit FAQ | login + timeout | Ya | `settings.manage` | Update | Aktif |
| POST | `/admin/handlers/faq_handler.php` `action=toggle_aktif` | Toggle FAQ | login + timeout | Ya | `settings.manage` | Update | Aktif |
| POST | `/admin/handlers/faq_handler.php` `action=hapus` | Hapus FAQ | login + timeout | Ya | `settings.manage` | Delete | Aktif |
| POST | `/admin/handlers/settings_handler.php` | Update settings | login + timeout + active session | Ya | `settings.manage` | Update banyak key | Aktif; tanpa audit |
| POST | `/admin/handlers/keuangan_handler.php` `action=simpan` | Tambah/edit transaksi manual | active session | Ya | `finance.manage` | Insert/update `laporan_keuangan` | Aktif; melanggar append-only |
| POST | `/admin/handlers/keuangan_handler.php` `action=hapus` | Hapus transaksi manual | active session | Ya | `finance.manage` | Delete + hapus lampiran | Aktif; melanggar append-only |
| GET | `/admin/handlers/keuangan_handler.php?action=export_csv&bulan=&tahun=` | Ekspor CSV keuangan | active session | **Tidak** | `finance.view` | Stream CSV | Aktif; GET tanpa CSRF |
| POST | `/admin/handlers/scanner_handler.php` `action=verify` | Cari tiket | Hanya `admin_id` | **Tidak** | Tidak ada | JSON data tiket | Aktif; tanpa active-session check |
| POST | `/admin/handlers/scanner_handler.php` `action=checkin` | Check-in | Hanya `admin_id` | **Tidak** | Tidak ada | Update `Selesai` | Aktif; check-then-act |
| GET | `/admin/api/notifications.php` | Hitung pending + 5 terbaru | Hanya `admin_id` | Tidak | Tidak ada | JSON `count/recent` | Aktif; tanpa active-session check |

---

## 5. Route yang Tidak Dipakai / Bermasalah

| Route/elemen | Lokasi | Status | Tindakan yang disarankan |
|---|---|---|---|
| `action=update_status` | Handler reservasi | Dibuka untuk batal/no-show; tidak ada form ubah status bebas | Batasi sesuai transisi service |
| `admin/handlers/hapus.php` | Tidak dipanggil UI mana pun | Orphan | Hapus atau hubungkan ke UI hapus buku tamu |
| `upload_bukti.php` | Hanya redirect | Duplikat lama | Hapus setelah memastikan tidak ada tautan eksternal |
| `.swal-hapus` di dashboard | `admin/index.php:543` | JS tanpa tombol pasangan | Hapus JS mati |
| `cek_kuota` | `reservasi_handler.php:16` | Publik tanpa CSRF | Tetap publik, tetapi beri rate limit dan respons minim |
| `simpan.php` di `admin/handlers/` | Dipakai form publik `index.php:215` | Penempatan menyesatkan | Pindahkan ke handler publik atau rename |
| `review_handler.php` | Melayani publik + admin | Campuran domain | Pisahkan handler publik dan admin |
| `reservasi_handler.php` | Melayani publik + admin | Campuran domain | Pisahkan handler publik dan admin |
| Redirect hardcode `/ujikom1/...` | Banyak handler | Tidak portabel | Gunakan `base_url()` |
| `export.php` dan `keuangan_handler.php?action=export_csv` | GET | Tidak sesuai PRD RPT-2 | Ubah ke POST + CSRF atau tambahkan token pada GET |

---

## 6. Peta Aksi per Resource

| Resource | Create | Read | Update | Delete | Sumber data |
|---|---|---|---|---|---|
| Buku tamu | `simpan.php` | `admin/index.php`, `edit.php` | `edit.php` | `hapus.php` (orphan) | `buku_tamu` |
| Reservasi | `reservasi_handler.php?action=simpan` | `admin/reservasi.php`, `cek_booking.php`, `scanner_handler.php` | `verifikasi_bayar`, `tolak_bayar`, `update_status` (batal/no-show) | `reservasi_handler.php?action=hapus` (ditolak) | `reservasi`, `kuota_harian` |
| Pembayaran | `cek_booking.php` upload | Detail di `admin/reservasi.php` | — | — | `reservasi.bukti_bayar` |
| Tiket/QR | Verifikasi bayar | `cek_booking.php`, scanner | — | — | `reservasi.qr_token`, `assets/tickets/` |
| Admin | `kelola_admin.php?action=add` | `kelola_admin.php` | `update_permissions`, `change_password` | `kelola_admin.php?action=delete` | `admin` |
| Galeri | `galeri_handler.php?action=add` | `galeri.php`, `index.php` | `galeri_handler.php?action=edit` | `galeri_handler.php?action=delete` | `galeri`, `assets/galeri/` |
| Berita | `berita_handler.php?action=simpan` | `berita.php`, `index.php` | `berita_handler.php?action=update` | `berita_handler.php?action=hapus` | `berita`, `assets/berita/` |
| FAQ | `faq_handler.php?action=simpan` | `faq.php`, `index.php` | `faq_handler.php?action=update` | `faq_handler.php?action=hapus` | `faq` |
| Ulasan | `review_handler.php?action=simpan` | `reviews.php`, `index.php` | `approve`/`tolak` | `review_handler.php?action=hapus` | `reviews` |
| Keuangan manual | `keuangan_handler.php?action=simpan` | `audit_keuangan.php` | `keuangan_handler.php?action=simpan` (id > 0) | `keuangan_handler.php?action=hapus` | `laporan_keuangan`, `assets/bukti_keuangan/` |
| Pengaturan | — | `pengaturan.php` | `settings_handler.php` | — | `settings` |
| Audit log | Sistem | `logs.php` | — | — | `audit_logs` |
| Kuota | Otomatis saat booking | `cek_kuota` | Otomatis | Otomatis | `kuota_harian` |
| Rekonsiliasi | — | — | — | — | Tidak ada |
| Tutup harian | — | — | — | — | Tidak ada |

---

## 7. Catatan Implementasi untuk Fase Berikutnya

1. Tetapkan daftar route resmi lebih dulu, lalu hapus route yang tidak ada di daftar. Jangan mempertahankan route mati hanya karena “mungkin dipakai”.
2. Pisahkan handler publik dan admin. Endpoint publik tidak boleh berada di folder `admin/handlers/`.
3. Semua aksi tulis admin memakai urutan yang sama: `require_admin` → `enforce_active_session` → CSRF → permission → validasi → service.
4. Semua endpoint AJAX memakai format `{ ok, data, error }` dan prefix `/admin/api/`.
5. Ekspor adalah aksi sensitif: POST + CSRF + audit log, bukan GET.
6. Setiap route yang mengubah status booking harus memanggil `BookingStateService`, bukan `UPDATE` langsung.
