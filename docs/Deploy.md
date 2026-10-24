# Deploy Demo Waterboom Cahaya Abadi

Panduan ini menyiapkan demo portfolio di hosting PHP + MySQL gratis. Paket dibuat **read-only** untuk pengunjung: form yang menyimpan data dinonaktifkan, semua perubahan admin ditolak, ekspor dimatikan, dan WhatsApp memakai driver log.

## Status dan target

- Target awal: InfinityFree, mengikuti panduan proyek. Layanan ini menyediakan PHP, MySQL, dan SSL pada paket gratis; cek kembali syarat/fitur di [situs resmi](https://www.infinityfree.com/) saat membuat akun.
- Deploy harus berada di subfolder `ujikom1` pada `htdocs` (URL berakhir `/ujikom1`). Banyak handler lama masih memakai awalan URL ini.
- PHP 8.x dengan ekstensi `mysqli` dan `fileinfo`; MySQL/MariaDB.
- Belum ada akun hosting atau domain demo yang dibuat. Pembuatan akun, database, dan pengisian credential memerlukan pemilik.

## 1. Buat database demo kosong

1. Di panel hosting, buat database MySQL baru dan catat host, nama database, user, serta password.
2. Buka phpMyAdmin database baru.
3. Impor `database/schema/demo.sql`. File ini hanya berisi struktur tabel tanpa baris data; skema sudah mencakup migrasi V4_001 sampai V4_006.
4. Jalankan berurutan:
   - `database/seeders/roles_permissions_seeder.sql`
   - `database/seeders/demo_accounts_seeder.sql`
   - `database/seeders/demo_cash_account_seeder.sql`

Jangan jalankan `demo_data_seeder.sql`: isinya merujuk ID booking lokal dan bukan fixture instalasi kosong.

## 2. Unggah aplikasi

1. Jangan unggah folder repository mentah dari Finder/VS Code: folder `.git` saja berisi lebih dari seribu file internal yang tidak diperlukan server. Jalankan `sh scripts/build_upload_package.sh` dari root proyek untuk membuat `waterboom-upload.zip`. Arsip ini hanya memuat file runtime yang dilacak Git, tidak memuat `.git`, `.env` lokal, dump data, tes, atau dokumentasi.
2. Unggah `waterboom-upload.zip` ke `htdocs/ujikom1/`, lalu ekstrak di sana. Pastikan `.htaccess` dan folder `public/` berada langsung di `htdocs/ujikom1/`, bukan di subfolder tambahan. Jika ukuran arsip melebihi batas uploader, gunakan FTP dan unggah isi proyek tanpa `.git`.
3. Buat `.env` di folder `ujikom1` dari `.env.example`. Isi `APP_URL` dengan URL HTTPS situs yang berakhir `/ujikom1`, lalu masukkan credential database dari panel hosting.
4. Buat direktori yang diperlukan aplikasi: `storage/sessions`, `public/assets/berita`, `public/assets/galeri`, `assets/bukti_bayar`, `assets/bukti_keuangan`, dan `assets/tickets`. Gunakan izin standar hosting, biasanya folder `755`; jangan gunakan `777`.
5. Pastikan `.env` tidak ikut terunggah melalui arsip Git. Buat file itu langsung di File Manager, jangan mengubah `.env.example` menjadi tempat secret.
6. Aktifkan SSL/HTTPS dari panel hosting.

Contoh konfigurasi (gunakan nilai dari panel hosting; jangan salin contoh literal):

```dotenv
APP_ENV=demo
APP_URL=https://nama-situs.example/ujikom1
APP_DEBUG=0
SIGNED_URL_SECRET=<acak minimal 32 karakter>
WA_DRIVER=log
DB_HOST=<host database dari panel>
DB_PORT=3306
DB_DATABASE=<nama database dari panel>
DB_USER=<user database dari panel>
DB_PASSWORD=<password database dari panel>
DB_CHARSET=utf8mb4
```

Buat secret dengan password manager atau `openssl rand -hex 32`. Jangan mengirimkannya lewat chat atau memasukkannya ke Git.

## 3. Akun demo

Seeder menyediakan akun contoh berikut. Password bersama untuk demo adalah `demo123`; akun ini hanya boleh dipakai pada database demo.

| Username | Peran |
|---|---|
| `demo_fo` | Front office |
| `demo_finance` | Keuangan |
| `demo_gate` | Petugas gerbang |
| `demo_mgr` | Manajer |
| `demo_content` | Konten |

Tidak ada akun super admin publik. Sesi admin demo bersifat baca saja; POST admin (selain logout), pengaturan, dan ekspor data ditolak.

## 4. Pemeriksaan setelah unggah

- Buka `https://nama-situs.example/ujikom1/health.php`; respons sehat berbentuk `{"status":"ok"}`.
- Buka `/ujikom1/.env` dan `/ujikom1/app/init.php`; keduanya harus ditolak (403/404), tidak boleh menampilkan isi file.
- Coba buka halaman utama, reservasi, login, dashboard dan modul keuangan.
- Pastikan banner Mode Demo tampil. Reservasi, buku tamu, ulasan, unggah bukti, dan semua perubahan admin tidak boleh menyimpan data.
- Pastikan berkas galeri/aset tidak menghasilkan 404; database baru tidak memiliki foto unggahan lokal, jadi galeri akan menampilkan state kosong sampai aset contoh yang memang boleh dipublikasikan disiapkan.

## 5. Menambahkan tautan portfolio

Setelah uji pasca-deploy berhasil, gunakan URL HTTPS `/ujikom1/` sebagai tombol **Coba Demo** di card portfolio. Tambahkan keterangan “Mode demo baca saja; WhatsApp disimulasikan.” Jangan menanam melalui iframe.

Situs resmi InfinityFree menyediakan PHP, MySQL, dan SSL gratis, tetapi kuota/fiturnya dapat berubah; periksa kembali sebelum membuat akun. Database free hosting hanya diakses dari skrip PHP di hosting atau phpMyAdmin, bukan dari XAMPP lokal ([panduan koneksi resmi komunitas](https://forum.infinityfree.com/t/unable-to-connect-to-mysql-host-sql305-infinityfree-com-or-sql305-epizy-com/101808)).
