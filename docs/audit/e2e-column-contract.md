# E2E Column Contract
**Date:** 2026-10-22  
**Scope:** static source and migration review only. No database connection.

## Dynamic SQL limitation

`admin/reservasi.php:42` and `admin/reservasi.php:49` interpolate `$where` into SQL. Static review cannot prove every runtime filter column. Therefore `reservasi` is **UNVERIFIED**, not fully OK.

The alias/dynamic scan also found ordinary formatting calls and SQL composition. Relevant dynamic SQL:

```text
admin/reservasi.php:42: $count_stmt = $koneksi->prepare("SELECT COUNT(*) as total FROM reservasi WHERE $where");
admin/reservasi.php:49: $sql = "SELECT * FROM reservasi WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
```

## Code-use output

```text
admin/api/notifications.php:10,13 — reservasi: status, id, kode_booking, nama, created_at
admin/audit_keuangan.php:45,76,90,91,105 — reservasi: total_harga, status, created_at, metode_bayar, bukti_bayar, kode_booking, keterangan
admin/reservasi.php:42,49,58,59,60,61 — reservasi: dynamic $where; status, total_harga, created_at
app/Models/Dashboard.php:18,21,22,33,68,69,73 — reservasi: status, total_harga, created_at, jenis_tiket
app/Models/Reservasi.php:11,17,79,107 — reservasi: id, kode_booking, qr_token, nama, no_hp, email, tanggal_kunjungan, jenis_tiket, jumlah_dewasa, jumlah_anak, total_harga, catatan, metode_bayar
app/Services/BookingStateService.php:112,164,333 — reservasi: id, status, qr_token, catatan_tolak, bukti_bayar, is_checkin, waktu_checkin, jumlah_dewasa, jumlah_anak, tanggal_kunjungan, metode_bayar, created_at
app/Models/Reservasi.php:25,49,59,114,122 — kuota_harian: tanggal, kuota_max, terisi
app/Services/BookingStateService.php:240,245,251 — refunds: id, booking_id, payment_id, amount, reason, status, requested_by, approved_by, approved_at
```

## Contract table

| Table | Column | Used by | Defined by | Status |
|---|---|---|---|---|
| reservasi | id | app/Models/Reservasi.php:17; BookingStateService.php:112 | sql/db.sql:66 | UNVERIFIED |
| reservasi | kode_booking | admin/api/notifications.php:13; admin/index.php:28; Reservasi.php:11 | sql/db.sql:67 | UNVERIFIED |
| reservasi | nama | admin/api/notifications.php:13; admin/index.php:28; Reservasi.php:79 | sql/db.sql:68 | UNVERIFIED |
| reservasi | no_hp | Reservasi.php:79 | sql/db.sql:69 | UNVERIFIED |
| reservasi | email | Reservasi.php:79 | sql/db.sql:70 | UNVERIFIED |
| reservasi | tanggal_kunjungan | Reservasi.php:79; BookingStateService.php:364 | sql/db.sql:71 | UNVERIFIED |
| reservasi | jenis_tiket | Dashboard.php:68,69; Reservasi.php:79 | sql/db.sql:72 | UNVERIFIED |
| reservasi | jumlah_dewasa | Reservasi.php:79; BookingStateService.php:359 | sql/db.sql:73 | UNVERIFIED |
| reservasi | jumlah_anak | Reservasi.php:79; BookingStateService.php:359 | sql/db.sql:74 | UNVERIFIED |
| reservasi | total_harga | audit_keuangan.php:45,76,105; Dashboard.php:21,22,33; reservasi.php:61; BookingStateService.php:181,232 | sql/db.sql:75 | UNVERIFIED |
| reservasi | metode_bayar | audit_keuangan.php:90,91; Reservasi.php:79; BookingStateService.php:182 | sql/db.sql:76; sql/migration_v2.sql:9 | UNVERIFIED |
| reservasi | bukti_bayar | audit_keuangan.php:90,91; BookingStateService.php:165; migration_v3_foundation.sql:279,302,309 | sql/db.sql:77; sql/migration_v2.sql:10 | UNVERIFIED |
| reservasi | status | notifications.php:10,13; reservasi.php:58-61; BookingStateService.php:119,164,333 | sql/db.sql:78; sql/migration_v3_state_machine.sql:20-46 | UNVERIFIED |
| reservasi | qr_token | Reservasi.php:107; BookingStateService.php:144-148,165 | sql/db.sql:79; sql/migration_v2.sql:11 | UNVERIFIED |
| reservasi | catatan | Reservasi.php:79 | sql/db.sql:80 | UNVERIFIED |
| reservasi | catatan_tolak | BookingStateService.php:165 | sql/db.sql:81 | UNVERIFIED |
| reservasi | created_at | notifications.php:13; Dashboard.php:21,22,33,73; BookingStateService.php:73,336 | sql/db.sql:82 | UNVERIFIED |
| reservasi | is_checkin | BookingStateService.php:152,155,159 | sql/migration_v3_foundation.sql:61 | UNVERIFIED |
| reservasi | waktu_checkin | BookingStateService.php:153,157,160,166 | sql/migration_v3_foundation.sql:62 | UNVERIFIED |
| kuota_harian | id | no direct code use found | sql/db.sql:87; migration_v2.sql:15 | OK |
| kuota_harian | tanggal | Reservasi.php:25,49,59,114,122; BookingStateService.php:364 | sql/db.sql:88; migration_v2.sql:16 | OK |
| kuota_harian | kuota_max | Reservasi.php:28,59,65 | sql/db.sql:89; migration_v2.sql:17 | OK |
| kuota_harian | terisi | Reservasi.php:29,122; BookingStateService.php:363 | sql/db.sql:90; migration_v2.sql:18 | OK |
| refunds | id | BookingStateService.php:240,241,256 | sql/migration_v3_foundation.sql:193 | OK |
| refunds | booking_id | BookingStateService.php:240,251 | sql/migration_v3_foundation.sql:194 | OK |
| refunds | payment_id | BookingStateService.php:251 | sql/migration_v3_foundation.sql:195 | OK |
| refunds | amount | BookingStateService.php:251 | sql/migration_v3_foundation.sql:196 | OK |
| refunds | reason | BookingStateService.php:251 | sql/migration_v3_foundation.sql:197 | OK |
| refunds | status | BookingStateService.php:240,245,251 | sql/migration_v3_foundation.sql:198-199 | OK |
| refunds | requested_by | BookingStateService.php:251 | sql/migration_v3_foundation.sql:200 | OK |
| refunds | approved_by | BookingStateService.php:245,251 | sql/migration_v3_foundation.sql:201 | OK |
| refunds | approved_at | BookingStateService.php:245,251 | sql/migration_v3_foundation.sql:202 | OK |
| refunds | created_at | no direct code use found | sql/migration_v3_foundation.sql:203 | OK |

## Status summary

| Table | OK | MISSING | DROPPED | UNVERIFIED |
|---|---:|---:|---:|---:|
| reservasi | 0 | 0 | 0 | 18 |
| kuota_harian | 4 | 0 | 0 | 0 |
| refunds | 10 | 0 | 0 | 0 |

No MISSING or DROPPED columns identified in the migration chain. `reservasi` remains UNVERIFIED because dynamic `$where` SQL prevents complete static extraction.
