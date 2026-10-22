# E2E Readiness — 2026-10-22
**Environment:** `db_ujikom_test` on `127.0.0.1:3307`  
**Gates:** G1 complete, G2 complete, G3 complete.  
**Manual walkthrough:** not performed by this record.

## Target guard

```text
TARGET: db=db_ujikom_test port=3307 host=Laptop-Haedar.local
```

## Schema preparation

Compatibility migration applied after two successful scratch runs:

```text
MIGRATION_EXIT=0
```

Required walkthrough tables now present: `reservasi`, `kuota_harian`, `refunds`, `payments`, `payment_proofs`, `ledger_entries`, `booking_status_history`, `cash_accounts`, `login_attempts`.

Migration evidence: `docs/audit/e2e-g2-compatibility.md`.

## Test accounts

Passwords are generated randomly, stored only outside the repository in a mode-600 local file, and are not recorded here.

| Username | Role | Status | Permissions |
|---|---|---|---|
| `test_finance` | Finance | Aktif | `reservasi.view`, `reservasi.manage`, `finance.view` |
| `test_frontoffice` | Admin | Aktif | `reservasi.view`, `reservasi.manage` |
| `test_gate` | Gate | Aktif | `reservasi.view` |

Each generated password hash passed `password_verify()` during seed setup.

## Booking scenarios

| Code | Visit date | Status | Setup path |
|---|---|---|---|
| `TEST-E2E-1` | 2026-11-01 | MENUNGGU_BAYAR | Raw fixture INSERT required to preserve requested code prefix; quota updated in same seed operation. |
| `TEST-E2E-2` | 2026-11-02 | MENUNGGU_VERIFIKASI | Raw fixture INSERT plus dummy proof metadata; state changed through `BookingStateService::submitProof()`. |
| `TEST-E2E-3` | 2026-11-03 | LUNAS | Raw fixture INSERT required to preserve requested code prefix; proof submission and verification executed through `BookingStateService`. |
| `TEST-E2E-4` | 2026-11-04 | DIBATALKAN | Raw fixture INSERT required to preserve requested code prefix; cancellation executed through `BookingStateService`. |

## Invariant evidence

```text
=== VERIFIED_WITHOUT_LEDGER ===
c
0

=== LEDGER_MISMATCH ===
c
(no rows)

=== DUPLICATE_ENTRY_NO ===
c
0

=== LUNAS_WITHOUT_PAYMENT ===
c
0
```

The LUNAS fixture has exactly one `PAYMENT` / `IN` / `TIKET` ledger row:

```text
entry_no | source_id | direction | category | amount
LED-20261022-1000001 | 1000001 | IN | TIKET | 20000
```

Quota results:

```text
2026-11-01 | 200 | 1
2026-11-02 | 200 | 1
2026-11-03 | 200 | 1
2026-11-04 | 200 | 0
```

## Config and web readiness

`.env.test` is absent. No browser GET was sent.

The web runtime may not inherit CLI test environment variables. Sending a web request without explicit test-only configuration could connect to `db_ujikom` through the development fallback. This record therefore does not declare browser walkthrough readiness.

## Verdict

**NOT READY for browser walkthrough.** Database schema, dummy accounts, fixtures, and financial invariants are ready. A local web-server test configuration that forces `APP_ENV=test`, `DB_HOST=127.0.0.1`, `DB_PORT=3307`, and `DB_DATABASE=db_ujikom_test` is still required before any GET request.

## Not covered

- Browser reservation or admin-login GET checks.
- Manual login or role authorization walkthrough.
- Content tables and `laporan_keuangan` data.
- Open incident `incident-ledger-gap-001` remains OPEN and is unaffected by this test setup.
