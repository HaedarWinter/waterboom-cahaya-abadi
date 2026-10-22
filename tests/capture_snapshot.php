<?php
/**
 * Capture legacy baseline snapshots for characterization tests.
 * Run with: APP_ENV=test DB_DATABASE=db_ujikom_test DB_SOCKET=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock php tests/capture_snapshot.php
 */
declare(strict_types=1);

ini_set('display_errors', 'stderr');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

$testCases = [
    // HTML tests
    ['name' => 'GET default (no filter)', 'get' => [], 'type' => 'html'],
    ['name' => 'GET filter bulan/tahun only', 'get' => ['bulan' => '01', 'tahun' => '2025'], 'type' => 'html'],
    ['name' => 'GET filter direction=OUT', 'get' => ['direction' => 'OUT', 'bulan' => '01', 'tahun' => '2025'], 'type' => 'html'],
    ['name' => 'GET filter kategori=REFUND', 'get' => ['kategori' => 'REFUND', 'bulan' => '01', 'tahun' => '2025'], 'type' => 'html'],
    ['name' => 'GET search (entry_no)', 'get' => ['q' => 'LED-001', 'bulan' => '01', 'tahun' => '2025'], 'type' => 'html'],
    // CSV tests
    ['name' => 'CSV default (no filter)', 'post' => ['action' => 'export_csv', 'bulan' => '01', 'tahun' => '2025'], 'type' => 'csv'],
    ['name' => 'CSV filter kategori=BIAYA_OPERASIONAL', 'post' => ['action' => 'export_csv', 'kategori' => 'BIAYA_OPERASIONAL', 'bulan' => '01', 'tahun' => '2025'], 'type' => 'csv'],
    ['name' => 'CSV filter direction=OUT', 'post' => ['action' => 'export_csv', 'direction' => 'OUT', 'bulan' => '01', 'tahun' => '2025'], 'type' => 'csv'],
];

$snapshotDir = __DIR__ . '/snapshots';
if (!is_dir($snapshotDir)) {
    mkdir($snapshotDir, 0755, true);
}

foreach ($testCases as $tc) {
    $type = $tc['type'];
    $args = $type === 'html' ? json_encode($tc['get']) : json_encode($tc['post']);
    $cmd = sprintf(
        'APP_ENV=test DB_DATABASE=db_ujikom_test DB_SOCKET=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock php %s/tests/legacy_wrapper.php %s %s 2>/dev/null',
        __DIR__, $type, escapeshellarg($args)
    );
    $output = shell_exec($cmd);

    $safeName = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $tc['name']));
    $file = "$snapshotDir/$safeName.$type";
    file_put_contents($file, $output);
    echo "Saved: $file (" . strlen($output) . " bytes)\n";
}

echo "\nSnapshot capture complete.\n";