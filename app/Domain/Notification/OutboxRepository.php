<?php
declare(strict_types=1);

namespace App\Domain\Notification;

class OutboxRepository {
    private $conn;
    private bool $available = true;

    public function __construct($connection) {
        $this->conn = $connection;
    }

    public function enqueue(array $data): int {
        $sql = "INSERT INTO notification_outbox (channel, recipient, template_key, subject, body, scheduled_at, idempotency_key)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('sssssss', 
            $data['channel'], $data['recipient'], $data['template_key'] ?? null,
            $data['subject'] ?? null, $data['body'], $data['scheduled_at'] ?? date('Y-m-d H:i:s'),
            $data['idempotency_key'] ?? null
        );
        $stmt->execute();
        return (int)$this->conn->insert_id;
    }

    public function findPending(int $limit = 100): array {
        $sql = "SELECT * FROM notification_outbox 
                WHERE status = 'PENDING' AND scheduled_at <= NOW() 
                ORDER BY scheduled_at ASC, id ASC LIMIT ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    }

    public function markSent(int $id): bool {
        $sql = "UPDATE notification_outbox SET status = 'SENT', sent_at = NOW(), attempts = attempts + 1 WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    public function markFailed(int $id, string $error): bool {
        $sql = "UPDATE notification_outbox SET status = CASE WHEN attempts + 1 >= max_attempts THEN 'DEAD' ELSE 'FAILED' END, 
                last_error = ?, attempts = attempts + 1 WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('si', $error, $id);
        return $stmt->execute();
    }

    public function retryFailed(): int {
        $sql = "UPDATE notification_outbox SET status = 'PENDING', last_error = NULL WHERE status IN ('FAILED', 'DEAD')";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->affected_rows;
    }

    public function getStats(): array {
        $sql = "SELECT status, COUNT(*) as count FROM notification_outbox GROUP BY status";
        $result = $this->conn->query($sql);
        $stats = ['PENDING' => 0, 'SENT' => 0, 'FAILED' => 0, 'DEAD' => 0];
        if (!$result) {
            $this->available = false;
            return $stats;
        }
        while ($row = $result->fetch_assoc()) {
            $stats[$row['status']] = (int)$row['count'];
        }
        return $stats;
    }

    public function isAvailable(): bool {
        return $this->available;
    }
}
