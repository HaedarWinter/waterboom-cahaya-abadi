<?php
declare(strict_types=1);

require_once __DIR__ . '/../tests/guard.php';
require_test_database_guard('SCRIPT: BLOCKED (guard rejected configuration)');

require __DIR__ . '/../app/init.php';

$db = \App\Core\Database::getInstance();
$conn = $db->getConnection();
require_test_database_connection($conn, 'SCRIPT: BLOCKED (guard rejected connection)');

echo "Reset Demo Data — db_ujikom_test\n";
echo str_repeat('─', 50) . "\n\n";

$conn->begin_transaction();

try {
    echo "1. Delete demo data...\n";
    $conn->query("DELETE FROM ledger_entries WHERE source_type IN ('PAYMENT', 'EXPENSE', 'ADJUSTMENT')");
    echo "   - Ledger entries: {$conn->affected_rows}\n";
    
    $conn->query("DELETE FROM daily_closings");
    echo "   - Daily closings: {$conn->affected_rows}\n";
    
    $conn->query("DELETE FROM payments WHERE booking_id IN (1,2,3,4)");
    echo "   - Payments: {$conn->affected_rows}\n";
    
    $conn->query("DELETE FROM booking_items WHERE booking_id IN (1,2,3,4)");
    echo "   - Booking items: {$conn->affected_rows}\n";
    
    echo "\n2. Re-seed demo data...\n";
    $seeder = file_get_contents(__DIR__ . '/../database/seeders/demo_data_seeder.sql');
    $stmts = array_filter(explode(';', $seeder), fn($s) => trim($s) && !str_starts_with(trim($s), '--'));
    
    foreach ($stmts as $stmt) {
        $clean = trim($stmt);
        if (!$clean || str_starts_with($clean, 'START') || str_starts_with($clean, 'COMMIT')) continue;
        $conn->query($clean);
    }
    echo "   - Seeder executed\n";
    
    echo "\n3. Log reset...\n";
    $note = addslashes("Demo data reset via script at " . date('Y-m-d H:i:s'));
    $conn->query("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, changes, ip_address, user_agent) VALUES (1, 'DEMO_RESET', 'SYSTEM', 0, '$note', '127.0.0.1', 'CLI')");
    echo "   - Activity logged\n";
    
    $conn->commit();
    echo "\n✓ Reset complete\n";
    exit(0);
    
} catch (Exception $e) {
    $conn->rollback();
    echo "\n✗ Failed: {$e->getMessage()}\n";
    exit(1);
}
