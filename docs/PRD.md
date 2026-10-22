# PRD: Waterboom Cahaya Abadi
**Sistem Reservasi Tiket, Check-in, dan Keuangan: Rekonstruksi v4.0**

| | |
|---|---|
| **Versi** | 4.0 (menggantikan v3.0 "Current Implementation") |
| **Status** | Draft untuk disetujui owner |
| **Tanggal** | 22 Oktober 2026 |
| **Stack (tetap)** | PHP 8.x native (tanpa framework), MySQL/MariaDB, SweetAlert2, Chart.js, Lucide |
| **Tujuan akhir** | Sistem reservasi yang rapi, aman, skalabel, dan bisa **di-deploy gratis sebagai demo portfolio** |
| **Dokumen pendamping** | `AGENTS.md` (aturan agent), `docs/DEPLOYMENT.md` (panduan deploy gratis) |

> **Cara baca dokumen ini.** PRD berisi *apa* dan *kenapa* serta aturan sistemnya. Instruksi kerja untuk agent ada di `AGENTS.md`, bukan di sini. Bagian 1 adalah hasil review PRD v3, bagian 2–10 adalah target rancangan, bagian 11 adalah urutan rekonstruksinya.
>
> **Catatan penting:** review di bagian 1 berdasarkan **teks PRD v3, bukan pembacaan kode**. Agent wajib memverifikasi tiap temuan ke kode sebelum memperbaiki dan melaporkan selisihnya di `docs/audit/gap-analysis.md`.

---

## 1. Hasil Review PRD v3

Skala: 🔴 High (risiko data/uang/akses) · 🟠 Medium · 🟡 Low

| ID | Sev | Temuan | Dampak | Perbaikan di PRD ini |
|---|---|---|---|---|
| R-01 | 🔴 | **Diagram state machine salah.** Digambar berantai `DITOLAK → KEDALUWARSA → DIBATALKAN → TIDAK_HADIR`, padahal itu bukan urutan, melainkan status akhir yang terpisah | Developer/agent bisa mengimplementasikan transisi yang keliru | Tabel transisi lengkap (bagian 4.2) |
| R-02 | 🔴 | **Ledger disebut append-only, tapi kolom `reconciled_at` & `reconciliation_id` di-UPDATE** dan ada fitur *unreconcile*. Test 9.3 justru menyatakan entri terekonsiliasi "tidak bisa diubah" (saling bertentangan). Selain itu `reconciliation_id = admin_id` salah makna | Ledger tidak benar-benar immutable; auditor bisa menolak | Rekonsiliasi dipindah ke tabel terpisah (5.3) |
| R-03 | 🔴 | **Dua sumber kebenaran.** Audit Keuangan menggabungkan `reservasi` LUNAS + `laporan_keuangan` manual, padahal ledger diklaim SSOT | Angka laporan bisa beda dengan buku kas | Semua laporan hanya dari ledger (5.4) |
| R-04 | 🔴 | **Role terlalu kasar & tanpa pemisahan tugas.** "Admin Biasa" memegang reservasi + scanner; tidak jelas siapa boleh verifikasi bayar; refund diajukan dan diproses tanpa penyetuju kedua; tidak ada peran khusus petugas gerbang | Satu orang bisa menyetujui uangnya sendiri; petugas gerbang melihat data yang tidak perlu | RBAC 6 peran + maker-checker (bagian 3) |
| R-05 | 🔴 | **Permission disimpan sebagai JSON per admin** dengan key tidak konsisten (`content_add` vs `gallery_manage`) | Tidak bisa di-query, di-audit, atau diuji sistematis | Tabel `roles`, `permissions`, `role_permissions` dengan key `modul.aksi` (3.3) |
| R-06 | 🔴 | **Tabel `activity_logs` tidak terlihat di migrasi**, sementara `Audit::log()` dipanggil | Jejak audit mungkin gagal diam-diam | Migrasi wajib + tes "aksi sensitif menghasilkan baris log" (5.6) |
| R-07 | 🔴 | **Logika kedaluwarsa tidak tegas:** kondisi ditulis `created_at > deadline` (terbalik), hanya untuk Transfer Bank, dan deadline dari setting global (mengubah setting bisa menggeser booking lama) | Booking tidak kedaluwarsa / kuota tersangkut | Kolom `payment_deadline_at` yang di-*snapshot* saat booking (4.4) |
| R-08 | 🔴 | **Unique code kontradiktif:** `amount_received = total_harga` padahal ada `unique_code` & kategori ledger `KODE_UNIK`; logikanya belum ada | Nominal transfer tidak bisa dicocokkan; ledger salah | Aturan nominal & pencatatan (4.5), keputusan owner di bagian 14 |
| R-09 | 🟠 | **Data ganda di `reservasi`:** `bukti_bayar`, `metode_bayar`, `is_checkin`, `waktu_checkin`, `total_harga` juga ada di `payments`, `payment_proofs`, atau tersirat dari status. Tidak ada *snapshot* item harga | Data bisa tidak sinkron; harga lama hilang saat tarif berubah | `booking_items` + hapus kolom duplikat bertahap (5.2) |
| R-10 | 🟠 | **Logika bisnis tersebar:** `migration_v3_state_machine.sql` berisi view/procedure, padahal aturan di PRD "hanya `BookingStateService`" | Dua tempat logika, sulit diuji, **tidak portabel** (banyak free hosting & TiDB tidak mendukung procedure/trigger) | Logika hanya di PHP; DB hanya constraint (6.4) |
| R-11 | 🟠 | **Cron memakai `php artisan schedule:run`** (perintah Laravel) padahal PHP native; XAMPP & free hosting tidak menyediakan cron tiap menit | Job kedaluwarsa tidak jalan | *Lazy expiry* + endpoint cron bertoken (4.4) |
| R-12 | 🟠 | **Handler generik** (`hapus.php`, `simpan.php`, `export.php`) | Permukaan serangan luas, izin sulit dikontrol | Controller per resource + middleware izin (6.2) |
| R-13 | 🟠 | **Tes hanya checklist manual** | Regresi tak terdeteksi, terutama race condition & invarian keuangan | Tes otomatis + CI gratis (bagian 9) |
| R-14 | 🟠 | **Path macOS XAMPP di-hardcode**, konfigurasi session di `init.php` | Tidak portabel antar lingkungan | `.env` + `config/` (6.5) |
| R-15 | 🟠 | **PDF tiket dikirim via path lokal/URL file** | Data pengunjung bisa terakses bila URL bocor; storage hosting gratis bersifat sementara | Tiket dibuat *on-demand* + signed URL berkedaluwarsa (4.6) |
| R-16 | 🟠 | **Pajak ambigu:** "PB1/PPN" dan "Net Profit" dicampur | Laporan bisa salah secara akuntansi | Tarif & perlakuan pajak jadi setting, `TODO: konfirmasi akuntan` (5.5) |
| R-17 | 🟡 | **CSS satu file besar** (`admin_style.css`), pagination manual tersebar, helper di `app/` root | Sulit dirawat, mudah konflik | Arsitektur CSS atomic + `@layer` (bagian 7) |
| R-18 | 🟡 | **PRD bercampur instruksi agent** (bagian 7 "untuk Claude review", bagian 10 prompt) | Dokumen produk dan dokumen kerja tercampur | Dipisah ke `AGENTS.md` |
| R-19 | 🟠 | Backlog keamanan sebelumnya: guard sesi aktif di scanner & API notifikasi, CSRF logout/ekspor | Celah masih terbuka bila belum selesai | Masuk fase 1 (bagian 11) |
| R-20 | 🟠 | **Token QR & validasi tanggal tidak dijelaskan** (disimpan plaintext? tiket berlaku tanggal berapa?) | Tiket bisa dipakai di hari lain / token bocor dari DB | QR token di-hash + validasi tanggal (4.6) |

**Yang sudah bagus dan dipertahankan:** `BookingStateService` sebagai satu pintu perubahan status, row lock `FOR UPDATE`, transisi atomik beserta ledger, unique constraint `1 payment = 1 ledger IN`, hash sha256 untuk bukti, skrip invarian keuangan, dan dark mode berbasis CSS variable.

---

## 2. Visi, Tujuan, dan Prinsip

### 2.1 Visi
Satu sistem untuk **pengunjung** (reservasi & tiket digital), **operasional** (verifikasi, check-in, kuota), dan **keuangan** (buku kas yang bisa diaudit), dengan kode yang rapi sehingga mudah dikembangkan dan layak dipamerkan sebagai portfolio.

### 2.2 Tujuan

| # | Tujuan | Indikator |
|---|---|---|
| G1 | Alur status booking/pembayaran/refund tegas dan tidak bisa dilanggar | Semua transisi lewat satu service; tes tabel transisi lolos |
| G2 | Hak akses per divisi sesuai prinsip *least privilege* | Tes matriks peran lolos 100% |
| G3 | Keuangan konsisten dan siap audit | Invarian ledger = 0 pelanggaran; laporan = ledger |
| G4 | Struktur kode berlapis, file kecil, mudah diuji | Batas ukuran file (AGENTS.md) terpenuhi |
| G5 | Tampilan konsisten, modular, dan aksesibel | Semua halaman memakai komponen dari design system |
| G6 | Bisa di-deploy gratis sebagai demo portfolio yang aman | Mode demo berjalan, tanpa data asli |

### 2.3 Non-Goals
Migrasi ke framework · payment gateway otomatis (disiapkan, belum dibangun) · akuntansi penuh (neraca, penyusutan) · aplikasi mobile native.

### 2.4 Prinsip Rancangan

1. **Satu pintu, satu sumber kebenaran.** Status lewat `BookingStateMachine`; uang lewat `LedgerService`; izin lewat `PermissionChecker`.
2. **Default tolak.** Tanpa izin eksplisit, akses ditolak.
3. **Uang tidak pernah dihapus.** Koreksi memakai entri pembalik.
4. **Logika di PHP, bukan di database.** Database menjaga integritas lewat constraint, bukan procedure/trigger.
5. **Atomic dari kecil ke besar.** Berlaku untuk kode (service kecil), CSS (atoms → organisms), dan komit (satu tema per perubahan).
6. **Tidak ada fitur tanpa tes untuk aturan bisnisnya.**
7. **Bisa dijelaskan ke orang awam**: setiap modul punya satu kalimat tujuan.

---

## 3. Pengguna, Divisi, dan Hak Akses (RBAC)

### 3.1 Siapa Mengerjakan Apa

| Divisi / Peran | Kode | Tugas harian | Tidak boleh |
|---|---|---|---|
| **Pengunjung** | (tanpa akun) | Reservasi, bayar, unggah bukti, cek status, unduh tiket, isi buku tamu/ulasan | Melihat booking orang lain |
| **Petugas Gerbang** | `gate` | Scan QR, melihat hasil scan | Lihat data keuangan, daftar reservasi lengkap, atau nomor HP |
| **Front Office / Kasir** | `front_office` | Bantu booking (walk-in), verifikasi/tolak bukti bayar, ajukan refund, urus pertanyaan pengunjung | Menyetujui refund sendiri, mengubah harga, tutup harian |
| **Keuangan / Akuntan** | `finance` | Buku kas, pengeluaran, rekonsiliasi, tutup harian, laporan & ekspor | Mengelola akun/peran, mengubah harga, scan |
| **Manajer Operasional** | `manager` | Kuota, harga (efektif masa depan), menyetujui refund, memantau semua modul, override check-in | Menghapus ledger, membuka periode tertutup, mengelola akun |
| **Admin Konten** | `content` | Berita, galeri, FAQ, moderasi ulasan & buku tamu | Semua data booking & keuangan |
| **Super Admin / Owner** | `super_admin` | Akun & peran, pengaturan, buka periode tertutup, log aktivitas, backup | Mengedit/menghapus ledger & log (tetap tidak bisa) |

> Jika venue kecil, satu orang boleh memegang beberapa peran. Sistem tetap mencatat peran yang dipakai per aksi, dan menandai transaksi yang disetujui oleh orang yang sama dengan pembuatnya di laporan.

### 3.2 Matriks Izin per Modul
Legenda: ✅ penuh · 👁 hanya lihat · ◐ terbatas (catatan di bawah) · — tidak ada akses

| Modul | Aksi (permission key) | super_admin | manager | front_office | finance | gate | content |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|
| **Dashboard** | `dashboard.view` | ✅ | ✅ | ◐ ops | ◐ keuangan | ◐ hari ini | ◐ konten |
| **Reservasi** | `reservation.view` | ✅ | ✅ | ✅ | 👁 | — | — |
| | `reservation.create` (walk-in) | — | ✅ | ✅ | — | — | — |
| | `reservation.update` / `.cancel` | — | ✅ | ✅ | — | — | — |
| | `reservation.export` | ✅ | ✅ | — | — | — | — |
| **Pembayaran** | `payment.view` | ✅ | 👁 | ✅ | ✅ | — | — |
| | `payment.verify` / `.reject` | ◐ darurat | — | ✅ | ✅ | — | — |
| **Check-in** | `checkin.scan` | ✅ | ✅ | ✅ | — | ✅ | — |
| | `checkin.view_log` | ✅ | ✅ | ✅ | — | ◐ milik sendiri | — |
| | `checkin.override` (QR rusak) | ✅ | ✅ | — | — | — | — |
| **Kuota & Harga** | `quota.view` | ✅ | ✅ | ✅ | ✅ | — | — |
| | `quota.manage` | ✅ | ✅ | — | — | — | — |
| | `price.manage` | ✅ | ✅ | — | — | — | — |
| **Refund** | `refund.request` | — | ✅ | ✅ | — | — | — |
| | `refund.approve` (≠ pengaju) | ✅ | ✅ | — | — | — | — |
| **Buku Kas** | `ledger.view` | ✅ | 👁 | — | ✅ | — | — |
| | `ledger.entry_manual` (pengeluaran/koreksi) | ◐ setujui | — | — | ✅ | — | — |
| **Rekonsiliasi** | `reconciliation.manage` | — | — | — | ✅ | — | — |
| | `reconciliation.approve` | ✅ | — | — | — | — | — |
| **Tutup Harian** | `closing.perform` | — | — | — | ✅ | — | — |
| | `closing.reopen` | ✅ | — | — | — | — | — |
| **Laporan Keuangan** | `report.finance.view` | ✅ | ✅ | — | ✅ | — | — |
| | `report.finance.export` | ✅ | — | — | ✅ | — | — |
| **Konten** | `content.news.manage`, `content.gallery.manage`, `content.faq.manage` | ✅ | ◐ lihat | — | — | — | ✅ |
| **Ulasan & Buku Tamu** | `review.moderate`, `guestbook.moderate` | ✅ | ✅ | — | — | — | ✅ |
| **Akun & Peran** | `user.manage`, `role.manage` | ✅ | — | — | — | — | — |
| **Pengaturan** | `settings.manage` | ✅ | — | — | 👁 | — | — |
| **Log Aktivitas** | `activity.view` | ✅ | — | — | ◐ bagian keuangan | — | — |
| **Sistem** | `system.backup`, `system.health` | ✅ | — | — | — | — | — |

**Catatan:** ◐ `dashboard.view` menampilkan widget sesuai peran. ◐ `payment.verify` untuk super_admin hanya jika tidak ada petugas lain (*break-glass*, wajib alasan, ditandai di laporan). ◐ `ledger.entry_manual` oleh finance di atas batas nominal `{{ditentukan owner}}` butuh persetujuan super_admin.

### 3.3 Aturan Teknis RBAC

| ID | Aturan | Prioritas |
|---|---|---|
| AUTHZ-1 | Tabel `roles`, `permissions`, `role_permissions`, `users.role_id`. Key izin berformat `modul.aksi` (huruf kecil, titik) | P0 |
| AUTHZ-2 | Izin dicek di **middleware route** *dan* di service untuk aksi sensitif (pertahanan berlapis) | P0 |
| AUTHZ-3 | Default tolak: route tanpa izin yang terdaftar = 403 (tes otomatis memeriksa tidak ada route tanpa middleware izin) | P0 |
| AUTHZ-4 | Daftar izin & peran awal ada di `config/permissions.php` dan `config/roles.php`, di-seed oleh migrasi; mengubah peran lewat UI Super Admin | P1 |
| AUTHZ-5 | **Maker-checker:** `refund.approve` ≠ `refund.request` oleh orang yang sama; `reconciliation.approve` ≠ `reconciliation.manage` pada batch yang sama | P0 |
| AUTHZ-6 | Aksi sensitif (setujui refund, buka periode, ubah rekening tujuan, ubah peran) meminta **konfirmasi ulang password** | P1 |
| AUTHZ-7 | **Masking data pribadi per peran**: gate hanya lihat inisial nama + jumlah tiket + tanggal + status; finance lihat kode booking, nama, nominal (nomor HP disamarkan); content tidak melihat PII | P0 |
| AUTHZ-8 | Override izin per-user dihindari; jika perlu, disimpan di tabel terpisah dan tercatat di log | P2 |
| AUTHZ-9 | Akun non-aktif tidak bisa login dan sesinya dicabut; akun tidak dihapus (agar jejak audit utuh) | P0 |

### 3.4 Apa yang Normal Dimiliki Website Reservasi yang Skalabel
Daftar kelengkapan; yang ✅ sudah ada di v3, ➕ ditambah v4:

| Area | Kelengkapan |
|---|---|
| Reservasi | ✅ kuota real-time · ✅ kode booking · ➕ stepper mobile-first · ➕ countdown batas bayar · ➕ booking walk-in oleh front office |
| Pembayaran | ✅ upload bukti & verifikasi · ➕ kode unik · ➕ tolak + alasan standar · ➕ refund parsial (P2) |
| Tiket | ✅ PDF + QR · ➕ token ter-hash · ➕ validasi tanggal · ➕ unduh ulang aman |
| Operasional | ✅ scanner · ➕ override check-in · ➕ penjadwalan kuota massal (libur/event) · ➕ penutupan hari tertentu |
| Keuangan | ✅ ledger · ✅ rekonsiliasi · ➕ pengeluaran terstruktur · ➕ laporan dari ledger |
| Komunikasi | ✅ WA · ➕ antrean notifikasi (*outbox*) dengan retry · ➕ template pesan di setting |
| Administrasi | ✅ role · ➕ RBAC tabel · ➕ log aktivitas lengkap · ➕ backup |
| Kualitas | ➕ tes otomatis · ➕ CI · ➕ health check |

---

## 4. Alur Sistem

### 4.1 Ringkasan Perjalanan

```
PENGUNJUNG                  SISTEM                          STAF
──────────                  ──────                          ────
Pilih tanggal & tiket ───▶  Cek kuota, hitung harga
Isi data diri ───────────▶  Booking MENUNGGU_BAYAR,
                            kuota dikunci, set deadline,
                            WA instruksi bayar
Transfer & unggah bukti ─▶  MENUNGGU_VERIFIKASI ─────────▶  Front Office/Finance
                                                            cek bukti
                            ◀─── TERVERIFIKASI ───────────  Verifikasi
                            LUNAS + ledger IN + QR + tiket
Terima tiket (WA/halaman) ◀─ WA + halaman cek booking
Datang & tunjukkan QR ───▶  Scan ───────────────────────▶  Petugas Gerbang
                            SELESAI (check-in tercatat)
                            ─ Rekonsiliasi & tutup harian ▶ Finance
```

Dua kanal booking, satu alur yang sama: `ONLINE` (pengunjung sendiri) dan `COUNTER` (front office input untuk tamu walk-in; pembayaran tunai langsung terverifikasi ke akun *Kas Loket*).

### 4.2 Tabel Transisi Status Booking (Sumber Kebenaran)

Status: `MENUNGGU_BAYAR`, `MENUNGGU_VERIFIKASI`, `DITOLAK`, `LUNAS`, `SELESAI`, `REFUND_DIAJUKAN`, `DIREFUND`, `KEDALUWARSA`, `DIBATALKAN`, `TIDAK_HADIR`.
**Status akhir (terminal):** `SELESAI`, `DIREFUND`, `KEDALUWARSA`, `DIBATALKAN`, `TIDAK_HADIR`.

| # | Dari | Ke | Pelaku (izin) | Syarat (guard) | Efek samping (dalam 1 transaksi) |
|---|---|---|---|---|---|
| T1 | *(baru)* | `MENUNGGU_BAYAR` | Pengunjung / `reservation.create` | Kuota cukup, tanggal valid & tidak lewat | Kunci kuota atomik, snapshot harga ke `booking_items`, set `payment_deadline_at`, kode unik, outbox WA |
| T2 | `MENUNGGU_BAYAR` | `MENUNGGU_VERIFIKASI` | Pengunjung | Belum lewat deadline; file valid | Buat `payments` (attempt+1) + `payment_proofs` (hash) |
| T3 | `MENUNGGU_VERIFIKASI` | `LUNAS` | `payment.verify` | Nominal diterima = nominal tagihan (atau selisih ditangani, 4.5); pemverifikasi ≠ kosong | `payments`→TERVERIFIKASI, **ledger IN**, buat QR token (hash), outbox WA tiket |
| T4 | `MENUNGGU_VERIFIKASI` | `DITOLAK` | `payment.reject` | Alasan wajib | `payments`→DITOLAK, outbox WA alasan |
| T5 | `DITOLAK` | `MENUNGGU_VERIFIKASI` | Pengunjung | Belum lewat deadline | Attempt baru |
| T6 | `MENUNGGU_BAYAR` / `DITOLAK` | `KEDALUWARSA` | Sistem | `payment_deadline_at < NOW()` | **Lepas kuota** (idempoten), outbox WA |
| T7 | `MENUNGGU_BAYAR` | `DIBATALKAN` | Pengunjung / `reservation.cancel` | Belum ada pembayaran terverifikasi | Lepas kuota |
| T8 | `LUNAS` | `SELESAI` | `checkin.scan` | Hari ini = tanggal kunjungan; token valid & belum dipakai | Catat `checkins` (siapa, kapan), token ditandai terpakai |
| T9 | `LUNAS` | `SELESAI` (manual) | `checkin.override` | Alasan wajib | Sama dengan T8 + ditandai "manual" |
| T10 | `LUNAS` | `REFUND_DIAJUKAN` | `refund.request` | Belum SELESAI; alasan wajib | Buat `refunds` (DIAJUKAN) |
| T11 | `REFUND_DIAJUKAN` | `DIREFUND` | `refund.approve` (≠ pengaju) | Nominal ≤ yang terbayar | `payments`→DIREFUND_*, **ledger OUT**, lepas kuota bila tanggal belum lewat |
| T12 | `REFUND_DIAJUKAN` | `LUNAS` | `refund.approve` | Alasan penolakan wajib | `refunds`→DITOLAK |
| T13 | `LUNAS` | `TIDAK_HADIR` | Sistem | Tanggal kunjungan sudah lewat tanpa check-in | Catat; kebijakan refund/reschedule mengikuti aturan owner |

Aturan global:
- **Hanya** `BookingStateMachine::transition()` yang boleh mengubah status. Tidak ada `UPDATE reservasi SET status` di tempat lain (dilarang dan dicek oleh tes statis).
- Setiap transisi: `SELECT ... FOR UPDATE` pada booking → cek guard → tulis efek → catat `booking_status_history` → `COMMIT`. Gagal di mana pun = `ROLLBACK` total.
- Transisi tidak ada dalam tabel = ditolak dan dicatat. Transisi ke status yang sama = ditolak (idempoten).
- Efek ledger memakai constraint unik sehingga dua klik bersamaan menghasilkan satu entri.

### 4.3 Status Pembayaran (terpisah)
`BELUM_ADA_BUKTI → MENUNGGU_VERIFIKASI → TERVERIFIKASI | DITOLAK → DIREFUND_SEBAGIAN | DIREFUND_PENUH`, ditambah `VOID` untuk pembatalan administratif. Status pembayaran **diturunkan dari transisi booking**, tidak diubah bebas.

### 4.4 Batas Bayar & Kedaluwarsa

| ID | Aturan |
|---|---|
| FLOW-1 | `payment_deadline_at` dihitung dan **disimpan saat booking dibuat** (`created_at + setting`), sehingga mengubah setting tidak menggeser booking lama |
| FLOW-2 | Kedaluwarsa berlaku untuk **semua** booking `MENUNGGU_BAYAR`/`DITOLAK` yang punya deadline (kanal COUNTER-tunai langsung lunas sehingga tidak relevan) |
| FLOW-3 | Query kedaluwarsa: `status IN (...) AND payment_deadline_at < NOW()` dengan row lock, batch kecil, idempoten, aman dijalankan paralel |
| FLOW-4 | **Tiga pemicu** agar tidak bergantung cron: (a) *lazy expiry* ringan di request tertentu (cek kuota/landing, dibatasi 1× per N menit lewat penanda di DB), (b) endpoint `scripts/cron_expire.php` / `/cron/expire?token=...` untuk cron nyata atau layanan pemicu eksternal, (c) pengecekan langsung saat booking dibuka |
| FLOW-5 | Pelepasan kuota idempoten: `UPDATE daily_quotas SET used = used - n WHERE date=? AND used >= n`, dan hanya dijalankan bersama perubahan status |
| FLOW-6 | Keputusan backlog: pembayaran **DITOLAK boleh unggah ulang** selama belum lewat deadline *(rekomendasi; ubah bila owner berbeda)* |
| FLOW-7 | Pengingat WA sebelum kedaluwarsa (mis. 30 menit sebelumnya), sekali saja (idempoten via outbox) |

### 4.5 Nominal, Kode Unik, dan Selisih

| Aturan | Isi |
|---|---|
| PAY-1 | `amount_due = total_tiket + unique_code`, dikunci server saat booking; klien tidak bisa mengubah |
| PAY-2 | Ledger mencatat dua baris terpisah: `IN / TIKET = total_tiket` dan `IN / KODE_UNIK = unique_code` *(keputusan owner: bagian 14 no. 4)* |
| PAY-3 | Nominal diterima ≠ tagihan: finance memilih **terima**, **tolak**, atau **kurang bayar** (booking tetap menunggu pelunasan); semua tercatat |
| PAY-4 | Hash file bukti dicek; bukti identik pada booking lain memunculkan peringatan |
| PAY-5 | Bukti disimpan di `storage/` di luar web root dan diakses lewat controller ber-izin |
| PAY-6 | Rekening tujuan hanya dari `settings`/`cash_accounts`; perubahannya memicu log + notifikasi ke super_admin |

### 4.6 Tiket & QR

| ID | Aturan |
|---|---|
| TKT-1 | QR berisi **token acak 128-bit**, bukan kode booking. Di DB disimpan **hash** token (`sha256`) |
| TKT-2 | Scan memeriksa: token cocok → status `LUNAS` → tanggal = hari ini → belum dipakai. Alasan penolakan jelas ("sudah dipakai pukul …", "bukan tanggal kunjungan", "belum lunas") |
| TKT-3 | Scan ganda bersamaan aman: satu sukses (row lock / update kondisional) |
| TKT-4 | PDF tiket **dibuat on-demand** dari data, tidak disimpan permanen; dikirim via **signed URL** berkedaluwarsa (HMAC + expiry), bukan path file |
| TKT-5 | Halaman cek booking butuh kode booking **+ 4 digit akhir no. HP** (anti tebak-tebakan) |

### 4.7 Refund
- Penuh pada fase ini; **parsial = P2** (`sum(refund) ≤ terbayar`, satu ledger OUT per refund).
- Maker-checker (AUTHZ-5). Penyetuju memilih apakah kuota dikembalikan (otomatis bila tanggal kunjungan belum lewat).
- Kebijakan potongan/biaya refund = keputusan owner (bagian 14).

---

## 5. Model Data & Modul Keuangan

### 5.1 Daftar Tabel Target

| Kelompok | Tabel |
|---|---|
| **Akses** | `users`, `roles`, `permissions`, `role_permissions`, `user_sessions` (opsional) |
| **Booking** | `bookings` (ganti nama dari `reservasi` bertahap), `booking_items`, `booking_status_history`, `checkins`, `daily_quotas`, `ticket_types`, `ticket_prices` |
| **Pembayaran** | `payments`, `payment_proofs`, `refunds` |
| **Keuangan** | `cash_accounts`, `ledger_entries`, `ledger_reconciliations`, `reconciliation_batches`, `expenses`, `daily_closings` |
| **Operasional** | `settings`, `notification_outbox`, `rate_limits`, `activity_logs` |
| **Konten** | `news`, `gallery`, `faq`, `reviews`, `guestbook` |

> Nama tabel Inggris dianjurkan agar seragam (v3 mencampur `reservasi`, `kuota_harian`, `payments`). Penggantian nama dilakukan **bertahap lewat view kompatibilitas**, bukan sekaligus.

### 5.2 Perubahan Skema yang Direkomendasikan

| ID | Perubahan | Alasan |
|---|---|---|
| DB-1 | `booking_items` (booking_id, ticket_type_id, qty, unit_price, subtotal) sebagai **snapshot harga** | Harga lama tak hilang saat tarif berubah (R-09) |
| DB-2 | `ticket_prices` dengan `effective_from`; perubahan harga hanya berlaku ke depan | Riwayat harga & audit |
| DB-3 | Hapus bertahap kolom ganda di booking (`bukti_bayar`, `metode_bayar`, `is_checkin`, `waktu_checkin`): turunkan dari `payments`/`checkins`; sediakan **view kompatibilitas** selama transisi | Hindari drift |
| DB-4 | Kolom `payment_deadline_at`, `channel` (ONLINE/COUNTER), `visit_date` terindeks | FLOW-1, query cepat |
| DB-5 | Uang = `BIGINT` rupiah (tanpa FLOAT/DECIMAL desimal); `CHECK (amount > 0)` bila engine mendukung, plus validasi di service | Akurasi |
| DB-6 | Semua tabel `InnoDB`, `utf8mb4`, FK eksplisit, indeks pada kolom filter (status, visit_date, created_at, source_type+source_id) | Integritas & performa |
| DB-7 | `activity_logs`, `notification_outbox`, `rate_limits` dibuat lewat migrasi (R-06) | Jejak & keandalan |
| DB-8 | Hapus procedure/view logika dari `migration_v3_state_machine.sql`; pindahkan ke PHP | R-10 |
| DB-9 | Migrasi bernomor `database/migrations/V4_001_*.sql`, idempoten, dengan `database/rollbacks/` pasangan | Aman & dapat diulang |

### 5.3 Buku Kas (Ledger): Aturan

- **Append-only mutlak.** `ledger_entries` hanya di-INSERT. Tidak ada kolom yang di-UPDATE.
- **Rekonsiliasi tidak mengubah ledger.** Dicatat di `ledger_reconciliations(ledger_entry_id UNIQUE, batch_id, reconciled_by, reconciled_at)`. Membatalkan rekonsiliasi = baris baru bertanda `reverted` + alasan (tetap ada jejak).
- **Koreksi = entri pembalik** (`reverses_entry_id`), alasan wajib, nominal besar butuh persetujuan.
- `entry_no` berurutan per tahun tanpa celah; unik di DB.
- Constraint unik `uq_ledger_source (source_type, source_id, category, reverses_entry_id)` menjaga "1 sumber = 1 entri".
- Saldo selalu **dihitung** dari entri, tidak disimpan sebagai kolom yang bisa diedit.
- Perlindungan lapis kedua: pada produksi MySQL, akun DB aplikasi dicabut hak `UPDATE/DELETE` pada `ledger_entries` & `activity_logs`. Pada hosting tanpa kontrol hak (mis. gratis/TiDB), perlindungan di level aplikasi + tes.

Kategori: `TIKET`, `KODE_UNIK`, `REFUND`, `BIAYA_OPERASIONAL`, `KOREKSI`, `LAINNYA`, `SALDO_AWAL`, `BACKFILL`.

### 5.4 Satu Sumber Kebenaran untuk Laporan
- **Semua laporan keuangan membaca ledger saja.** `laporan_keuangan` dimigrasikan menjadi `expenses` + entri ledger `OUT`/`IN` dan dipensiunkan.
- Dua sudut pandang pendapatan tersedia: berdasarkan **tanggal uang diterima** (kas) dan berdasarkan **tanggal kunjungan** (layanan), karena tiket bisa dibayar jauh sebelum datang.
- Tes otomatis: total dashboard = total laporan = total ledger.

### 5.5 Pajak (perlu konfirmasi ahli)
Dokumen ini bukan nasihat pajak. Titik desain:
- Tarif, nama pajak, dan apakah harga tiket sudah termasuk pajak menjadi **setting** (`tax_name`, `tax_rate`, `price_includes_tax`). `TODO: konfirmasi akuntan` (jenis pajak daerah, tarif sesuai peraturan daerah setempat, perlakuan pendapatan diterima di muka).
- Laporan memisahkan **dasar pengenaan**, **pajak**, dan **neto**. Pajak tidak dicampur dengan "laba" sebelum perlakuannya dikonfirmasi.

### 5.6 Rekonsiliasi, Tutup Harian, Audit Log

| ID | Aturan | Prioritas |
|---|---|---|
| REC-1 | Batch rekonsiliasi per akun & periode; finance mencocokkan entri ledger ↔ mutasi bank (nominal + kode unik + tanggal); selisih wajib dijelaskan | P1 |
| REC-2 | Entri yang sudah di-batch yang disetujui tidak bisa dilepas kecuali dengan pembatalan tercatat oleh super_admin | P0 |
| REC-3 | **Tutup harian**: total IN/OUT per akun, saldo fisik vs sistem, selisih; periode tertutup **mengunci** entri tanggal itu (entri baru bertanggal di periode tertutup ditolak) | P0 |
| REC-4 | Buka kembali periode: hanya `closing.reopen`, alasan wajib, tercatat | P0 |
| AUD-1 | `activity_logs`: waktu, user, peran, IP, aksi, entitas+id, **nilai sebelum/sesudah (JSON)**, hasil (sukses/ditolak) | P0 |
| AUD-2 | Wajib dicatat: login/gagal login/logout, transisi status, verifikasi/tolak, entri ledger, refund, tutup/buka periode, perubahan harga/kuota/setting/akun/peran, ekspor data | P0 |
| AUD-3 | Log tidak berisi password, token, atau isi bukti; tidak ada fitur hapus di UI | P0 |
| AUD-4 | Retensi log & data keuangan `{{ditentukan owner/akuntan}}` | P1 |

### 5.7 Laporan Wajib

| Laporan | Sumber | Pengguna |
|---|---|---|
| Buku kas harian per akun | ledger | finance |
| Rekap pendapatan (hari/bulan/tahun, jenis tiket, metode) | ledger + booking_items | finance, manager |
| Pendapatan per tanggal kunjungan | booking_items + ledger | finance |
| Pembayaran tertunda (+ umur) | payments | front_office, finance |
| Refund & pembatalan | refunds | manager, finance |
| Rekonsiliasi & selisih | reconciliations | finance |
| Tutup harian | daily_closings | finance, super_admin |
| Okupansi & kuota (terjual, hadir, tidak hadir) | quotas + checkins | manager |
| Jejak audit | activity_logs | super_admin |

Semua laporan: filter periode, ekspor CSV, ekspor tercatat di log, CSRF + izin.

---

## 6. Arsitektur & Struktur Folder

### 6.1 Prinsip Struktur
- **Hanya `public/` yang boleh diakses web.** Kode, konfigurasi, bukti bayar, dan log berada di luar.
- **Berlapis:** Route → Controller (tipis) → Service/Domain (aturan bisnis) → Repository (SQL).
- **Satu tanggung jawab per file.** Batas ukuran ada di `AGENTS.md`.
- **Pendekatan *strangler*:** halaman lama tetap jalan sementara modul dipindah satu per satu ke struktur baru.

### 6.2 Struktur Target

```
waterboom/
├── public/                        # ← satu-satunya web root
│   ├── index.php                  # front controller (publik + admin lewat prefix /admin)
│   ├── .htaccess                  # rewrite ke index.php, tolak file sensitif
│   └── assets/
│       ├── css/                   # lihat bagian 7
│       ├── js/
│       ├── img/
│       └── fonts/
├── app/
│   ├── Core/                      # kerangka kecil buatan sendiri
│   │   ├── Router.php  Request.php  Response.php  View.php
│   │   ├── Database.php  Session.php  Csrf.php  Validator.php  Env.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Site/              # Landing, Reservation, BookingStatus, Guestbook, Review
│   │   │   └── Admin/             # Dashboard, Reservation, Payment, Checkin, Refund,
│   │   │                          # Ledger, Reconciliation, Closing, Expense, Report,
│   │   │                          # Quota, Price, Content, User, Role, Setting, ActivityLog
│   │   ├── Middleware/            # Auth, ActiveSession, Csrf, Permission, RateLimit, SecurityHeaders
│   │   └── Requests/              # validasi per use case (CreateReservationRequest, ...)
│   ├── Domain/
│   │   ├── Booking/               # BookingService, BookingStateMachine, QuotaService,
│   │   │                          # PriceCalculator, TicketTokenService, BookingRepository
│   │   ├── Payment/               # PaymentService, ProofStorage, UniqueCodeGenerator
│   │   ├── Refund/                # RefundService
│   │   ├── Finance/               # LedgerService, ReconciliationService, ClosingService,
│   │   │                          # ExpenseService, ReportService
│   │   ├── Access/                # PermissionChecker, RoleRepository, UserService
│   │   ├── Notification/          # Outbox, WhatsAppClient (+ driver log untuk demo)
│   │   ├── Audit/                 # ActivityLogger
│   │   └── Content/
│   └── Support/                   # Money, Pagination, Csv, Clock, Str, SignedUrl
├── resources/
│   └── views/
│       ├── layouts/               # site.php, admin.php
│       ├── components/            # button, badge, card, table, modal, form-field, pagination
│       ├── partials/              # header, sidebar, flash
│       ├── site/                  # halaman publik
│       └── admin/                 # halaman admin per modul
├── routes/                        # site.php, admin.php, internal.php (cron/health)
├── config/                        # app.php, database.php, permissions.php, roles.php, states.php
├── database/
│   ├── migrations/                # V4_001_*.sql ...
│   ├── rollbacks/
│   └── seeders/                   # roles, permissions, cash_accounts, demo_data
├── storage/                       # TIDAK web-accessible
│   ├── proofs/  cache/  logs/
├── scripts/                       # cron_expire.php, integrity_check.php, build_assets.php, reset_demo.php
├── tests/                         # Unit/ Integration/ Concurrency/ Permission/ Static/ run.php
├── docs/                          # ARCHITECTURE.md, DEPLOYMENT.md, ERD.md, adr/, audit/
├── .env.example  .gitignore  AGENTS.md  PRD.md  README.md  CHANGELOG.md
```

### 6.3 Pemetaan dari Struktur Lama

| Lama (v3) | Baru (v4) |
|---|---|
| `admin/reservasi.php` + `handlers/reservasi_handler.php` | `Controllers/Admin/ReservationController` + `PaymentController` + `RefundController` + view `admin/reservations/*` |
| `admin/audit_keuangan.php` + `keuangan_handler.php` | `ReportController` + `ExpenseController` + `ReportService` (dari ledger) |
| `admin/buku_kas.php` + `buku_kas_handler.php` | `LedgerController` |
| `admin/reconciliation.php` + handler | `ReconciliationController` + `ReconciliationService` |
| `admin/kelola_admin.php` | `UserController` + `RoleController` |
| `admin/handlers/hapus.php`, `simpan.php`, `export.php` | **Dihapus**; digantikan aksi `destroy/store/export` per controller |
| `app/Services/BookingStateService.php` | `Domain/Booking/BookingStateMachine.php` + `BookingService.php` |
| `app/PaginationHelper.php` | `Support/Pagination.php` + komponen view `pagination` |
| `css/admin_style.css` | `public/assets/css/**` (bagian 7) |
| `config/` lama + `app/init.php` | `config/*.php` + `.env` + `Core/Env.php` |

### 6.4 Aturan Arsitektur

| ID | Aturan |
|---|---|
| ARCH-1 | Controller hanya: ambil input → validasi → panggil service → kembalikan respons. Tidak ada SQL & aturan bisnis |
| ARCH-2 | Service memegang transaksi & aturan; Repository hanya SQL (prepared statement) |
| ARCH-3 | View tidak berisi logika bisnis atau query; hanya tampilan (escape `e()` selalu) |
| ARCH-4 | Dependensi masuk lewat konstruktor (sederhana, tanpa container berat) agar mudah di-mock |
| ARCH-5 | Aturan status & izin hidup di `config/states.php` dan `config/permissions.php` (data), bukan tersebar di kode |
| ARCH-6 | Tidak ada procedure/trigger/logika di DB (DB-8) |
| ARCH-7 | Tidak ada dependensi Composer baru tanpa persetujuan owner |

### 6.5 Konfigurasi & Lingkungan
- `.env` (tidak di-commit) dan `.env.example`; `APP_ENV = local | demo | production`.
- Validasi saat boot: variabel wajib kosong = aplikasi berhenti dengan pesan jelas (tanpa membocorkan nilai).
- Tidak ada path absolut OS tertentu; semua relatif terhadap root.

---

## 7. Frontend: Design System & CSS Atomic

### 7.1 Masalah yang Diselesaikan
Satu file CSS besar, warna/spasi tak seragam, komponen disalin-tempel, JS inline. Target: **modular, bisa dipakai ulang, dan mudah dimengerti**.

### 7.2 Arsitektur CSS (Atomic Design + `@layer`)

```
public/assets/css/
├── app.css            # manifest: hanya @layer + @import (tanpa aturan)
├── 0-tokens/          # variabel saja
│   ├── colors.css  typography.css  spacing.css  radius-shadow.css  themes.css
├── 1-base/            # reset, elemen HTML polos (body, a, h1–h6, form dasar)
├── 2-layout/          # struktur halaman: container, grid, stack, sidebar-shell, topbar
├── 3-atoms/           # button, input, label, badge, icon, avatar, spinner
├── 4-molecules/       # form-field, stat-card, search-bar, pagination, toast, alert
├── 5-organisms/       # data-table, sidebar, drawer, modal, filter-bar, ticket-card, stepper
├── 6-pages/           # penyesuaian khusus halaman (seminimal mungkin)
└── 7-utilities/       # .u-hidden, .u-mt-4, .u-text-muted (kecil, jarang)
```

```css
/* app.css, manifest saja */
@layer tokens, base, layout, atoms, molecules, organisms, pages, utilities;
@import "0-tokens/colors.css" layer(tokens);
@import "1-base/reset.css"    layer(base);
/* ...dst. Urutan layer = urutan prioritas, tanpa !important */
```

| ID | Aturan CSS |
|---|---|
| CSS-1 | **Satu komponen = satu file**, ≤ **200 baris**. Lebih dari itu dipecah |
| CSS-2 | Semua warna, spasi, radius, bayangan, ukuran huruf berasal dari **token** (CSS variable). Dilarang nilai hex/px sembarang di komponen |
| CSS-3 | Penamaan **BEM-lite + prefix**: `.c-button`, `.c-button--primary`, `.c-button__icon`; layout `.l-stack`; utilitas `.u-*`; state `.is-active`, `.has-error` |
| CSS-4 | **Tanpa `!important`** dan tanpa selector berantai > 3 tingkat; urutan ditentukan `@layer` |
| CSS-5 | Dark mode lewat token tema (`[data-theme="dark"]`), komponen **tidak** punya nilai warna sendiri |
| CSS-6 | Mobile-first (`min-width` media query); breakpoint dari token |
| CSS-7 | Komponen baru dibuat jika pola dipakai ≥ 2 tempat; sebelum itu, gunakan yang ada |
| CSS-8 | Produksi memakai **bundel** (`scripts/build_assets.php` menggabungkan file `@import` menjadi satu `app.min.css`) agar jumlah request kecil (penting di hosting gratis yang menghitung *hits*) |
| CSS-9 | Satu bundel publik (`site.css`) dan satu bundel admin (`admin.css`), keduanya berbagi token & atom |

**Contoh token (ringkas):**

```css
/* 0-tokens/colors.css */
:root {
  --color-primary: #0a84c6;   --color-primary-contrast: #ffffff;
  --color-success: #1f9d55;   --color-warning: #d98e04;   --color-danger: #d64545;
  --color-surface: #ffffff;   --color-surface-2: #f5f7fa;
  --color-text: #1b2430;      --color-text-muted: #5b6676;  --color-border: #dfe4ea;
  --space-1: .25rem; --space-2: .5rem; --space-3: .75rem; --space-4: 1rem; --space-6: 1.5rem; --space-8: 2rem;
  --radius-sm: .375rem; --radius-md: .625rem; --radius-lg: 1rem;
  --font-sans: "Inter", system-ui, -apple-system, "Segoe UI", sans-serif;
  --text-sm: .875rem; --text-md: 1rem; --text-lg: 1.25rem; --text-xl: 1.5rem;
}
[data-theme="dark"] {
  --color-surface: #0f1620; --color-surface-2: #172230;
  --color-text: #e8edf3;    --color-text-muted: #9aa7b8; --color-border: #26344a;
}
```

### 7.3 Pemetaan Status → Warna Badge (konsisten di seluruh sistem)

| Status | Token | Teks pengguna (ID) |
|---|---|---|
| `MENUNGGU_BAYAR` | warning | Menunggu pembayaran |
| `MENUNGGU_VERIFIKASI` | info | Sedang diverifikasi |
| `LUNAS` | success | Lunas |
| `SELESAI` | neutral | Sudah check-in |
| `DITOLAK` | danger | Bukti ditolak |
| `REFUND_DIAJUKAN` | warning | Refund diajukan |
| `DIREFUND` | neutral | Dana dikembalikan |
| `KEDALUWARSA` / `DIBATALKAN` / `TIDAK_HADIR` | muted | Kedaluwarsa / Dibatalkan / Tidak hadir |

Satu komponen `status-badge` dipakai di semua halaman; label & warna dibaca dari `config/states.php`.

### 7.4 JavaScript
- Modul ES per fitur: `assets/js/core/` (http, csrf, toast, theme), `components/` (drawer, modal, confirm, table-filter), `pages/` (scanner, reservation-form, dashboard-chart).
- **Tanpa JS inline** di view; halaman memuat modul lewat atribut `data-module="..."`.
- ≤ **250 baris per modul**. Library (SweetAlert2, Chart.js, Lucide) di-*pin* versinya dengan SRI, atau di-host sendiri.
- Aksi hapus/arsip memakai satu komponen `confirm` (SweetAlert) + POST ber-CSRF.

### 7.5 Pola Desain & UX

| Area | Pedoman |
|---|---|
| **Prinsip** | Konsisten, jelas, ramah awam; satu aksi utama per layar |
| **Pengunjung (mobile-first)** | Stepper: *Pilih tanggal → Data diri → Bayar → Tiket*; kuota & harga selalu terlihat; hitung mundur batas bayar; tombol besar (≥ 44px); instruksi bayar ringkas dengan tombol "salin nomor rekening" |
| **Halaman status booking** | Satu kartu: status (badge), langkah berikutnya, tombol aksi tunggal (mis. "Unggah bukti"), unduh tiket bila lunas |
| **Admin: pola daftar** | Judul + aksi utama → filter bar (cari, status, tanggal) → tabel → pagination (satu komponen). Mobile: tabel jadi kartu |
| **Admin: pola detail** | Header ringkas (kode, status), panel info, panel pembayaran, riwayat status (timeline), tombol aksi sesuai izin (yang tak boleh disembunyikan **dan** ditolak server) |
| **Form** | Label jelas, error di bawah kolom, nilai tetap terisi saat gagal (flash), validasi sisi server selalu menang |
| **State kosong/gagal/loading** | Setiap daftar punya *empty state* informatif, *skeleton* saat memuat, dan pesan error ramah |
| **Aksesibilitas** | Kontras teks ≥ 4.5:1, fokus terlihat, label pada input, `aria-live` untuk toast, hormati `prefers-reduced-motion` |
| **Gaya visual** | Pertahankan karakter yang sudah ada (bersih, glassmorphism/bento ringan di landing); admin lebih padat dan datar demi keterbacaan |

---

## 8. Standar Endpoint, Keamanan Baseline, dan Observability

### 8.1 Konvensi Route
Halaman server-rendered dengan pola resource; JSON hanya untuk interaksi AJAX.

| Pola | Contoh |
|---|---|
| Daftar | `GET /admin/reservations` |
| Detail | `GET /admin/reservations/{id}` |
| Aksi domain | `POST /admin/reservations/{id}/payments/{pid}/verify`, `.../reject`, `POST /admin/reservations/{id}/refunds` |
| JSON internal | `GET /admin/api/dashboard/stats`, `POST /admin/api/checkin/scan` (`{ ok, data, error }`) |
| Publik | `GET /reservasi`, `POST /reservasi`, `GET /booking/{kode}`, `POST /booking/{kode}/proof` |
| Sistem | `GET /health`, `GET /cron/expire?token=…` (bertoken, hanya untuk pemicu terjadwal) |

Aturan: kata benda jamak, method sesuai fungsi (GET tidak mengubah data), semua POST ber-CSRF, urutan middleware **sesi aktif → CSRF → izin → validasi → service**, respons error seragam, rate limit per rute sensitif (login, booking, upload, scan, cek booking).

### 8.2 Baseline Keamanan (melanjutkan audit sebelumnya)
Prepared statement di mana-mana · escape output · password argon2id/bcrypt · cookie sesi `HttpOnly+Secure+SameSite` & regenerasi ID saat login · rate limit login · upload: validasi isi (finfo), nama acak, di luar web root · header keamanan (CSP, nosniff, Referrer-Policy, HSTS) · `.env` & `storage/` tidak terjangkau web · tidak ada secret di repo (scan sebelum commit) · password seed default **wajib diganti** (atau dinonaktifkan di mode production) · error ke pengguna generik, detail ke log.

### 8.3 Observability
`activity_logs` untuk aksi bisnis, `storage/logs/app.log` untuk error teknis (tanpa PII), `/health` memeriksa koneksi DB & versi migrasi, `notification_outbox` menyimpan status kirim WA + percobaan + error.

---

## 9. Strategi Pengujian & CI

Tanpa dependensi baru: runner ringan `tests/run.php` dengan helper `assertEquals/assertThrows`. (PHPUnit sebagai *dev-dependency* boleh jika owner setuju.)

| Jenis | Isi |
|---|---|
| **Unit** | `PriceCalculator`, `Money`, `UniqueCodeGenerator`, tabel transisi `BookingStateMachine` (semua transisi sah lolos, semua yang tidak sah ditolak) |
| **Integrasi** | Alur lengkap T1→T3→T8; refund T10→T11; kedaluwarsa T6 melepas kuota tepat sekali |
| **Konkurensi** | 50 booking paralel pada slot terakhir → sukses sebanyak sisa; 2 verifikasi bersamaan → 1 ledger; 2 scan bersamaan → 1 sukses; expire × unggah bukti bersamaan → konsisten |
| **Invarian keuangan** | Skrip `integrity_check.php` (semua query di PRD v3 bagian 9.3, plus: tiap `payments` TERVERIFIKASI punya tepat 1 ledger TIKET; total dashboard = laporan = ledger; saldo = ΣIN − ΣOUT; tak ada entri di periode tertutup setelah penutupan) |
| **Matriks peran** | Untuk tiap peran × tiap route: hasil sesuai matriks 3.2 (uji positif & negatif), termasuk tes "tidak ada route tanpa middleware izin" |
| **Statik** | `php -l`, larangan `UPDATE reservasi SET status` di luar state machine, larangan `innerHTML`/query berantai string, batas ukuran file |
| **Keamanan** | IDOR (pengunjung lain), CSRF tanpa token, upload berbahaya, rate limit |
| **CI** | GitHub Actions (gratis untuk repo publik) menjalankan lint + tes dengan layanan MySQL pada setiap push; badge status di README (nilai tambah portfolio) |

---

## 10. Deployment & Mode Demo Portfolio

Ya, agar bisa dinavigasi dari portfolio, aplikasi **harus di-deploy** (atau minimal ada video/screenshot sebagai cadangan). Detail pilihan & langkah: `docs/DEPLOYMENT.md`.

### 10.1 Kebutuhan Mode Demo (`APP_ENV=demo`)

| ID | Aturan |
|---|---|
| DEMO-1 | Data **dummy** saja; seeder `demo_data` membuat booking, pembayaran, dan ledger contoh. Tidak ada data pengunjung asli |
| DEMO-2 | Pengiriman WhatsApp memakai **driver log** (pesan ditampilkan di panel "Kotak Pesan Demo"), tidak memanggil Fonnte |
| DEMO-3 | Banner "Mode Demo" tampil di semua halaman; peringatan agar tidak memasukkan data pribadi asli |
| DEMO-4 | Akun demo per peran (`gate`, `front_office`, `finance`, `manager`, …) tersedia di halaman login demo; password hanya untuk demo dan **tidak dipakai di produksi** |
| DEMO-5 | **Reset otomatis** data demo berkala (lazy reset di request, bukan cron) dan tombol reset oleh super_admin demo |
| DEMO-6 | Unggahan dibatasi (ukuran kecil, jumlah per sesi) dan dibersihkan saat reset; rate limit ketat |
| DEMO-7 | Pembayaran disimulasikan: tombol "Simulasikan transfer" menyediakan bukti contoh agar penonton bisa menjalankan alur penuh tanpa uang asli |
| DEMO-8 | Fitur berisiko (ekspor data massal, ubah rekening, hapus akun) dinonaktifkan di demo |

### 10.2 Batasan Lingkungan Hosting Gratis yang Memengaruhi Desain
- Biasanya **tidak ada cron** → FLOW-4 (lazy expiry) wajib.
- Ada batas **hits/hari** → bundel CSS/JS, kurangi request kecil, cache statis.
- Penyimpanan bisa terbatas/sementara → tiket dibuat on-demand (TKT-4), bukti demo dibersihkan berkala.
- Tidak ada procedure/trigger atau hak DB granular di sebagian host → logika di PHP (ARCH-6).
- Akses masuk otomatis (webhook/API) ke situs bisa diblokir → alur tidak boleh bergantung pada webhook masuk di demo.

---

## 11. Roadmap Rekonstruksi

Setiap fase: **backup → migrasi → tes gagal dulu → implementasi → tes lolos → komit kecil → catat di CHANGELOG.** Halaman lama tetap berfungsi sampai versi barunya lolos tes paritas.

| Fase | Fokus | Isi utama | Selesai bila |
|---|---|---|---|
| **0. Verifikasi** | Pemetaan | Baca kode; verifikasi tiap temuan bagian 1; buat inventaris route, tabel, status; `gap-analysis.md`; `.env` & `.gitignore`; backup | Gap analysis diterima owner |
| **1. Tambal berisiko** | Keamanan & integritas cepat | `ActiveSession` di scanner & API notifikasi; CSRF logout/ekspor; migrasi `activity_logs`; ganti password seed; hapus handler generik atau tambah izin + CSRF; skrip invarian awal | Backlog audit tertutup, `integrity_check` berjalan |
| **2. Fondasi** | Struktur & tes | `public/` + front controller + Router/Request/Response/View; middleware; `Core/Env`; runner tes; CI; struktur folder baru; modul baru berjalan berdampingan dengan halaman lama | Satu modul contoh (mis. Buku Kas) sudah di struktur baru dengan tes |
| **3. RBAC & akses** | Hak akses | Tabel roles/permissions; seed peran; middleware izin; migrasi data `admin.permissions` JSON → role; masking PII; UI kelola peran; tes matriks | Tes matriks 100% lolos |
| **4. Inti booking** | State machine & kuota | Tabel transisi di `config/states.php`; `BookingStateMachine` murni PHP; `booking_items`; `payment_deadline_at`; kuota atomik & pelepasan idempoten; lazy expiry + endpoint cron; token QR ter-hash + validasi tanggal; tes konkurensi | Tes alur & konkurensi lolos |
| **5. Pembayaran & keuangan** | Uang | Kode unik; verifikasi atomik; ledger sebagai satu-satunya sumber; `ledger_reconciliations`; `expenses` menggantikan `laporan_keuangan`; tutup harian & penguncian; refund maker-checker; laporan dari ledger | Invarian = 0 pelanggaran; laporan = ledger |
| **6. Frontend** | Design system | Token, atoms→organisms, bundel CSS/JS, komponen view, ganti pagination manual, stepper pengunjung, state kosong/loading, dark mode via token | Tidak ada CSS > 200 baris/file; tidak ada JS inline |
| **7. Notifikasi & tiket** | Komunikasi | Outbox + retry; driver log; PDF on-demand + signed URL | Tiket dapat diunduh ulang dengan aman |
| **8. Demo & rilis** | Portfolio | Mode demo, seeder, reset, deploy gratis, README & `PROJECT_STORY.md`, screenshot/video | Demo publik berjalan, aman, dan bisa dinavigasi dari portfolio |

---

## 12. Pemetaan Technical Debt v3 → Rencana v4

| Debt v3 | Status di v4 |
|---|---|
| 7.1-1 Pagination helper belum dipakai merata | Fase 6: satu komponen `pagination`; tes statis melarang pagination manual |
| 7.1-2 Session path XAMPP | Fase 2: konfigurasi via `.env`; path session ditentukan per lingkungan |
| 7.1-3 Drawer hak akses | Diganti UI kelola **peran** (fase 3), bukan JSON per user |
| 7.1-4 Hapus log buku tamu | Fase 1: handler ber-izin+CSRF, lalu dipindah ke `GuestbookController` |
| 7.1-5 Cron expire | FLOW-4 (lazy + endpoint bertoken + skrip) |
| 7.2-1 TicketService tidak terlihat | Fase 0 verifikasi; fase 7 on-demand |
| 7.2-2 WA file lokal | Signed URL (TKT-4) |
| 7.2-3 Unique code belum ada | PAY-1/PAY-2 (fase 5) |
| 7.2-4 Backup DB | `system.backup` (fase 8) + panduan di DEPLOYMENT |
| 7.2-5 `activity_logs` hilang | Fase 1 |
| 7.3 Dark mode chart, print CSS, realtime notif, rate limit config | Fase 6 (token & re-render), `print.css` terpisah, polling ringan (P2), rate limit ke `settings` |

---

## 13. Kriteria Penerimaan (Definition of Done)

- [ ] Semua temuan R-01…R-20 diverifikasi ke kode dan berstatus *fixed / not-applicable / won't-fix (alasan)*
- [ ] Semua perubahan status lewat `BookingStateMachine`; tabel transisi 4.2 teruji penuh
- [ ] Matriks izin 3.2 teruji otomatis (positif & negatif) dan tidak ada route tanpa middleware izin
- [ ] Ledger & log tidak pernah di-UPDATE/DELETE; rekonsiliasi di tabel terpisah
- [ ] `integrity_check.php` = 0 pelanggaran; total dashboard = laporan = ledger
- [ ] Tes konkurensi (booking, verifikasi, scan, expire) lolos
- [ ] Struktur folder 6.2 berlaku; hanya `public/` yang web-accessible
- [ ] CSS modular (≤ 200 baris/file, tanpa `!important`, hanya token); tanpa JS inline
- [ ] Mode demo berfungsi (WA tersimulasi, data dummy, reset otomatis)
- [ ] CI hijau; README, `PROJECT_STORY.md`, `docs/DEPLOYMENT.md` terbarui
- [ ] Item `TODO: konfirmasi akuntan` terdaftar dan diserahkan ke owner

## 14. Pertanyaan Terbuka untuk Owner

1. Batas waktu bayar default: 30 menit / 2 jam / 24 jam?
2. Kebijakan refund & reschedule (syarat, potongan, batas hari)?
3. Apakah ada penjualan tunai di loket (kanal COUNTER) yang ikut di sistem?
4. **Kode unik:** dicatat sebagai pemasukan terpisah, dikembalikan saat refund, atau tidak dipakai?
5. Berapa peran yang dipakai nyata? Boleh semua 6, atau digabung?
6. Batas nominal koreksi/pengeluaran yang butuh persetujuan super_admin?
7. Pajak: jenis, tarif, harga sudah termasuk pajak atau belum? *(konfirmasi akuntan)*
8. Retensi data keuangan & log (berapa tahun)?
9. Apakah demo publik boleh menampilkan nama venue asli, atau dibuat generik/dummy?
10. Rencana jangka menengah ke QRIS dinamis / payment gateway? (menentukan kolom `provider_ref` & webhook)

## 15. Lampiran: Perubahan Penting Dibanding v3

| Aspek | v3 | v4 |
|---|---|---|
| Peran | 3 (permission JSON per user) | 6 peran, RBAC tabel, maker-checker, masking PII |
| State machine | Diagram berantai (keliru) | Tabel transisi dengan pelaku, guard, efek |
| Ledger | "Append-only" tetapi ada UPDATE rekonsiliasi | Benar-benar append-only; rekonsiliasi tabel terpisah |
| Sumber laporan | Reservasi + laporan manual | Hanya ledger |
| Struktur | Halaman & handler per file di root | `public/` + controller/service/repository |
| CSS | `admin_style.css` tunggal | Atomic design + `@layer`, ≤ 200 baris/file |
| Logika DB | View/procedure | Semua di PHP; DB hanya constraint |
| Kedaluwarsa | Cron "artisan" | `payment_deadline_at` + lazy expiry + endpoint bertoken |
| Tes | Checklist manual | Otomatis + konkurensi + CI |
| Deploy | Hanya lokal XAMPP | Mode demo + panduan hosting gratis |
