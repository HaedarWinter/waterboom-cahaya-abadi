<?php
declare(strict_types=1);

$handler = file_get_contents(__DIR__ . '/../admin/handlers/reconciliation_handler.php');
$migrationPath = __DIR__ . '/../database/migrations/V4_002_reconciliation.sql';
$migration = file_exists($migrationPath) ? file_get_contents($migrationPath) : false;

$failures = [];
if ($handler === false || preg_match('/UPDATE\s+ledger_entries/i', $handler)) {
    $failures[] = 'reconciliation handler updates ledger_entries';
}
if ($migration === false || strpos($migration, 'CREATE TABLE IF NOT EXISTS ledger_reconciliations') === false) {
    $failures[] = 'reconciliation migration missing ledger_reconciliations';
}

if ($failures !== []) {
    foreach ($failures as $failure) echo "FAIL $failure\n";
    exit(1);
}

echo "PASS append-only reconciliation contract\n";
