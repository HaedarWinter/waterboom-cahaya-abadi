# Fase 3 Lanjutan — Hasil Verifikasi
**Tanggal:** 2026-10-22

## Selesai

- `scripts/integrity_check.php` tidak lagi memeriksa `ledger_entries.reconciled_at` atau `reconciliation_id`.
- Pemeriksaan rekonsiliasi sekarang mencari baris `ledger_reconciliations` yang menunjuk ledger yang tidak ada.
- `tests/test_reconciliation_integrity_static.php` ditambahkan.
- `tests/test_rbac_static.php` sekarang lulus untuk route scanner, ledger, rekonsiliasi, dan export.
- **Semua admin page, handler, dan API telah diberi canonical guard sesuai PRD §3.2.**
- **Centralized middleware route registry (AUTHZ-3) dibuat: `config/routes_rbac.php`, `app/Http/RouteRegistry.php`, `app/Http/Middleware/RequirePermission.php`, `app/Http/Middleware/Bootstrap.php`. Default-deny untuk route admin tanpa mapping eksplisit.**
- **`admin/logs.php` ditambahkan guard `activity.view`.**

## Belum selesai

- `integrity_check.php` belum dijalankan karena guard dan kebijakan skrip tulis (butuh review kebijakan owner sebelum menjalankan test suite penulis).
- Maker-checker `reconciliation.approve` belum diterapkan (AUTHZ-5): tertunda keputusan owner.
- PII masking per role belum diterapkan (AUTHZ-7): tertunda keputusan owner.
- Migrasi kompatibilitas legacy JSON bridge masih aktif untuk melindungi saat migrasi DB belum selesai.
- Seed V4 roles/permissions ke test DB: butuh keputusan owner.
- UI kelola peran (super_admin): butuh seed V4 dulu.
