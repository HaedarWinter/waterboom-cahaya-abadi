<?php
/**
 * tests/run.php - Minimal test runner for the Ledger module
 * Uses a dedicated test database (db_ujikom_test) on separate MySQL instance.
 * All tests rely solely on PHP built-in extensions.
 *
 * USAGE: APP_ENV=test DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=db_ujikom_test DB_USER=root php tests/run.php
 * EXIT 0 = all tests green
 * EXIT 1 = any failure, with error summary
 * EXIT 2 = configuration refused (not test mode or incomplete settings)
 */
declare(strict_types=1);

require_once __DIR__ . '/guard.php';

$guardOnly = in_array('--guard-only', $argv, true);

require_test_database_guard();
configureTestEnvironment();
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
require __DIR__ . '/../app/init.php';

use App\Core\Database;

$db = Database::getInstance();
$conn = $db->getConnection();
require_test_database_connection($conn);

if ($guardOnly) {
    echo "CHARACTERIZATION: SKIPPED (guard-only)\n";
    exit(0);
}

function configureTestEnvironment(): void {
    putenv('APP_ENV=test');
    putenv('DB_HOST=' . getenv('DB_HOST'));
    putenv('DB_PORT=' . getenv('DB_PORT'));
    putenv('DB_DATABASE=' . getenv('DB_DATABASE'));
    putenv('DB_USER=' . (getenv('DB_USER') ?: 'root'));
    putenv('DB_PASSWORD=' . (getenv('DB_PASSWORD') ?: ''));

    $_SERVER['APP_ENV'] = 'test';
    $_SERVER['DB_HOST'] = getenv('DB_HOST');
    $_SERVER['DB_PORT'] = getenv('DB_PORT');
    $_SERVER['DB_DATABASE'] = getenv('DB_DATABASE');
    $_SERVER['HTTP_HOST'] = 'localhost';

    $_ENV['APP_ENV'] = 'test';
    $_ENV['DB_HOST'] = getenv('DB_HOST');
    $_ENV['DB_PORT'] = getenv('DB_PORT');
    $_ENV['DB_DATABASE'] = getenv('DB_DATABASE');
}

$failures = [];
$passed  = 0;

// Seed test DB from SQL file
$seedPath = __DIR__ . '/seed_test.sql';
if (file_exists($seedPath)) {
    $sql = file_get_contents($seedPath);
    if ($sql) {
        // Run seed; ignore multi_query result for DDL
        $conn->multi_query($sql);
        do {
            if ($res = $conn->store_result()) {
                $res->free();
            }
        } while ($conn->more_results() && $conn->next_result());
    }
}

function checkAssert(bool $condition, string $msg): void {
    global $passed, $failures;
    if ($condition) {
        $passed++;
        echo "PASS  $msg\n";
    } else {
        $failures[] = $msg;
        echo "FAIL  $msg\n";
    }
}

function captureHtml(array $get): string {
    $paramsJson = json_encode($get);
    $cmd = 'APP_ENV=test DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=db_ujikom_test DB_USER=root php ' . __DIR__ . '/legacy_wrapper.php html ' . escapeshellarg($paramsJson);
    $output = shell_exec($cmd);
    return $output ?: '';
}

function captureCsv(array $post): string {
    $paramsJson = json_encode($post);
    $cmd = 'APP_ENV=test DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=db_ujikom_test DB_USER=root php ' . __DIR__ . '/legacy_wrapper.php csv ' . escapeshellarg($paramsJson);
    $output = shell_exec($cmd);
    return $output ?: '';
}

// -------------------------------------------------------------------
// CHARACTERIZATION TESTS
// -------------------------------------------------------------------

$testCases = [
    // ---- GET (HTML) ----
    [
        'name' => 'GET default (no filter)',
        'get'  => [],
        'type' => 'html',
        'expectedContains' => ['<td><strong>LED-001</strong></td>', 'Masuk'],
        'notContains' => ['<td class="td-empty">Tidak ada mutasi buku kas untuk periode ini.</td>'],
    ],
    [
        'name' => 'GET filter bulan/tahun only',
        'get'  => ['bulan' => '01', 'tahun' => '2025'],
        'type' => 'html',
        'expectedContains' => ['<td><strong>LED-002</strong></td>', 'Keluar'],
    ],
    [
        'name' => 'GET filter direction=OUT',
        'get'  => ['direction' => 'OUT'],
        'type' => 'html',
        'expectedContains' => ['badge-out', 'KELUAR'],
    ],
    [
        'name' => 'GET filter kategori=REFUND',
        'get'  => ['kategori' => 'REFUND'],
        'type' => 'html',
        'expectedContains' => ['REFUND'],
    ],
    [
        'name' => 'GET search (entry_no)',
        'get'  => ['q' => 'LED-001'],
        'type' => 'html',
        'expectedContains' => ['LED-001'],
    ],
    // ---- CSV (POST) ----
    [
        'name' => 'CSV default (no filter)',
        'post' => ['action' => 'export_csv', 'bulan' => '01', 'tahun' => '2025'],
        'type' => 'csv',
        'expectedContains' => ['LED-001', 'Masuk', 'Rp 500.000'],
    ],
    [
        'name' => 'CSV filter kategori=BIAYA_OPERASIONAL',
        'post' => ['action' => 'export_csv', 'kategori' => 'BIAYA_OPERASIONAL'],
        'type' => 'csv',
        'expectedContains' => ['BIAYA_OPERASIONAL'],
    ],
    [
        'name' => 'CSV filter direction=OUT',
        'post' => ['action' => 'export_csv', 'direction' => 'OUT'],
        'type' => 'csv',
        'expectedContains' => ['KELUAR'],
    ],
];

foreach ($testCases as $tc) {
    $name = $tc['name'];
    $type = $tc['type'];

    if ($type === 'html') {
        $html = captureHtml($tc['get']);
        $ok = true;
        if (isset($tc['expectedContains'])) {
            foreach ($tc['expectedContains'] as $exp) {
                if (strpos($html, $exp) === false) {
                    $ok = false;
                    $failures[] = "$name: missing '$exp' in HTML";
                    break;
                }
            }
        }
        if (isset($tc['notContains'])) {
            foreach ($tc['notContains'] as $exp) {
                if (strpos($html, $exp) !== false) {
                    $ok = false;
                    $failures[] = "$name: unexpected '$exp' found in HTML";
                    break;
                }
            }
        }
        if ($ok) {
            $passed++;
            echo "PASS  $name\n";
        }
    } else { // csv
        $csv = captureCsv($tc['post']);
        $ok = true;
        if (isset($tc['expectedContains'])) {
            foreach ($tc['expectedContains'] as $exp) {
                if (strpos($csv, $exp) === false) {
                    $ok = false;
                    $failures[] = "$name: missing '$exp' in CSV";
                    break;
                }
            }
        }
        if ($ok) {
            $passed++;
            echo "PASS  $name\n";
        }
    }
}

// -------------------------------------------------------------------
// FINAL REPORT
// -------------------------------------------------------------------
echo PHP_EOL . "\n=== SUMMARY ===\n";
echo "Passed: $passed\n";
if (!empty($failures)) {
    echo "CHARACTERIZATION: FAIL (" . count($failures) . " mismatches)\n";
    echo "Failed:\n";
    foreach ($failures as $f) {
        echo "  - $f\n";
    }
    exit(1);
}

echo "All tests passed.\n";
echo "CHARACTERIZATION: PASS\n";
exit(0);