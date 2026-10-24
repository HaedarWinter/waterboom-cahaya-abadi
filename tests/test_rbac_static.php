<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$routes = require $root . '/config/routes_rbac.php';
$failures = [];
$routeGuardFiles = [
    'admin/index.php' => 'admin/index.php',
    'admin/api/notifications.php' => 'admin/api/notifications.php',
    'admin/scanner.php' => 'admin/scanner.php',
    'admin/buku_kas.php' => 'admin/buku_kas.php',
    'admin/reconciliation.php' => 'admin/reconciliation.php',
    'admin/berita.php' => 'admin/berita.php',
    'admin/galeri.php' => 'admin/galeri.php',
    'admin/faq.php' => 'admin/faq.php',
    'admin/reviews.php' => 'admin/reviews.php',
    'admin/reservasi.php' => 'admin/reservasi.php',
    'admin/pengaturan.php' => 'admin/pengaturan.php',
    'admin/kelola_admin.php' => 'admin/kelola_admin.php',
    'admin/edit.php' => 'admin/edit.php',
    'admin/audit_keuangan.php' => 'admin/audit_keuangan.php',
    'admin/expenses.php' => 'app/Http/Controllers/Admin/ExpenseController.php',
    'admin/closings.php' => 'app/Http/Controllers/Admin/ClosingController.php',
    'admin/reports.php' => 'app/Http/Controllers/Admin/ReportController.php',
    'admin/notifications.php' => 'app/Http/Controllers/Admin/NotificationController.php',
    'admin/templates.php' => 'app/Http/Controllers/Admin/TemplateController.php',
    'admin/logs.php' => 'admin/logs.php',
];

foreach ($routes as $route => $permission) {
    if ($permission === '@authenticated') continue;
    $guardPath = $routeGuardFiles[$route] ?? $route;
    $source = @file_get_contents($root . '/' . $guardPath);
    if ($source === false || strpos($source, "has_canonical_permission('$permission')") === false) {
        $failures[] = "$route has no matching $permission guard in $guardPath";
    }
}

$controllerGuards = [
    'app/Http/Controllers/Admin/reservasi_handler.php' => [
        'payment.verify', 'payment.reject', 'reservation.cancel', 'refund.request', 'refund.approve',
    ],
    'app/Http/Controllers/Admin/keuangan_handler.php' => [
        'ledger.entry_manual', 'report.finance.export',
    ],
    'app/Http/Controllers/Admin/scanner_handler.php' => ['checkin.scan'],
    'app/Http/Controllers/Admin/reconciliation_handler.php' => ['reconciliation.manage'],
    'app/Http/Controllers/Admin/export.php' => ['guestbook.moderate'],
    'app/Http/Controllers/Admin/berita_handler.php' => ['content.news.manage'],
    'app/Http/Controllers/Admin/galeri_handler.php' => ['content.gallery.manage'],
    'app/Http/Controllers/Admin/faq_handler.php' => ['content.faq.manage'],
    'app/Http/Controllers/Admin/review_handler.php' => ['review.moderate'],
    'app/Http/Controllers/Admin/hapus.php' => ['guestbook.moderate'],
    'app/Http/Controllers/Admin/settings_handler.php' => ['settings.manage'],
    'app/Http/Controllers/Admin/buku_kas_handler.php' => ['ledger.view'],
];

foreach ($controllerGuards as $path => $permissions) {
    $source = @file_get_contents($root . '/' . $path);
    foreach ($permissions as $permission) {
        if ($source === false || strpos($source, "has_canonical_permission('$permission')") === false) {
            $failures[] = "$path missing $permission guard";
        }
    }
}

$bootstrap = file_get_contents($root . '/app/Http/Middleware/Bootstrap.php');
if ($bootstrap === false || strpos($bootstrap, '$required === null') === false) {
    $failures[] = 'Unregistered admin PHP routes must be denied by default';
}

if ($failures !== []) {
    foreach ($failures as $failure) echo "FAIL $failure\n";
    exit(1);
}

echo 'PASS canonical RBAC guards match current route structure' . PHP_EOL;

