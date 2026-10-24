<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/RequestPath.php';

use App\Core\RequestPath;

$cases = [
    ['/ujikom1/admin/index.php?x=1', '/ujikom1/', '/admin/index.php'],
    ['/ujikom1/admin/', '/ujikom1', '/admin/'],
    ['/admin/index.php', '/', '/admin/index.php'],
    ['/ujikom10/admin/index.php', '/ujikom1', '/ujikom10/admin/index.php'],
];

foreach ($cases as [$request, $base, $expected]) {
    $actual = RequestPath::relativeToBase($request, $base);
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL $request -> $actual (expected $expected)\n");
        exit(1);
    }
}

echo "PASS request path normalization\n";
