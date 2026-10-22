# Rekonsiliasi Append-Only
**Status:** Implementasi selesai di test DB; review owner tetap diperlukan sebelum produksi.

## Perubahan

- `database/migrations/V4_002_reconciliation.sql` membuat `ledger_reconciliations` append-only.
- `database/rollbacks/V4_002_reconciliation.sql` hanya untuk rollback database terisolasi.
- `admin/handlers/reconciliation_handler.php` tidak lagi mengubah `ledger_entries`.
- Rekonsiliasi dibuat dengan `INSERT` status `RECONCILED`.
- Pembatalan dibuat dengan `INSERT` status `REVERTED` dan alasan wajib.
- `admin/reconciliation.php` membaca status rekonsiliasi terbaru melalui derived query.
- `tests/test_reconciliation_append_only.php` memeriksa tidak ada `UPDATE ledger_entries` dan migration tersedia.

## Verifikasi

Scratch rehearsal dua kali:

```text
TARGET: db=db_ujikom_test port=3307
=== SCRATCH RUN 1 ===
EXIT=0
=== SCRATCH RUN 2 ===
EXIT=0
```

Migration diterapkan ke test DB setelah target dikonfirmasi:

```text
TARGET: db=db_ujikom_test port=3307
```

Static test:

```text
PASS append-only reconciliation contract
TEST_EXIT=0
```

PHP lint:

```text
No syntax errors detected in admin/reconciliation.php
No syntax errors detected in admin/handlers/reconciliation_handler.php
No syntax errors detected in tests/test_reconciliation_append_only.php
```

## Catatan

- `scripts/integrity_check.php` masih memeriksa kolom legacy `reconciled_at` dan `reconciliation_id`; pemeriksaan itu perlu disesuaikan dalam pekerjaan berikutnya.
- Belum ada maker-checker approval untuk reconciliation; PRD membutuhkan `reconciliation.approve` terpisah.
- Belum menjalankan test suite database-writing sesuai guard policy.
