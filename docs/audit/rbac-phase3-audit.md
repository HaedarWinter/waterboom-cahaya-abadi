# RBAC Phase 3 Route Audit
**Date:** 2026-10-22  
**Status:** Discovery only. No schema, route, or permission behavior changed.

## Approved legacy-role mapping

| Legacy `admin.role` | Approved target role |
|---|---|
| `Super Admin` | `super_admin` |
| `Admin` | `front_office` |
| `Akuntan` | `finance` |
| `Finance` | `finance` |
| `Gate` | `gate` |
| New role | `manager` |
| New role | `content` |

Refund maker-checker and reschedule rules remain deferred by owner decision.

## Current authorization mechanism

`config/security.php:138-150` authorizes from `$_SESSION['permissions']`, a JSON blob loaded at `admin/handlers/cek_login.php:85`. `Super Admin` bypasses all checks at `config/security.php:141-142`.

This differs from PRD AUTHZ-1 through AUTHZ-4: no `roles`, `permissions`, or `role_permissions` tables; no canonical `module.action` permission keys; no route middleware registry; and no default-deny test for routes missing authorization.

## Route findings

| Route or endpoint | Current server guard | PRD target | Finding |
|---|---|---|---|
| `admin/index.php` | `require_admin()` | `dashboard.view` | No dashboard permission check; every active account reaches dashboard. |
| `admin/reservasi.php` | `reservasi.view` | `reservation.view` | Legacy key differs from PRD key. |
| `admin/handlers/reservasi_handler.php` payment verify/reject | `reservasi.manage` | `payment.verify` / `payment.reject` | Wrong permission boundary; cannot enforce PRD separation. |
| `admin/scanner.php` | `reservasi.view` | `checkin.scan` | Gate receives reservation-view permission instead of scan-only access. |
| `admin/handlers/scanner_handler.php` | Session only | `checkin.scan` | Missing server-side permission check. |
| `admin/api/notifications.php` | Session only | role-specific dashboard data | Missing permission check and role-based data filtering. |
| `admin/buku_kas.php` | `finance.view` | `ledger.view` | Legacy key differs from PRD key. |
| `admin/reconciliation.php` | `finance.manage` | `reconciliation.manage` | Legacy key differs from PRD key. |
| `admin/logs.php` / `admin/kelola_admin.php` | Direct `Super Admin` role string | `activity.view`, `user.manage`, `role.manage` | Role string bypasses canonical permission model. |
| content handlers | Mixed `settings.manage`, `gallery.manage`, `content.delete` | `content.*.manage`, `review.moderate`, `guestbook.moderate` | Inconsistent legacy key names. |

## Blocking implementation work

1. Create V4 schema migration plus rollback for `roles`, `permissions`, `role_permissions`, and a safe mapping from `admin` to target role IDs.
2. Add canonical permission registry in `config/permissions.php` and role seed data in `config/roles.php`.
3. Implement a server-side permission checker that resolves the session account's role. Maintain legacy JSON only during the migration bridge.
4. Map every admin page, handler, and API endpoint to one canonical permission key; deny missing mappings by default.
5. Enforce `checkin.scan` in both scanner page and scanner handler.
6. Split payment verification/rejection from reservation management.
7. Add static route coverage plus positive/negative permission matrix tests.
8. Add PII masking rules after canonical access checks exist.

## Required owner approval

This phase requires schema changes and changes to access behavior. Per AGENTS.md §3 and §8, do not create or apply a migration without explicit owner approval.

Suggested approval text:

```text
APPROVE RBAC SCHEMA: create V4 roles, permissions, role_permissions, and admin role mapping; migrate legacy JSON permissions through a compatibility bridge; enforce PRD §3.2 permission keys; defer refund maker-checker and reschedule.
```
