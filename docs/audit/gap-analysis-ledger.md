# Gap Analysis — Ledger (Buku Kas) Module

**Tanggal:** 22 Oktober 2026  
**Baseline commit:** `1c1e76c` (working tree berisi perubahan Fase 1)  
**Tujuan:** Petakan modul lama *sebelum* pindah ke struktur baru (Fase 2 pilot).

---

## 1. Ringkasan Modul Lama

| File | Peran | Jumlah Baris |
|---|---|---|
| `admin/buku_kas.php` | View + filter + pagination + stat cards | 270 |
| `admin/handlers/buku_kas_handler.php` | Export CSV | 102 |

**Alur request:**  
`GET admin/buku_kas.php` → filter bar (GET, auto-submit on change) → stat cards + tabel + pagination  
`POST admin/handlers/buku_kas_handler.php` (action=export_csv) → download CSV

---

## 2. Query & Logika Aktual (dikutip dari file)

### 2.1 Filter & Validasi Input

```php
// buku_kas.php:14-18
$bulan   = $_GET['bulan'] ?? date('m');
$tahun   = $_GET['tahun'] ?? date('Y');
$kategori = $_GET['kategori'] ?? '';
$direction = $_GET['direction'] ?? '';
$search    = $_GET['q'] ?? '';
```

*Tidak ada validasi tipe/allowlist di sini.*  
Kategori diambil dari array hardcoded di line 87:
```php
$categories = ['TIKET','KODE_UNIK','REFUND','BIAYA_OPERASIONAL','KOREKSI','LAINNYA','SALDO_AWAL','BACKFILL'];
```

### 2.2 WHERE Clause Builder (identik di view & export)

```php
// buku_kas.php:21-41, handler:40-53
$where = "MONTH(le.entry_date) = ? AND YEAR(le.entry_date) = ?";
$params = [(int)$bulan, (int)$tahun];
$types  = "ii";

if ($kategori) { $where .= " AND le.category = ?"; $params[]=$kategori; $types.="s"; }
if ($direction){ $where .= " AND le.direction = ?"; $params[]=$direction; $types.="s"; }
if ($search)   { $where .= " AND (le.entry_no LIKE ? OR le.note LIKE ?)"; $s="%$search%"; $params[]=$s; $params[]=$s; $types.="ss"; }
```

### 2.3 Stat Cards (Summary Ringkasan Kas)

```php
// buku_kas.php:44-59
// Bulan ini
SELECT SUM(CASE WHEN direction='IN' THEN amount ELSE 0 END) as total_in,
       SUM(CASE WHEN direction='OUT' THEN amount ELSE 0 END) as total_out
FROM ledger_entries
WHERE MONTH(entry_date)=? AND YEAR(entry_date)=?
-- bound: $bulan, $tahun

// Akumulatif seluruh waktu
SELECT SUM(CASE WHEN direction='IN' THEN amount ELSE -amount END) as saldo_akhir
FROM ledger_entries
```

Output dipakai di stat cards: `format_harga($total_in)`, `format_harga($total_out)`, `format_harga($total_in-$total_out)`, `format_harga($saldo_akhir_total)`.

### 2.4 Paginated List

```php
// buku_kas.php:62-85
$per_page = 20;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page-1)*$per_page;

$count_stmt = $koneksi->prepare("SELECT COUNT(*) as total FROM ledger_entries le WHERE $where");
$count_stmt->bind_param($types, ...$params);
$total_rows = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $per_page);

$sql = "SELECT le.*, ca.name as account_name, COALESCE(a.username,'SYSTEM') as created_by_name
        FROM ledger_entries le
        LEFT JOIN cash_accounts ca ON le.cash_account_id = ca.id
        LEFT JOIN admin a ON le.created_by = a.id
        WHERE $where
        ORDER BY le.entry_date DESC, le.id DESC
        LIMIT ? OFFSET ?";
$p_list = array_merge($params, [$per_page, $offset]);
$t_list = $types . "ii";
```

### 2.5 Export CSV (handler line 23-98)

- Filter pakai **POST** body (bulan, tahun, kategori, direction).  
- **Tidak** memakai `search` / `q`.  
- **Tidak** memakai pagination (ambil semua).  
- **ORDER BY** `entry_date ASC, le.id ASC` (bedanya: view pakai DESC).  
- Header CSV: `['No. Entri','Tanggal','Akun Kas','Arah','Kategori','Nominal','Sumber','Catatan','Petugas','Posted At']`  
- Body rows: `entry_no`, `entry_date`, `account_name ?? 'Kas Utama'`, `direction`, `category`, `amount (int)`, `source_type + ' #' + source_id`, `note ?? '-'`, `created_by_name`, `posted_at`  
- Footer: blank row, `RINGKASAN`, `Total Arus Masuk`, `Total Arus Keluar`, `Surplus/Defisit`  
- BOM UTF-8: `chr(0xEF).chr(0xBB).chr(0xBF)`

### 2.6 Permission & Security

| Titik | Mekanisme |
|---|---|
| View page | `enforce_active_session($koneksi)` + `has_permission('finance','view')` |
| Export | Sama + `validate_csrf($_POST['csrf_token'])` + `http_response_code(403)` bila gagal |
| Activity log | **Tidak ada** catat ekspor |

### 2.7 Helper & Dependensi

- `format_harga()` — didefinisikan di `config/settings_helper.php` (perlu dicek).  
- `e()` — global escape di `app/init.php`.  
- `csrf_field()` — dari `config/security.php`.  
- `base_url()` — dari `app/init.php`.  
- CSS inline `.badge-in`/`.badge-out` (line 100-101).

---

## 3. Deviasi dari PRD / Aturan Arsitektur

| ID | Temuan | Referensi PRD/AGENTS |
|---|---|---|
| L-01 | Logic SQL & filter builder berada di view (`buku_kas.php`), bukan di Repository/Service. | ARCH-1, ARCH-2 |
| L-02 | Export CSV logic duplikat di handler (query builder + select sama). | ARCH-1 |
| L-03 | `activity_logs` tidak dicatat saat ekspor (wajib AUD-2). | PRD 5.6 AUD-2 |
| L-04 | CSV rentan formula injection (`=...`, `-...`, `@...` di awal cell). | Security baseline |
| L-05 | Pagination manual (limit/offset + link GET), bukan `Support\Pagination`. | PRD 6.2, CSS-6 |
| L-06 | Sort order beda: view `DESC`, export `ASC`. | Konsistensi data |
| L-07 | Filter `search` (q) tidak diikutkan ke export. | Parity ekspektasi user |
| L-08 | `reconciled_at`/`reconciliation_id` masih dibaca di query (L-01) — **LEGACY(R-02)**. | PRD R-02 |
| L-09 | Permission berbasis JSON per user, belum tabel RBAC (`roles`/`permissions`). | PRD 3.3 AUTHZ-1 |
| L-10 | Tidak ada `tests/run.php` atau characterization test. | PRD 9, AGENTS §10 |

---

## 4. Risiko & Catatan Migrasi

1. **Parity CSV**: Ekspor lama tidak punya `search` filter, urutan ASC vs DESC, tanpa formula guard. Harus direplikasi dulu, *lalu* perbaiki (Step 5) di commit terpisah.
2. **Float/Integer**: Semua nominal `amount` adalah `BIGINT` — OK. `format_harga` menerima int.
3. **Bootstrap**: Modul masih butuh `app/init.php` dan `$koneksi` global. Fase 2 pilot **tidak** bikin front controller baru; pakai bootstrap lama.
4. **activity_logs**: Tabel sudah ada (migrasi v3 foundation). Perlu insert saat ekspor.
5. **Orphan data**: `integrity_check.php` menemukan 3 orphan `booking_status_history` & `payments`; tidak berkaitan langsung ledger tapi perlu dibersihkan sebelum tes characterization.
6. **Ukuran file**: `buku_kas.php` 270 baris > batas 200 (AGENTS §5). Harus dipecah ke view + komponen.

---

## 5. Checklist Activity Logs

```sql
SELECT COUNT(*) FROM activity_logs;
-- Harus > 0, cek struktur kolom apakah sudah punya before/after JSON
```

---

## 6. Rencana Langkah Selanjutnya (Sesuai Instruksi)

1. **Step 0 selesai** → laporan ini.  
2. **Step 1**: `tests/run.php` minimal + characterization tests (HTML + CSV snapshot) pada kode LAMA. STOP & report.  
3. **Step 2**: `LedgerRepository`, `LedgerService`, `FilterLedgerRequest` — pindahkan query tanpa mengubah perilaku.  
4. **Step 3**: `Core/Request`, `Core/Response`, `LedgerController`, view di `resources/views/admin/ledger/index.php`, entry `admin/ledger_new.php`.  
5. **Step 4**: Export POST+CSRF+can()+activity_log, streaming CSV, byte-for-byte parity.  
6. **Step 5 (setelah approve)**: formula-injection guard + `Support\Pagination`.  

Semua di atas di-*branch* atau di folder baru (`admin/ledger_new.php`) agar halaman lama **tetap utuh** untuk diff.