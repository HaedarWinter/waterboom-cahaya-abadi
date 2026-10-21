# PRD: Audit & Hardening Codebase
**Keamanan, Alur Sistematis, Anti-Race Condition, dan REST API yang Rapi**

| | |
|---|---|
| **Versi** | 1.0 |
| **Status** | Draft |
| **Owner** | {{haedar}} |
| **Tanggal** | 21 Oktober 2026 |
| **Cakupan** | Bagian codebase yang berisiko (bukan rewrite total) |

<!--
🤖 INSTRUKSI UNTUK AGENT
1. Dokumen ini adalah brief kerja. Ikuti urutan fase di Bagian 8.
2. Jangan mengarang temuan. Setiap temuan harus menunjuk file + baris + bukti.
3. Jangan mengubah perilaku bisnis tanpa persetujuan owner. Kalau ragu, tulis di "Pertanyaan Terbuka".
4. Jangan menjalankan perintah destruktif (drop table, force push, hapus data, rotasi secret produksi) tanpa konfirmasi eksplisit.
5. Jangan pernah mencetak secret / token / data pribadi asli ke laporan, log, atau commit. Tulis `[REDACTED]`.
6. Perbaikan dikerjakan bertahap dengan perubahan kecil dan bisa di-review.
-->

---

## 1. Latar Belakang

Beberapa bagian codebase dibangun cepat dan belum diaudit. Risiko yang dikhawatirkan:

- Data pengguna bocor atau terekspos lewat API, log, atau pesan error.
- Akses tidak sah (login lemah, hak akses tidak dicek, ID bisa ditebak).
- Race condition yang membuat data ganda, saldo/stok salah, atau status tidak konsisten.
- Endpoint REST yang menumpuk, tidak konsisten, dan sulit dirawat.
- Alur kode yang tidak terstruktur sehingga sulit diuji dan mudah menimbulkan bug keamanan baru.

**Dalam bahasa awam:** kita periksa "rumah" ini, cek pintu mana yang tidak terkunci, rapikan ruangan yang berantakan, lalu pasang kunci yang tepat tanpa membongkar seluruh rumah.

## 2. Tujuan (Goals)

| # | Tujuan | Indikator berhasil |
|---|---|---|
| G1 | Menemukan dan menutup celah keamanan berdampak tinggi | 0 temuan Critical/High yang terbuka |
| G2 | Mencegah kebocoran dan eksposur data | Tidak ada secret di repo, respons API tidak membawa field sensitif |
| G3 | Menghilangkan race condition pada operasi kritis | Operasi kritis atomik dan lolos tes konkurensi |
| G4 | Merapikan REST API agar konsisten dan tidak menumpuk | Semua endpoint mengikuti standar di Bagian 6 |
| G5 | Menyusun alur kode yang berlapis dan mudah diuji | Pemisahan layer jelas, cakupan tes area kritis ≥ target |
| G6 | Meninggalkan dokumentasi dan bukti audit | Laporan audit + OpenAPI + changelog perbaikan |

## 3. Non-Goals

- Tidak menulis ulang seluruh aplikasi atau mengganti framework/stack.
- Tidak menambah fitur bisnis baru.
- Tidak mengubah desain UI.
- Tidak melakukan penetration test ke sistem pihak ketiga atau produksi tanpa izin tertulis.

## 4. Pengguna & Pemangku Kepentingan

| Peran | Kebutuhan |
|---|---|
| **Owner / developer** | Tahu apa yang berisiko, apa yang sudah diperbaiki, dan kenapa |
| **Pengguna akhir** | Data aman, layanan stabil, tidak ada hal aneh pada akunnya |
| **Reviewer / rekan tim** | Perubahan kecil, jelas, dan mudah di-review |
| **Calon klien / recruiter** | Bukti bahwa projek dibangun dengan standar profesional |

## 5. Lingkup (Scope)

### 5.1 Prioritas audit (urutkan dari risiko tertinggi)

1. Autentikasi, sesi, token, reset password
2. Otorisasi (siapa boleh akses apa)
3. Endpoint yang menerima input pengguna (form, upload, query, webhook)
4. Operasi keuangan, stok, kuota, voucher, antrean, status (rawan race condition)
5. Akses database dan query
6. Konfigurasi, secret, environment, CORS, header, deployment
7. Logging, error handling, monitoring
8. Dependensi dan supply chain
9. Struktur kode dan organisasi API

### 5.2 Di luar lingkup (kecuali diminta)
Infrastruktur cloud mendalam, audit fisik, social engineering.

---

## 6. Kebutuhan Fungsional (Requirements)

> Tiap item punya ID agar mudah dirujuk di laporan dan commit. Prioritas: **P0** wajib, **P1** penting, **P2** sebaiknya.

### 6.1 Autentikasi & Sesi

| ID | Kebutuhan | Prioritas |
|---|---|---|
| AUTH-1 | Password di-hash dengan algoritma modern (argon2id atau bcrypt cost memadai), tidak pernah disimpan plain | P0 |
| AUTH-2 | Rate limit & lockout bertahap pada login, register, reset password, OTP | P0 |
| AUTH-3 | Token/JWT: secret kuat, masa berlaku pendek, algoritma dipaksa (tolak `none`), refresh token dirotasi dan bisa dicabut | P0 |
| AUTH-4 | Cookie sesi: `HttpOnly`, `Secure`, `SameSite`; sesi di-invalidate saat logout & ganti password | P0 |
| AUTH-5 | Pesan error login tidak membocorkan apakah email terdaftar (anti user enumeration) | P1 |
| AUTH-6 | Token reset/verifikasi sekali pakai, acak kuat, kedaluwarsa singkat | P0 |
| AUTH-7 | Opsi MFA untuk akun admin | P2 |

### 6.2 Otorisasi & Akses Data

| ID | Kebutuhan | Prioritas |
|---|---|---|
| AUTHZ-1 | Setiap endpoint punya aturan akses eksplisit (default **tolak**) | P0 |
| AUTHZ-2 | Cegah **IDOR/BOLA**: akses resource selalu dicek kepemilikan/izin di server, bukan hanya lewat ID | P0 |
| AUTHZ-3 | Cegah privilege escalation & **mass assignment** (user tidak bisa mengisi `role`, `isAdmin`, `balance`, dll. lewat body) | P0 |
| AUTHZ-4 | Pengecekan izin di layer service/policy terpusat, bukan tersebar di tiap handler | P1 |
| AUTHZ-5 | Row-level / tenant isolation bila sistem multi-tenant | P0 (jika berlaku) |

### 6.3 Validasi Input & Serangan Umum

| ID | Kebutuhan | Prioritas |
|---|---|---|
| INP-1 | Semua input divalidasi dengan skema (tipe, panjang, format, enum) di batas masuk API | P0 |
| INP-2 | Query database memakai parameterized query/ORM; tidak ada string concatenation | P0 |
| INP-3 | Perlindungan XSS: output di-escape, CSP dipasang, tidak ada `dangerouslySetInnerHTML`/`innerHTML` dari input mentah | P0 |
| INP-4 | Perlindungan CSRF untuk sesi berbasis cookie | P0 |
| INP-5 | Perlindungan SSRF pada fitur yang mengambil URL eksternal (allowlist host, blok IP internal) | P1 |
| INP-6 | Upload file: batasi ukuran, validasi tipe via isi file (bukan hanya ekstensi), nama file di-generate ulang, simpan di luar web root | P0 |
| INP-7 | Cegah path traversal, command injection, deserialisasi tidak aman, open redirect | P0 |
| INP-8 | Batas ukuran body, kedalaman JSON, dan jumlah item pada list/bulk request | P1 |

### 6.4 Perlindungan Data & Anti-Eksposur

| ID | Kebutuhan | Prioritas |
|---|---|---|
| DATA-1 | Tidak ada secret/API key/credential di kode, riwayat git, atau frontend bundle; gunakan env/secret manager | P0 |
| DATA-2 | `.env` masuk `.gitignore`; tersedia `.env.example` tanpa nilai asli | P0 |
| DATA-3 | Respons API memakai **DTO/serializer** dengan allowlist field; tidak mengembalikan objek DB mentah (`passwordHash`, token, field internal) | P0 |
| DATA-4 | Data sensitif/PII dienkripsi at-rest bila perlu dan selalu in-transit (HTTPS/TLS, HSTS) | P0 |
| DATA-5 | Log tidak memuat password, token, nomor kartu, atau PII; ada masking | P0 |
| DATA-6 | Pesan error ke klien generik; stack trace & detail internal hanya di log server | P0 |
| DATA-7 | Debug mode, endpoint debug, source map, dan dokumentasi API internal dimatikan/dibatasi di produksi | P0 |
| DATA-8 | Prinsip **data minimization**: hanya kumpulkan, simpan, dan kembalikan data yang dibutuhkan; ada kebijakan retensi/penghapusan | P1 |
| DATA-9 | Backup terenkripsi dan pernah diuji restore | P1 |
| DATA-10 | Database & storage tidak terbuka ke publik; akun DB memakai hak minimum | P0 |

### 6.5 Race Condition & Konsistensi Data

| ID | Kebutuhan | Prioritas |
|---|---|---|
| RACE-1 | Identifikasi semua pola **check-then-act** (baca → cek → tulis) pada operasi kritis: saldo, stok, kuota, kupon, booking, poin, status | P0 |
| RACE-2 | Operasi kritis dibungkus **transaksi** dengan isolation level yang sesuai, atau memakai update atomik (`UPDATE ... WHERE stock > 0`) | P0 |
| RACE-3 | Gunakan **locking** yang tepat: row lock (`SELECT ... FOR UPDATE`), optimistic locking (kolom `version`), atau distributed lock bila lintas instance | P0 |
| RACE-4 | **Idempotency key** untuk endpoint yang tidak boleh dobel (pembayaran, order, webhook); retry aman | P0 |
| RACE-5 | Unique constraint & foreign key di level database sebagai pengaman terakhir (jangan hanya andalkan cek di aplikasi) | P0 |
| RACE-6 | Webhook: verifikasi signature, tolak replay, proses idempoten | P0 |
| RACE-7 | Job/queue/cron: cegah eksekusi ganda, tangani retry & dead-letter | P1 |
| RACE-8 | Hindari state global yang dimodifikasi lintas request (variabel module-level, cache tanpa invalidasi) | P1 |
| RACE-9 | Tes konkurensi otomatis (mis. 50 request paralel ke endpoint yang sama) membuktikan hasil tetap benar | P0 |

### 6.6 Standar REST API (Rapi & Tidak Menumpuk)

| ID | Kebutuhan | Prioritas |
|---|---|---|
| API-1 | Penamaan resource berupa **kata benda jamak**, bukan kata kerja: `GET /users/{id}`, bukan `/getUser` | P1 |
| API-2 | Metode HTTP sesuai fungsi: GET (baca), POST (buat), PUT/PATCH (ubah), DELETE (hapus); GET tidak boleh mengubah data | P0 |
| API-3 | **Versioning**: `/api/v1/...` | P1 |
| API-4 | Tidak ada endpoint duplikat/mati; setiap endpoint dipetakan, digabung bila fungsinya sama, dan yang tidak dipakai dihapus lewat proses deprecation | P1 |
| API-5 | Format respons & error **seragam** (mis. `{ data, meta }` dan `{ error: { code, message, details } }`) dengan status code yang benar (200/201/204/400/401/403/404/409/422/429/500) | P0 |
| API-6 | List endpoint wajib **pagination** (cursor/limit), filter, dan sort dengan batas maksimum | P0 |
| API-7 | Rate limiting per IP/user/endpoint; header `Retry-After` pada 429 | P0 |
| API-8 | Dokumentasi **OpenAPI/Swagger** sinkron dengan kode; contoh request/respons | P1 |
| API-9 | CORS memakai allowlist origin eksplisit (bukan `*` untuk API ber-kredensial) | P0 |
| API-10 | Security headers: `Content-Security-Policy`, `X-Content-Type-Options`, `Referrer-Policy`, `Strict-Transport-Security`, dll. | P1 |
| API-11 | Endpoint health check terpisah, tidak membocorkan detail internal | P2 |
| API-12 | Hindari **N+1 query** dan over-fetching; respons ringan | P1 |

### 6.7 Struktur Kode & Alur Sistematis

| ID | Kebutuhan | Prioritas |
|---|---|---|
| ARCH-1 | Pisahkan layer: **route/controller → service (logika bisnis) → repository/data access**; handler tidak berisi query/logika berat | P1 |
| ARCH-2 | Satu tempat untuk validasi, otorisasi, dan error handling (middleware/policy terpusat) | P1 |
| ARCH-3 | Konfigurasi terpusat & divalidasi saat startup (aplikasi gagal start bila env wajib kosong) | P1 |
| ARCH-4 | Hapus kode mati, duplikasi, dan TODO berbahaya; konsistensi penamaan dan struktur folder | P2 |
| ARCH-5 | Tipe data kuat (TypeScript strict / type hints) pada batas modul | P2 |
| ARCH-6 | Dependency injection atau pola yang membuat modul mudah di-mock untuk tes | P2 |

### 6.8 Dependensi, Pipeline, & Observability

| ID | Kebutuhan | Prioritas |
|---|---|---|
| OPS-1 | Scan dependensi (`npm audit` / `pip-audit` / Dependabot / OSV) dan perbaiki yang Critical/High; lockfile di-commit | P0 |
| OPS-2 | **Secret scanning** pada repo dan riwayat git (mis. gitleaks/trufflehog) | P0 |
| OPS-3 | SAST (mis. Semgrep/CodeQL) dan linter keamanan di CI | P1 |
| OPS-4 | CI menjalankan tes + scan sebelum merge; branch utama diproteksi | P1 |
| OPS-5 | Audit log untuk aksi sensitif (login, ubah izin, hapus data, ekspor) | P1 |
| OPS-6 | Monitoring & alert untuk lonjakan error, 401/403/429, dan pola anomali | P2 |
| OPS-7 | Container/deploy: image minimal, non-root, tanpa secret di image | P1 |

---

## 7. Kebutuhan Non-Fungsional

- **Kompatibilitas:** perilaku fitur yang ada tidak berubah; perubahan kontrak API melalui versioning/deprecation.
- **Performa:** perbaikan tidak boleh menurunkan latensi p95 lebih dari {{10%}} pada endpoint utama.
- **Keterbacaan:** perubahan kecil per PR (idealnya < {{400}} baris), satu tema per PR.
- **Keterlacakan:** setiap perbaikan merujuk ke ID temuan (mis. `fix(AUTHZ-2): ...`).
- **Keamanan proses:** tidak ada data asli pengguna di laporan, tes, atau commit.

---

## 8. Rencana Kerja (Fase)

### Fase 0: Persiapan & Pemetaan
- Buat branch `audit/hardening` dan pastikan ada backup/snapshot.
- Petakan: stack, entry point, daftar **semua endpoint**, model data, integrasi pihak ketiga, alur data sensitif.
- Hasilkan **inventaris endpoint** (method, path, auth, input, output, dipakai/tidak).
- Jalankan tes yang ada untuk mendapat baseline.

**Output:** `docs/audit/00-inventory.md`

### Fase 1: Audit (hanya membaca, belum mengubah)
- Review per kategori di Bagian 6, dimulai dari prioritas tertinggi (Bagian 5.1).
- Jalankan tool otomatis: secret scan, dependency scan, SAST.
- Telaah manual untuk logika bisnis, otorisasi, dan race condition (tool otomatis sering melewatkan ini).
- Catat setiap temuan memakai format di Bagian 9.

**Output:** `docs/audit/01-findings.md` (urut berdasarkan severity)

### Fase 2: Triase & Rencana Perbaikan
- Beri severity dan estimasi usaha.
- Kelompokkan jadi paket perbaikan; tandai yang berisiko mengubah kontrak API.
- Owner menyetujui urutan & hal yang butuh keputusan.

**Output:** `docs/audit/02-remediation-plan.md`

### Fase 3: Perbaikan Bertahap
Urutan eksekusi:
1. **Darurat:** secret bocor (rotasi), celah Critical, data terekspos.
2. **Akses:** auth, otorisasi, IDOR, mass assignment.
3. **Konsistensi:** race condition, transaksi, idempotency, constraint DB.
4. **Input & output:** validasi, DTO, error handling, upload.
5. **API:** rapikan endpoint, versioning, pagination, rate limit, OpenAPI.
6. **Struktur:** refactor layer, hapus kode mati.
7. **Pipeline:** CI scan, lint, monitoring.

Aturan tiap perbaikan: **tulis tes yang gagal dulu → perbaiki → tes lolos → commit kecil.**

### Fase 4: Verifikasi
- Jalankan ulang seluruh scan dan tes (termasuk tes konkurensi).
- Uji negatif: coba akses tanpa token, token orang lain, ID milik user lain, payload berlebih, request paralel.
- Pastikan tidak ada regresi fungsional.

**Output:** `docs/audit/03-verification.md`

### Fase 5: Dokumentasi & Serah Terima
- Update `README.md`, `PROJECT_STORY.md`, OpenAPI, `SECURITY.md`, dan `CHANGELOG`.
- Ringkasan untuk non-teknis (apa yang diamankan, dengan bahasa awam).
- Daftar risiko sisa (residual risk) dan rekomendasi lanjutan.

---

## 9. Format Laporan Temuan

Tiap temuan memakai format berikut:

```md
### [SEV-ID] Judul singkat
- **Severity:** Critical | High | Medium | Low | Info
- **Kategori:** AUTHZ / RACE / DATA / INP / API / ARCH / OPS
- **Lokasi:** `path/ke/file.ts:123`
- **Deskripsi:** Apa yang salah (bahasa jelas).
- **Dampak:** Apa yang bisa terjadi, siapa yang terdampak.
- **Cara reproduksi / bukti:** Langkah atau potongan kode (tanpa data asli).
- **Rekomendasi perbaikan:** Langkah konkret.
- **Estimasi usaha:** S / M / L
- **Status:** Open | In progress | Fixed | Won't fix (alasan)
- **Rujukan:** OWASP / CWE terkait
```

### Skala severity

| Level | Arti | Target penanganan |
|---|---|---|
| **Critical** | Bisa dieksploitasi mudah, dampak data/akun besar | Segera (hitungan jam–hari) |
| **High** | Eksploitasi mungkin, dampak signifikan | ≤ 1 minggu |
| **Medium** | Butuh syarat tertentu, dampak sedang | ≤ 1 bulan |
| **Low** | Dampak kecil / hardening | Dijadwalkan |
| **Info** | Saran praktik terbaik | Opsional |

---

## 10. Contoh Pola Perbaikan (Referensi untuk Agent)

**Race condition: stok/saldo**
```sql
-- ❌ Rawan: dua request bisa lolos bersamaan
-- SELECT stock → cek di aplikasi → UPDATE stock = stock - 1

-- ✅ Atomik
UPDATE products
SET stock = stock - 1
WHERE id = $1 AND stock > 0;
-- jika affected_rows = 0 → stok habis, kembalikan 409
```

**IDOR**
```
❌ GET /orders/123  → ambil order 123 begitu saja
✅ GET /orders/123  → WHERE id = 123 AND user_id = <user dari token>
```

**Respons API aman**
```
❌ return user            // ikut membawa passwordHash, token, dll.
✅ return toUserDTO(user) // allowlist: id, name, avatarUrl
```

**Idempotency**
```
POST /payments
Idempotency-Key: 7f3c...   → request ulang dengan key sama mengembalikan hasil yang sama, tidak membuat transaksi baru
```

---

## 11. Kriteria Penerimaan (Definition of Done)

- [ ] Inventaris endpoint lengkap dan tidak ada endpoint tak dikenal
- [ ] 0 temuan **Critical** dan **High** berstatus Open
- [ ] Tidak ada secret di kode maupun riwayat git (atau sudah dirotasi)
- [ ] Semua endpoint punya aturan otorisasi eksplisit dan tes akses negatif
- [ ] Semua operasi kritis atomik dan lolos tes konkurensi
- [ ] Respons API memakai DTO; tidak ada field sensitif yang bocor
- [ ] Semua endpoint patuh standar REST di 6.6, terdokumentasi di OpenAPI
- [ ] Scan dependensi, secret, dan SAST bersih / risiko tersisa terdokumentasi
- [ ] Tes otomatis lolos dan cakupan area kritis ≥ {{70%}}
- [ ] Dokumentasi diperbarui, laporan audit tersedia di `docs/audit/`

## 12. Metrik Keberhasilan

| Metrik | Sebelum | Target |
|---|---|---|
| Temuan Critical/High terbuka | {{...}} | 0 |
| Endpoint tanpa otorisasi eksplisit | {{...}} | 0 |
| Endpoint duplikat / tak terpakai | {{...}} | 0 |
| Dependensi dengan kerentanan High+ | {{...}} | 0 |
| Cakupan tes area kritis | {{...}} | ≥ 70% |
| Endpoint list tanpa pagination | {{...}} | 0 |

## 13. Risiko & Mitigasi

| Risiko | Mitigasi |
|---|---|
| Perbaikan merusak fitur yang ada | Tes regresi dulu, perubahan kecil, branch terpisah |
| Perubahan kontrak API memutus klien (frontend/mobile) | Versioning, masa deprecation, komunikasi ke konsumen API |
| Rotasi secret mengganggu layanan | Jadwalkan, siapkan rollback, rotasi bertahap |
| Refactor melebar jadi rewrite | Patuhi Non-Goals; tolak scope creep, catat sebagai backlog |
| Agent salah menyimpulkan | Wajib bukti file+baris; temuan ambigu masuk Pertanyaan Terbuka |
| Data asli bocor lewat laporan/tes | Gunakan data dummy & `[REDACTED]` |

## 14. Asumsi & Dependensi

- Owner punya akses ke repo, environment dev/staging, dan database non-produksi.
- Tersedia data dummy atau seeding untuk pengujian.
- Perubahan pada produksi dilakukan setelah lolos staging dan disetujui owner.

## 15. Pertanyaan Terbuka

1. Stack & framework yang dipakai? {{...}}
2. Apakah sistem multi-tenant atau ada data pribadi yang diatur regulasi (mis. UU PDP)? {{...}}
3. Ada konsumen API selain frontend sendiri (mobile app, pihak ketiga)? {{...}}
4. Ada pembayaran/transaksi uang? {{...}}
5. Boleh membuat migrasi database (constraint, index)? {{...}}
6. Di mana aplikasi berjalan (VPS, serverless, container)? {{...}}

## 16. Lampiran: Referensi

- OWASP Top 10 dan **OWASP API Security Top 10** (BOLA, Broken Authentication, Excessive Data Exposure, dll.)
- OWASP ASVS dan Cheat Sheet Series
- CWE Top 25
- Panduan REST API: konsistensi resource, status code, pagination, versioning
