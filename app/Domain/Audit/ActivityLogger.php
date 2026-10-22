<?php
declare(strict_types=1);

namespace App\Domain\Audit;

use App\Core\Database;
use App\Core\Clock;

class ActivityLogger {
    private $conn;

    public function __construct($connection = null) {
        $this->conn = $connection ?? Database::getInstance()->getConnection();
    }

    public function log(string $action, string $entity, int $entityId, array $data = [], int $userId = 0): int {
        $sql = "INSERT INTO activity_logs (user_id, action, entity_type, entity_id, changes, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $this->conn->prepare($sql);
        
        $userId = $userId ?: ($_SESSION['user_id'] ?? 0);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';
        $changes = json_encode($data, JSON_UNESCAPED_UNICODE);

        $stmt->bind_param('issiiss', $userId, $action, $entity, $entityId, $changes, $ip, $ua);
        $stmt->execute();
        return (int)$this->conn->insert_id;
    }
}