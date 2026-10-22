<?php
declare(strict_types=1);

$configRoutes = require dirname(__DIR__) . '/config/routes_rbac.php';
$failures = [];

$reservationHandler = file_get_contents(dirname(__DIR__) . '/admin/handlers/reservasi_handler.php');
$requiredReservationPermissions = [
    'payment.verify',
    'payment.reject',
    'reservation.cancel',
    'refund.request',
    'refund.approve',
];
foreach ($requiredReservationPermissions as $permission) {
    if ($reservationHandler === false || strpos($reservationHandler, "has_canonical_permission('$permission')") === false) {
        $failures[] = "admin/handlers/reservasi_handler.php missing has_canonical_permission('$permission')";
    }
}

$keuanganHandler = file_get_contents(dirname(__DIR__) . '/admin/handlers/keuangan_handler.php');
$requiredKeuanganPermissions = [
    'ledger.entry_manual',
    'report.finance.export',
];
foreach ($requiredKeuanganPermissions as $permission) {
    if ($keuanganHandler === false || strpos($keuanganHandler, "has_canonical_permission('$permission')") === false) {
        $failures[] = "admin/handlers/keuangan_handler.php missing has_canonical_permission('$permission')";
    }
}

$kelolaAdmin = file_get_contents(dirname(__DIR__) . '/admin/kelola_admin.php');
if ($kelolaAdmin === false || strpos($kelolaAdmin, "has_canonical_permission('role.manage')") === false) {
    $failures[] = "admin/kelola_admin.php missing has_canonical_permission('role.manage')";
}

foreach ($configRoutes as $route => $permission) {
    $path = dirname(__DIR__) . '/' . $route;
    $source = file_get_contents($path);
    if ($source === false || strpos($source, "has_canonical_permission('$permission')") === false) {
        $failures[] = "$route missing has_canonical_permission('$permission')";
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) echo "FAIL $failure\n";
    exit(1);
}

echo 'PASS canonical RBAC route guards' . PHP_EOL;

