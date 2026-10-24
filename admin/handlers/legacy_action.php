<?php
declare(strict_types=1);

$handlerMap = [
    'berita' => 'berita_handler.php',
    'buku_kas' => 'buku_kas_handler.php',
    'faq' => 'faq_handler.php',
    'galeri' => 'galeri_handler.php',
    'keuangan' => 'keuangan_handler.php',
    'reconciliation' => 'reconciliation_handler.php',
    'reservasi' => 'reservasi_handler.php',
    'reviews' => 'review_handler.php',
    'settings' => 'settings_handler.php',
];

$handler = $handlerMap[(string)($_GET['handler'] ?? '')] ?? null;
if ($handler === null) {
    http_response_code(404);
    return;
}

// Legacy handlers use paths relative to admin/handlers; keep that context while
// application source itself remains inaccessible over HTTP.
chdir(__DIR__);
require dirname(__DIR__, 2) . '/app/Http/Controllers/Admin/' . $handler;
