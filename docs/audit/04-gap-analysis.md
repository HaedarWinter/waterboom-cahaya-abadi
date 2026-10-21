# Gap Analysis — Waterboom Cahaya Abadi

**Fase:** 0 (pemetaan)  
**Tanggal:** 22 Oktober 2026  
**Baseline commit:** `2192cf8` (working tree berisi perubahan belum di-commit)  
**Metode:** telaah kode, `sql/`, `docs/audit/`, inspeksi skema database live secara read-only, dan menjalankan `tests/verify_hardening.php`.

> Dokumen ini memetakan kondisi nyata terhadap `docs/Dokumentasi Alur CRUD Keuangan.md`. Snapshot awal diambil sebelum migrasi v3. Per 22 Oktober 2026, `sql/migration_v3_foundation.sql` dan `sql/migration_v3_state_machine.sql` sudah diterapkan; temuan yang sudah ditutup ditandai di tiap bagian.

---

## 1. Ringkasan Eksekutif

Hardening keamanan sebelumnya masih valid: `php tests/verify_hardening.php` menghasilkan **7 PASS / 0 FAIL**. Namun kesiapan finansial masih jauh dari target PRD.

| Area PRD | Kondisi nyata | Status |
|---|---|---|
| Fondasi tabel keuangan (`payments`, `ledger_entries`, `cash_accounts`, `refunds`, `daily_closings`, `reconciliations`) | Tidak ada satu pun tabel tersebut | Belum |
| State machine booking | 10 status PRD, transisi terpusat di `BookingStateService`, riwayat tercatat | Selesai |
| Pembayaran atomik + entri kas | Verifikasi hanya mengubah status `reservasi`; tidak ada tabel pembayaran atau buku kas | Belum |
| Buku kas append-only | `laporan_keuangan` bisa diedit dan dihapus; bukan ledger | Belum |
| Rekonsiliasi & tutup harian | Tidak ada | Belum |
| Audit log siap audit | Ada `audit_logs`, tetapi tanpa before/after, role, user-agent, hasil | Sebagian |
| RBAC/SoD | Hanya `Super Admin` dan `Admin`; tidak ada peran finance/gate; tidak ada pemisahan tugas | Belum |
| CRUD terstruktur | Sebagian resource sudah punya handler, tetapi masih ada endpoint publik di folder `admin/handlers/`, duplikasi, dan kode mati | Sebagian |
| Laporan keuangan | Ada halaman `audit_keuangan.php`, tetapi sumbernya campuran dan belum konsisten | Sebagian |
| Backlog keamanan SEC-1/SEC-2 | Belum selesai | Belum |

**Kesimpulan:** jangan mulai dari buku kas. Fondasi skema dan state machine harus dibuat lebih dulu. Buku kas yang dibangun di atas `reservasi.status` saat ini akan mewarisi transisi status yang tidak atomik dan tidak tercatat.

---

## 2. Skema Database Nyata

### 2.1 Tabel yang ada di database live sebelum migrasi v3

`admin`, `audit_logs`, `berita`, `buku_tamu`, `faq`, `galeri`, `kuota_harian`, `laporan_keuangan`, `login_attempts`, `reservasi`, `reviews`, `settings`.

### 2.2 Tabel PRD yang belum dipakai aplikasi

Setelah `sql/migration_v3_foundation.sql`, tabel berikut sudah dibuat secara fisik: `payments`, `payment_proofs`, `cash_accounts`, `ledger_entries`, `refunds`, `daily_closings`, `booking_status_history`. Belum ada kode yang menulis atau membacanya.

Masih belum ada: `ticket_types`, `ticket_prices`, `expenses`, `reconciliations`, `reconciliation_items`, `checkins`, `notification_logs`, `users` (peran internal masih memakai `admin`).

Catatan: `kuota_harian` setara `daily_quotas`, `buku_tamu` setara `guestbook`, dan `admin` setara `users`. Namanya berbeda, tetapi fungsinya ada.

### 2.3 Selisih sumber skema

Skema tidak konsisten antara file migrasi dan database nyata.

| Temuan | Bukti | Dampak |
|---|---|---|
| `sql/db.sql` tidak membuat `audit_logs` | `sql/db.sql` hanya berisi 9 tabel; `sql/migration_v3_foundation.sql` membuatnya | Instalasi baru tetap gagal login bila migrasi v3 dilewati |
| `sql/db.sql` tidak memiliki kolom `is_checkin` dan `waktu_checkin` | Kolom ada di DB live; `sql/migration_v3_foundation.sql` menambahkannya | Check-in gagal bila migrasi v3 dilewati |
| `laporan_keuangan` dahulu dibuat saat runtime | DDL runtime sudah dihapus dari `admin/audit_keuangan.php` dan `admin/handlers/keuangan_handler.php`; skema kini dari migrasi v3 | Tabel tetap legacy dan bukan buku kas |
| `admin.role` berbeda | DB live sudah mencakup `Finance`, `Gate`, `Akuntan`; `sql/db.sql:21` belum memuat `Finance`/`Gate` | Peran baru hanya ada bila migrasi v3 dijalankan |
| Seed `settings` berbeda | `sql/db.sql` memakai `rekening_bank` gabungan; `sql/migration_v2.sql` memakai `rekening_bank`, `rekening_nomor`, `rekening_nama` | Nilai rekening bisa ambigu |
| `pajak_persen` tidak ada di DB live | `get_setting('pajak_persen', 10)` selalu jatuh ke default 10% | Pajak dihitung dengan asumsi, bukan konfigurasi |
| Migrasi keamanan berada di `db/migration_v3_security.sql` | PRD meminta `sql/migration_v3_*.sql` | Lokasi migrasi tidak seragam |
| `settings` menyimpan token Fonnte plaintext | Tabel `settings`, key `fonnte_token` (nilai tidak dicetak) | Rahasia ada di database tanpa enkripsi; akses DB = akses token |

### 2.4 Skema `reservasi` saat ini

Kolom: `id`, `kode_booking`, `nama`, `no_hp`, `email`, `tanggal_kunjungan`, `jenis_tiket`, `jumlah_dewasa`, `jumlah_anak`, `total_harga`, `status`, `catatan`, `catatan_tolak`, `metode_bayar`, `bukti_bayar`, `qr_token`, `is_checkin`, `waktu_checkin`, `created_at`.

Status enum: 10 status PRD; data lama sudah dipetakan oleh `sql/migration_v3_state_machine.sql`.

Tidak ada kolom batas bayar, nominal diterima, tanggal transfer, rekening tujuan, kode unik, hash bukti, `archived_at`, atau `payment_id`.

---

## 3. Status Booking & State Machine

### 3.1 Perbandingan status

| PRD | Nyata | Keterangan |
|---|---|---|
| `MENUNGGU_BAYAR` | `MENUNGGU_BAYAR` | Booking transfer dan bayar di lokasi |
| `MENUNGGU_VERIFIKASI` | `MENUNGGU_VERIFIKASI` | Bukti sudah diunggah |
| `LUNAS` | `LUNAS` | Verifikasi admin |
| `SELESAI` | `SELESAI` | Check-in |
| `DITOLAK` | `DITOLAK` | Admin menolak bukti, boleh unggah ulang sebelum batas |
| `DIBATALKAN` | `DIBATALKAN` | Manual |
| `KEDALUWARSA` | `KEDALUWARSA` | Job expire idempoten |
| `REFUND_DIAJUKAN` | `REFUND_DIAJUKAN` | Transisi siap, alur refund belum |
| `DIREFUND` | `DIREFUND` | Transisi siap, alur refund belum |
| `TIDAK_HADIR` | `TIDAK_HADIR` | Transisi siap, job harian belum |

### 3.2 Lokasi perubahan status

| Aksi | Lokasi | Atomik? | Tercatat di riwayat? |
|---|---|---|---|
| Semua transisi | `app/Services/BookingStateService.php` | Ya, row lock + transaksi | Ya |
| Bukti diunggah | `BookingStateService::submitProof` | Ya | Ya |
| Verifikasi | `BookingStateService::transition(LUNAS)` | Ya, satu kali | Ya |
| Tolak | `BookingStateService::transition(DITOLAK)` | Ya | Ya |
| Expire | `BookingStateService::expireOverdue` | Ya, idempoten | Ya |
| Check-in | `BookingStateService::checkIn` | Ya, satu kali | Ya |
| Hapus permanen | Ditolak handler | — | — |

### 3.3 Status implementasi state machine

**Selesai:**
1. Semua transisi lewat `BookingStateService` dengan tabel transisi eksplisit. Target FLOW-1 terpenuhi.
2. Setiap transisi menulis `booking_status_history`. Target FLOW-2 terpenuhi.
3. Batas bayar memakai setting `payment_deadline_minutes` (default 120). Target FLOW-3 terpenuhi.
4. `DITOLAK` tidak melepas kuota; unggah ulang diizinkan selama belum lewat batas. Target FLOW-4 terpenuhi.
5. Pelepasan kuota terjadi di transaksi yang sama dengan perubahan status. Target FLOW-5 terpenuhi.
6. Expire idempoten: kandidat dikunci ulang sebelum transisi. Target FLOW-6 terpenuhi untuk logika; penjadwalan masih lazy.
7. Check-in memakai row lock dan menolak scan kedua. Target FLOW-7 terpenuhi.
8. Baris `kuota_harian` dipastikan ada sebelum `FOR UPDATE`, menutup race tanggal baru.
9. Hapus permanen reservasi ditolak; UI memakai pembatalan.

**Belum:**
1. Auto-expire masih dipanggil dari `app/init.php` setiap request. Butuh opsi cron sungguhan.
2. Job harian `TIDAK_HADIR` belum ada. Target FLOW-8 (P2) belum.
3. Reschedule belum ada. Target FLOW-9 (P2) belum.
4. Refund baru sebatas status; alur persetujuan dan entri pembalik belum.
---

## 4. Pembayaran

| ID | Target | Kondisi nyata | Status |
|---|---|---|---|
| PAY-1 | Tabel `payments` terpisah, 1 booking banyak percobaan | Tidak ada; semua data di `reservasi` | Belum |
| PAY-2 | Nominal dikunci di server | `total_harga` dihitung server saat create (`reservasi_handler.php:58-59`) dan disimpan di `reservasi` | Sebagian |
| PAY-3 | Kode unik 2–3 digit | Tidak ada | Belum |
| PAY-4 | Verifikasi wajib nominal, tanggal, rekening, catatan | Handler hanya menerima `id`; tidak ada input nominal/tanggal/rekening | Belum |
| PAY-5 | Verifikasi atomik, satu kali per percobaan | Tidak ada guard status; verifikasi berulang membuat token baru dan mengirim tiket lagi | Belum |
| PAY-6 | Hash bukti untuk deteksi duplikat | Tidak ada; hanya nama file | Belum |
| PAY-7 | Bukti di luar web root, akses berizin, retensi | Disimpan di `assets/bukti_bayar/` yang berada di web root; tidak ada `.htaccess`; `.gitignore` hanya mencegah commit | Belum |
| PAY-8 | Transfer kurang/lebih tercatat sebagai tindakan | Tidak ada | Belum |

Temuan tambahan:

- `bukti_bayar` tidak menyimpan metadata apa pun (tanggal transfer, nominal diterima, rekening tujuan, hash, ukuran, tipe MIME asli).
- Verifikasi mengurangi risiko ganda hanya lewat kebiasaan admin, bukan constraint. Tidak ada `UNIQUE` atau transaksi yang mengikat verifikasi ke entri kas.
- Tabel `payments` sudah ada, tetapi belum dipakai; status pembayaran belum menggerakkan UI atau buku kas.
- `metode_bayar` hanya `Bayar di Lokasi` dan `Transfer Bank`; tidak ada kesiapan `provider_ref` untuk payment gateway.

---

## 5. Buku Kas, Rekonsiliasi, dan Audit Keuangan

### 5.1 `laporan_keuangan` bukan buku kas

`laporan_keuangan` adalah tabel catatan manual yang dapat diedit dan dihapus. Tabel ini kini dibuat oleh migrasi v3, bukan runtime, tetapi tetap bukan buku kas:

- `admin/handlers/keuangan_handler.php:74-82` melakukan `UPDATE`.
- `admin/handlers/keuangan_handler.php:93-116` melakukan `DELETE`, termasuk menghapus lampiran.
- Nominal memakai `DECIMAL(15,2)`, bukan `BIGINT` rupiah.
- Tidak ada `source_type`, `source_id`, `direction`, `entry_no`, `reverses_entry_id`, atau `reconciled_at`.
- Tidak ada constraint unik untuk mencegah entri ganda.
- DDL runtime sudah dihapus; skema berasal dari `sql/migration_v3_foundation.sql`.

Target LED-1 sampai LED-9 belum terpenuhi.

### 5.2 Rekonsiliasi dan tutup harian

Tidak ada tabel maupun UI untuk:

- mutasi bank/e-wallet,
- pencocokan otomatis,
- selisih dan penjelasannya,
- tutup harian,
- penguncian periode,
- pembukaan kembali periode,
- peringatan transaksi belum terekonsiliasi.

Target REC-1 sampai REC-5 belum terpenuhi.

### 5.3 Audit log

`audit_logs` yang ada: `id`, `admin_id`, `admin_username`, `action`, `target_table`, `target_id`, `details`, `ip_address`, `created_at`.

Selisih terhadap PRD:

| ID | Target | Kondisi |
|---|---|---|
| AUD-1 | Waktu, user, peran, IP, user-agent, aksi, entitas+id, before/after, hasil | Hanya sebagian; tidak ada peran, user-agent, before/after, hasil |
| AUD-2 | Wajib mencatat login, harga, kuota, verifikasi, kas, reversal, refund, tutup periode, akun, ekspor, pengaturan | Login dan beberapa aksi konten/reservasi tercatat; harga, kuota, pengaturan, akun, refund, kas, tutup periode tidak |
| AUD-3 | Append-only dan terlindungi | Tidak ada pembatasan DB; tabel dapat dihapus lewat kredensial aplikasi |
| AUD-4 | Retensi minimal 5 tahun | Tidak ada kebijakan retensi |
| AUD-5 | Hash chain | Tidak ada |

Selain itu, `audit_logs` tidak ada di `sql/db.sql`; tabel dibuat oleh `sql/migration_v3_foundation.sql`. Instalasi baru yang melewatkan migrasi tetap tidak bisa login.

### 5.4 Laporan

Halaman `admin/audit_keuangan.php` menghitung:

- pendapatan sistem dari `reservasi` berstatus `LUNAS`/`SELESAI` berdasarkan `created_at`,
- pemasukan/pengeluaran manual dari `laporan_keuangan`,
- pajak dengan asumsi default 10%.

Masalah konsistensi:

- `kategori_list` mencakup `Reservasi` (`audit_keuangan.php:142`), sehingga transaksi manual berkategori `Reservasi` dapat dihitung dua kali bersama pendapatan sistem.
- Laporan memakai tanggal `created_at` booking, bukan tanggal pembayaran diterima.
- Tidak ada pemisahan “uang diterima di muka” vs “layanan diberikan” (RPT / 8.4).
- Ekspor CSV (`keuangan_handler.php:120`) memakai logika pajak berbeda dari halaman dan tidak memakai CSRF.
- Dashboard (`app/Models/Dashboard.php`) dan laporan keuangan memakai query berbeda; belum ada tes yang membandingkan totalnya (RPT-3).

Target RPT-1 sampai RPT-3 belum terpenuhi.

---

## 6. Peta CRUD Nyata

Legenda: **C** create, **R** read, **U** update, **D** delete/hapus.

| Entitas | C | R | U | D | Lokasi utama | Catatan |
|---|---|---|---|---|---|---|
| Buku tamu | Publik | Admin | Admin | Endpoint ada | `admin/handlers/simpan.php`, `admin/index.php`, `admin/edit.php`, `admin/handlers/hapus.php` | `hapus.php` tidak dipanggil UI; hapus fisik |
| Reservasi | Publik | Admin | Admin (verifikasi/tolak) | Admin | `admin/handlers/reservasi_handler.php` | Hapus fisik; `update_status` mati |
| Bukti bayar | Publik | Admin | Tidak | Tidak | `cek_booking.php` | Disimpan di web root |
| Kuota harian | Tidak ada UI | Publik (sisa) | Otomatis | — | `Reservasi.php` | Tidak ada CRUD admin untuk kuota per tanggal |
| Harga tiket | Seed settings | Publik | Settings | — | `settings` | Tidak ada riwayat harga/tanggal berlaku |
| Admin/internal user | Super Admin | Super Admin | Super Admin | Super Admin | `admin/kelola_admin.php` | Hapus fisik; tidak ada nonaktifkan sebagai default |
| Galeri | Admin | Publik/Admin | Admin | Admin | `admin/galeri.php`, `galeri_handler.php` | Hapus fisik + file |
| Berita | Admin | Publik/Admin | Admin | Admin | `admin/berita.php`, `berita_handler.php` | Hapus fisik + file |
| FAQ | Admin | Publik/Admin | Admin | Admin | `admin/faq.php`, `faq_handler.php` | Hapus fisik |
| Ulasan | Publik | Publik/Admin | Admin | Admin | `review_handler.php` | Hapus fisik |
| Laporan keuangan | Admin | Admin | Admin | Admin | `audit_keuangan.php`, `keuangan_handler.php` | Bertentangan dengan aturan append-only |
| Audit log | Sistem | Super Admin | Tidak | Tidak | `logs.php` | Retensi dan perlindungan belum ada |
| Pengaturan | Super Admin | Admin | Super Admin | — | `pengaturan.php`, `settings_handler.php` | Termasuk rekening dan token WA |

### 6.1 Pelanggaran aturan penghapusan PRD 7.3

| Jenis | Aturan PRD | Nyata |
|---|---|---|
| Keuangan | Tidak pernah dihapus | `laporan_keuangan` dihapus fisik |
| Transaksional | Tidak dihapus fisik; pakai status/`archived_at` | `reservasi` dihapus fisik (`reservasi_handler.php:152`) |
| Master | Nonaktifkan, jangan hapus | `admin` dihapus fisik; galeri/FAQ/berita dihapus fisik |
| Konten | Boleh hapus/soft delete | Hapus fisik tanpa soft delete |
| Akun internal | Nonaktifkan, jangan hapus | Ada aksi `delete` di `kelola_admin.php` |

### 6.2 Struktur handler

- Satu file handler menangani beberapa aksi, tetapi tidak seragam. `simpan.php`, `hapus.php`, `edit.php` adalah sisa pola lama per tombol.
- `admin/handlers/reservasi_handler.php` dan `review_handler.php` melayani endpoint publik dan admin dalam satu file.
- `admin/handlers/simpan.php` berada di folder admin tetapi dipakai form publik `index.php`.
- Respons error tidak seragam: sebagian redirect dengan query string, sebagian JSON dengan format `success/message`, bukan `{ ok, data, error }`.
- Prefix API belum konsisten: `admin/api/notifications.php` ada, tetapi `scanner_handler.php` bukan di bawah `admin/api/`.
- Banyak redirect hardcode `/ujikom1/...` meskipun sudah ada `base_url()`.
- `admin/index.php` masih memuat JS `.swal-hapus` yang tidak punya tombol pasangan (kode mati).
- `upload_bukti.php` hanya redirect ke `cek_booking.php`.

Target CRUD-1 sampai CRUD-10 baru terpenuhi sebagian.

---

## 7. RBAC, Keamanan, dan Pemisahan Tugas

### 7.1 Peran nyata

Database live hanya memiliki `Super Admin` dan `Admin`. Kode memakai `Super Admin` secara hardcode untuk `kelola_admin.php` dan `logs.php`. Tidak ada peran `finance`, `gate`, atau `Akuntan` yang berfungsi.

Permission granular disimpan sebagai JSON di `admin.permissions` dengan kategori: `content`, `gallery`, `reservasi`, `news`, `reviews`, `faq`, `finance`, `settings`, `user`, `system`.

Tidak ada permission spesifik untuk `payment.verify`, `ledger.read`, `refund.approve`, `period.close`, atau `reconciliation`.

### 7.2 Pemisahan tugas

Tidak ada mekanisme SoD. Satu akun `Super Admin` dapat mengubah harga, memverifikasi pembayaran, membuat entri kas, dan menyetujui koreksi. Tidak ada penanda “tanpa pemeriksa kedua” pada laporan.

### 7.3 Celah keamanan yang masih terbuka

| ID | Temuan | Bukti |
|---|---|---|
| SEC-1 | `scanner_handler.php` dan `admin/api/notifications.php` hanya memeriksa `$_SESSION['admin_id']`, tidak memanggil `enforce_active_session` | `scanner_handler.php:8`, `notifications.php:6` |
| SEC-2 | Logout memakai GET tanpa CSRF; ekspor buku tamu dan ekspor keuangan memakai GET tanpa CSRF | `admin/logout.php:8-14`, `admin/handlers/export.php:24`, `keuangan_handler.php:120` |
| SEC-3 | Permission keuangan hanya `finance.view`/`finance.manage`; tidak ada permission aksi spesifik | `config/security.php:114-126` |
| SEC-4 | Tidak ada re-autentikasi password untuk aksi sensitif | Tidak ditemukan |
| SEC-5 | Rekening tujuan adalah nilai `settings` bebas; perubahan tidak diaudit dan tidak memberi notifikasi | `settings_handler.php:59-74` |
| SEC-6 | Cek booking hanya butuh kode booking; tidak ada faktor kedua dan tidak ada rate limit pencarian | `cek_booking.php:74-86` |
| SEC-7 | Tes konkurensi baru mencakup kuota; verifikasi ganda, scan ganda, dan race upload belum diuji | `tests/verify_hardening.php` |
| SEC-8 | Tidak ada kebijakan minimalisasi/retensi data pribadi | Tidak ditemukan |
| SEC-9 | Tidak ada skrip backup/restore terjadwal | Tidak ditemukan |
| SEC-10 | Seed default masih ada dan tidak ada paksaan ganti password saat login pertama | `sql/db.sql:27-30`; tidak ada flag `must_change_password` |

Celah tambahan:

- `review_handler.php:53-63` memeriksa login dan CSRF, tetapi tidak memanggil `enforce_active_session` dan tidak memeriksa permission. Admin yang sudah dinonaktifkan masih bisa approve/tolak/hapus selama session tersisa.
- `berita_handler.php` dan `faq_handler.php` memakai permission `settings.manage`, bukan `news.manage`/`faq.manage`.
- `galeri_handler.php` memvalidasi MIME dari `$_FILES['type']` (nilai dari klien), bukan `finfo`.
- `keuangan_handler.php` hanya memvalidasi ekstensi lampiran, bukan MIME.
- `cek_booking.php` menyimpan bukti ke folder yang dapat diakses langsung lewat URL.
- `admin/edit.php` memakai `enforce_active_session` tanpa `require_admin`; akses tamu tertahan oleh `has_permission`, tetapi polanya tidak konsisten dengan `admin/index.php`.
- Tidak ada pembatasan percobaan pada halaman cek booking, sehingga kode booking dapat ditebak/di-enumerasi.
- `kode_booking` memakai 3 byte acak (`CA-` + 6 hex) sehingga ruang tebakannya relatif kecil bila tanpa rate limit.

---

## 8. Tes

`tests/verify_hardening.php` saat ini menguji:

- dashboard tanpa login,
- GET-delete ditolak,
- kuota atomik pada baris kuota yang sudah ada,
- pengembalian kuota saat `DIBATALKAN`, `KEDALUWARSA`, atau `DIREFUND`,
- idempotensi update status yang sama.

Hasil: **7 PASS / 0 FAIL**.

Belum ada tes untuk:

- seluruh transisi state machine (sah dan ilegal),
- dua verifikasi pembayaran bersamaan,
- dua scan QR bersamaan,
- booking pertama pada tanggal tanpa baris kuota,
- entri kas tunggal per pembayaran,
- periode tertutup,
- konsistensi total dashboard vs laporan vs buku kas,
- akses negatif per peran,
- IDOR booking/bukti,
- idempotensi notifikasi dan job,
- upload bukti pada booking batal/selesai.

Tes juga tidak berjalan dalam transaksi yang di-rollback; tes menulis data nyata ke DB lalu menghapusnya. Ini berisiko bila dijalankan di database produksi.

---

## 9. Kesesuaian per Kebutuhan PRD

### 9.1 FLOW

| ID | Status | Catatan |
|---|---|---|
| FLOW-1 | Belum | Tidak ada `BookingStateService` |
| FLOW-2 | Belum | Tidak ada `booking_status_history` |
| FLOW-3 | Belum | Tidak ada batas bayar terkonfigurasi |
| FLOW-4 | Belum | Re-upload tidak mengambil kuota; upload pada booking batal bisa menghidupkan booking |
| FLOW-5 | Sebagian | Create atomik; reject/cancel tidak satu transaksi dengan update status |
| FLOW-6 | Belum | Lazy cron per request; tidak idempoten/aman paralel |
| FLOW-7 | Belum | Check-in check-then-act |
| FLOW-8 | Belum | Tidak ada `TIDAK_HADIR` |
| FLOW-9 | Belum | Tidak ada reschedule |

### 9.2 PAY

| ID | Status |
|---|---|
| PAY-1 | Belum |
| PAY-2 | Sebagian |
| PAY-3 | Belum |
| PAY-4 | Belum |
| PAY-5 | Belum |
| PAY-6 | Belum |
| PAY-7 | Belum |
| PAY-8 | Belum |

### 9.3 UX

| ID | Status | Catatan |
|---|---|---|
| UX-1 | Sebagian | Status ditampilkan, tetapi tanpa hitung mundur dan bahasa awam yang konsisten |
| UX-2 | Belum | Hanya kode booking |
| UX-3 | Sebagian | WhatsApp ada; idempotensi dan log gagal kirim belum tersedia |

### 9.4 CRUD

| ID | Status | Catatan |
|---|---|---|
| CRUD-1 | Sebagian | Handler per resource belum seragam |
| CRUD-2 | Sebagian | Sebagian memakai PRG; beberapa endpoint mengembalikan redirect/JSON campur |
| CRUD-3 | Sebagian | Urutan sesi → CSRF → izin → validasi → service belum konsisten |
| CRUD-4 | Sebagian | Ada model, tetapi logika bisnis masih di handler |
| CRUD-5 | Belum | Tidak ada Validator terpusat |
| CRUD-6 | Sebagian | Pagination ada di banyak daftar; galeri belum; filter/urutan belum seragam |
| CRUD-7 | Sebagian | Pesan error beragam; beberapa membocorkan detail DB |
| CRUD-8 | Sebagian | Format JSON tidak seragam |
| CRUD-9 | Sebagian | Inventaris route dibuat di `05-route-inventory.md`; kode mati belum dihapus |
| CRUD-10 | Sebagian | Sebagian konfigurasi di `settings`, tetapi belum semua dan belum ada UI terkontrol |

### 9.5 LED / REC / AUD / RPT / SEC

| Kelompok | Status |
|---|---|
| LED-1..LED-9 | Belum |
| REC-1..REC-5 | Belum |
| AUD-1..AUD-5 | Belum/sebagian |
| RPT-1..RPT-3 | Belum/sebagian |
| SEC-1..SEC-10 | Belum/sebagian |

---

## 10. Rekomendasi Urutan Fase 1

1. ~~**Bekukan skema nyata ke migrasi idempotent**~~ **Selesai 22 Okt 2026**: `sql/migration_v3_foundation.sql` + rollback diterapkan; DDL runtime dihapus. Migrasi sudah diuji dua kali dan pada instalasi baru.
2. ~~**Bangun `BookingStateService` + `booking_status_history`**~~ **Selesai 22 Okt 2026**: semua transisi, upload bukti, expire, dan check-in lewat service; tes `tests/verify_state_machine.php` 26 PASS.
3. ~~**Perbaiki kuota**~~ **Selesai sebagian**: baris `kuota_harian` dibuat sebelum lock; status + kuota satu transaksi. Sisa: jadwal cron sungguhan.
4. **Buat `payments` + `payment_proofs`** dengan metadata verifikasi, hash bukti, dan guard verifikasi satu kali.
5. **Buat `cash_accounts` + `ledger_entries`** append-only, lalu hubungkan verifikasi pembayaran ke entri kas dalam transaksi yang sama.
6. **Tutup backlog SEC-1/SEC-2/SEC-10** dan tambahkan tes negatif per peran.
7. **Baru** kerjakan refund, tutup harian, rekonsiliasi, dan laporan.

---

## 11. Keputusan Owner yang Masih Dibutuhkan

Item `TODO: konfirmasi akuntan` di PRD tetap terbuka:

- tarif dan dasar pajak daerah,
- perlakuan pendapatan diterima di muka,
- kebijakan refund/reschedule,
- retensi bukti dan audit log,
- jumlah rekening/e-wallet dan pemegangnya,
- apakah penjualan tunai loket masuk sistem,
- target hosting (menentukan cron dan hak DB).

Tanpa keputusan ini, implementasi buku kas dan pajak berisiko mengunci asumsi yang salah.
