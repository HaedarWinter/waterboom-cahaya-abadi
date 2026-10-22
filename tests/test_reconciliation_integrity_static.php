<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../scripts/integrity_check.php');
$failures = [];

if ($source === false) {
    $failures[] = 'cannot read integrity check';
} elseif (str_contains($source, 'reconciled_at') || str_contains($source, 'reconciliation_id')) {
    $failures[] = 'integrity check still reads legacy reconciliation columns';
}

if ($source === false || !str_contains($source, 'ledger_reconciliations')) {
    $failures[] = 'integrity check does not validate reconciliation records';
}

if ($failures !== []) {
    foreach ($failures as $failure) echo "FAIL $failure\n";
    exit(1);
}

echo "PASS reconciliation integrity check contract\n";
