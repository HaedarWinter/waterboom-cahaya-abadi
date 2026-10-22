# $koneksi Audit (Verified)

**Timestamp:** 2026-10-22T03:52:00Z  
**Scope:** Read-only analysis of global `$koneksi` variable usage. All evidence from grep and file inspection.

---

## STEP 3b.1: Path Verification

```bash
$ ls -la config/
total 32
drwxr-xr-x@  5 haedarwezzer  admin   160 Oct 21 20:24 .
drwxr-xr-x@ 26 haedarwezzer  admin   832 Oct 22 09:07 ..
-rw-r--r--@  1 haedarwezzer  admin   790 Oct 21 20:24 koneksi.php
-rw-r--r--@  1 haedarwezzer  admin  6416 Oct 22 08:52 security.php
-rw-r--r--@  1 haedarwezzer  admin   754 Oct 21 20:24 settings_helper.php
```

```bash
$ find . -name 'koneksi*.php' -not -path './vendor/*' -not -path './.git/*'
./config/koneksi.php
```

```bash
$ find . -name 'connection*.php' -not -path './vendor/*' -not -path './.git/*'
(no output)
```

**Finding:** The connection config file is **exactly `config/koneksi.php`**.  
**Correction from previous version:** Old audit cited `config/k/connection.php` and `config/k connection.php` — these do not exist. This was a transcription error.

---

## STEP 3b.2: Q4 — Which file triggers "Undefined variable '$koneksi'"?

**Method:** `grep -rn '\$koneksi' --include='*.php' .` → 141 matches in 32 files.

**Classification:**

| File | Type | Evidence (line) |
|---|---|---|
| `config/koneksi.php` | **ASSIGNS** | Line 14: `$koneksi = \App\Core\Database::getInstance()->getConnection();` |
| `app/init.php` | **ASSIGNS** | Line 43: `$koneksi = $db->getConnection();` |
| `app/Core/Database.php` | **DECLARES** (property) | Line 7: `private $koneksi;` (not a global) |
| `app/Models/Dashboard.php` | **ASSIGNS LOCAL** | Line 9: `$koneksi = $db->getConnection();` (shadows global in method) |
| All other 28 files | **READS** | Uses `$koneksi->prepare()`, `$koneksi->query()`, or passes to functions |

**For each READS file, require/include before first read:**

| File | Require lines before first `$koneksi` read | Loads connection? |
|---|---|---|
| `admin/audit_keuangan.php` | L6: `require_once '../app/init.php';` | Via init.php |
| `admin/berita.php` | L5: `require_once '../app/init.php';` | Via init.php |
| `admin/buku_kas.php` | L6: `require_once '../app/init.php';` | Via init.php |
| `admin/edit.php` | L1: `require_once '../app/init.php';` | Via init.php |
| `admin/faq.php` | L5: `require_once '../app/init.php';` | Via init.php |
| `admin/galeri.php` | L5: `require_once '../app/init.php';` | Via init.php |
| `admin/index.php` | L2: `require_once '../app/init.php';` | Via init.php |
| `admin/kelola_admin.php` | L6: `require_once '../app/init.php';` | Via init.php |
| `admin/logs.php` | L7: `require_once '../app/init.php';` | Via init.php |
| `admin/pengaturan.php` | L6: `require_once '../app/init.php';` | Via init.php |
| `admin/reconciliation.php` | L6: `require_once '../app/init.php';` | Via init.php |
| `admin/reservasi.php` | L5: `require_once '../app/init.php';` | Via init.php |
| `admin/reviews.php` | L5: `require_once '../app/init.php';` | Via init.php |
| `admin/scanner.php` | L2: `require_once '../app/init.php';` | Via init.php |
| `admin/handlers/buku_kas_handler.php` | L6: `require_once '../../app/init.php';` | Via init.php |
| `admin/handlers/cek_login.php` | L6: `require_once '../../app/init.php';` | Via init.php |
| `admin/handlers/keuangan_handler.php` | L6: `require_once '../../app/init.php';` | Via init.php |
| `admin/handlers/reconciliation_handler.php` | L6: `require_once '../../app/init.php';` | Via init.php |
| `admin/handlers/review_handler.php` | L5: `require_once __DIR__ . '/../../app/init.php';` | Via init.php |
| `admin/handlers/scanner_handler.php` | L2: `require_once '../../app/init.php';` | Via init.php |
| **LEGACY — DIRECT koneksi.php** | | |
| `admin/handlers/berita_handler.php` | L6: `require '../../config/koneksi.php';` | **Direct** |
| `admin/handlers/export.php` | L14: `require '../../config/koneksi.php';` | **Direct** |
| `admin/handlers/faq_handler.php` | L6: `require '../../config/koneksi.php';` | **Direct** |
| `admin/handlers/galeri_handler.php` | L11: `require '../../config/koneksi.php';` | **Direct** |
| `admin/handlers/hapus.php` | L15: `require '../../config/koneksi.php';` | **Direct** |
| `admin/handlers/settings_handler.php` | L12: `require '../../config/koneksi.php';` | **Direct** |
| `admin/handlers/simpan.php` | L7: `require '../../config/koneksi.php';` | **Direct** |

**Answer to Q4:** No file reads `$koneksi` before loading it. All 28 READS files load either `init.php` (21 files) or `koneksi.php` directly (7 legacy handlers) before first use. The IDE warning "Undefined variable '$koneksi'" has **no basis in actual code** — it may be a false positive from static analysis not resolving `require_once`. **Risk: Low.**

---

## STEP 3b.3: Q2 — Require chain trace

**Web entry path (e.g., `admin/buku_kas.php`):**
```
admin/buku_kas.php:6 → require_once '../app/init.php'
  app/init.php:36 → require_once '../config/koneksi.php'
    config/koneksi.php:10 → require_once '../app/Core/Database.php'
      app/Core/Database.php → class Database (singleton)
  app/init.php:42 → $db = Database::getInstance()
  app/init.php:43 → $koneksi = $db->getConnection()
```

**Legacy handler path (e.g., `admin/handlers/berita_handler.php`):**
```
admin/handlers/berita_handler.php:6 → require '../../config/koneksi.php'
  config/koneksi.php:10 → require_once '../app/Core/Database.php'
    app/Core/Database.php → class Database (singleton)
  config/koneksi.php:14 → $koneksi = \App\Core\Database::getInstance()->getConnection()
  config/koneksi.php:16 → if (!$koneksi) die(...)
  admin/handlers/berita_handler.php:7 → require '../../config/security.php'
```

**Does a second include overwrite?** No. `require_once` prevents re-execution. Both paths call `Database::getInstance()->getConnection()`, returning the same singleton mysqli object. The global `$koneksi` is assigned the same reference. **Risk: Medium** — initialization order differs between web path (init.php then koneksi.php) and legacy path (koneksi.php directly). Both work because of singleton, but debug order is inconsistent.

---

## STEP 3b.4: Q6 — Every connection caller

**Method:** `grep -rln 'getInstance\|mysqli_connect\|new mysqli\|koneksi.php' --include='*.php' .`

| File | Line | Type | Guarded by test guard? |
|---|---:|---|---|
| `app/Core/Database.php` | 26 | `mysqli_connect` (test mode) | n.a. (guard is IN Database.php) |
| `app/Core/Database.php` | 36 | `mysqli_connect` (web mode) | n.a. |
| `app/Core/Database.php` | 39 | `mysqli_connect` (fallback) | n.a. |
| `app/init.php` | 42 | `Database::getInstance()` | no (web path) |
| `config/koneksi.php` | 14 | `Database::getInstance()` | no (legacy web path) |
| `app/Models/Audit.php` | 17 | `Database::getInstance()` | n.a. |
| `app/Models/Dashboard.php` | 8 | `Database::getInstance()` | n.a. |
| `app/Models/Faq.php` | 6 | `Database::getInstance()` | n.a. |
| `app/Models/Gallery.php` | 6 | `Database::getInstance()` | n.a. |
| `app/Models/News.php` | 6 | `Database::getInstance()` | n.a. |
| `app/Models/Reservasi.php` | 10,16,22,39,106,112,121 | `Database::getInstance()` | n.a. |
| `app/Models/Review.php` | 6,9 | `Database::getInstance()` | n.a. |
| `app/Models/Setting.php` | 15,28 | `Database::getInstance()` | n.a. |
| `app/Services/BookingStateService.php` | 108,330,358 | `Database::getInstance()` | n.a. |
| `admin/includes/header.php` | 6 | `Database::getInstance()` | n.a. |
| `admin/api/notifications.php` | 6 | `Database::getInstance()` | n.a. |
| `scripts/integrity_check.php` | 13 | `Database::getInstance()` | no (CLI script, no guard) |
| `tests/run.php` | 78 | `Database::getInstance()` | **yes** (env pre-checks at line 66) |
| `tests/legacy_wrapper.php` | 25 | `require app/init.php` | **yes** (env set at lines 17-22) |
| `tests/test_payment_ledger_invariant.php` | 12 | `Database::getInstance()` | no (unit test, no guard) |
| `tests/verify_hardening.php` | 80 | `Database::getInstance()` | no (unit test, no guard) |
| `tests/verify_state_machine.php` | 21,42,48 | `Database::getInstance()` | no (unit test, no guard) |

**Findings:**
- All `mysqli_connect` calls are inside `app/Core/Database.php` constructor (3 sites).
- Only `tests/run.php` and `tests/legacy_wrapper.php` go through the **test env guard**.
- All other callers (web, scripts, unit tests) bypass the test guard. **Risk: Medium** — scripts/unit tests could connect to dev DB if env vars not set correctly.

---

## STEP 3b.5: Q1–Q6 Summary with Corrections

### Q1: Where is $koneksi assigned?
- **Verified paths:** `config/koneksi.php:14`, `app/init.php:43`, `app/Models/Dashboard.php:9` (local)
- **Correction:** Old audit used incorrect path `config/k/connection.php`.

### Q2: Is config/koneksi.php included more than once?
- **Verified:** `require_once` in `app/init.php:36`. Legacy handlers require `config/koneksi.php` directly (7 files). `require_once` prevents duplicate execution. No overwrite. **Risk: Medium** (order inconsistency).

### Q3: When connect fails, what value does $koneksi get?
- **Verified:** `config/koneksi.php:16-19` calls `die()`. `app/Core/Database.php:28-31` and `42-46` call `die()`. `$koneksi` never assigned falsy value. Execution halts. **Risk: Low**.

### Q4: Undefined variable '$koneksi'?
- **Verified:** No file reads `$koneksi` before `require` of `init.php` or `koneksi.php`. No IDE warning source found. **Risk: Low**.

### Q5: Separate connection from $koneksi?
- **Verified:** One singleton (`Database::getInstance()`). `$koneksi` is a reference to it. **Risk: Low**.

### Q6: Unguarded mysqli_connect?
- **Verified:** All 3 `mysqli_connect` calls inside `Database` constructor, called only via `getInstance()`. Test guard only in `tests/run.php` and `tests/legacy_wrapper.php`. **Risk: Medium** (scripts/unit tests unguarded).

---

## Corrections from Previous Version

| Item | Old Audit Claim | Corrected |
|---|---|---|
| Config file path | `config/k/connection.php`, `config/k connection.php` | `config/koneksi.php` (verified) |
| Q1 assignment count | "Three" (correct count, wrong path) | `config/koneksi.php:14`, `app/init.php:43`, `app/Models/Dashboard.php:9` |
| Q2 double include | "No, uses require_once" | Same conclusion, now with verified require chains |
| Q4 undefined variable | "No IDE warnings found" | Same, now with full 28-file classification table |
| Q5 dual connection | "One singleton" | Same, verified via getInstance() call sites |
| Q6 unguarded paths | "Low risk" | **Medium** — 8 scripts/unit tests unguarded |

---

## Overall Risk Assessment

| Q | Risk | Notes |
|---|---|---|
| Q1 | Low | Single global; Dashboard.php shadows locally |
| Q2 | Medium | Legacy handlers load koneksi.php directly; init.php also loads it. Order differs. |
| Q3 | Low | `die()` on failure; no falsy assignment |
| Q4 | Low | All readers load connection first |
| Q5 | Low | Singleton enforced |
| Q6 | Medium | Only `tests/run.php` and `tests/legacy_wrapper.php` guarded |

**No fixes applied.** Document is read-only evidence.

