<?php
declare(strict_types=1);

namespace App\Domain\Notification;

class TemplateRepository {
    private $conn;

    public function __construct($connection) {
        $this->conn = $connection;
    }

    public function findAll(): array {
        $sql = "SELECT * FROM wa_templates ORDER BY is_active DESC, `key`";
        return $this->conn->query($sql)->fetch_all(MYSQLI_ASSOC) ?: [];
    }

    public function findByKey(string $key): ?array {
        $sql = "SELECT * FROM wa_templates WHERE `key` = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('s', $key);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function create(array $data): int {
        $sql = "INSERT INTO wa_templates (`key`, name, subject, body, variables, is_active, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $variables = json_encode($data['variables'] ?? [], JSON_UNESCAPED_UNICODE);
        $stmt->bind_param('sssssii', 
            $data['key'], $data['name'], $data['subject'] ?? null, 
            $data['body'], $variables, $data['is_active'] ?? 1, $data['created_by'] ?? 0
        );
        $stmt->execute();
        return (int)$this->conn->insert_id;
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [];
        $types = '';

        $allowed = ['name', 'subject', 'body', 'variables', 'is_active', 'updated_by'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "`$field` = ?";
                if ($field === 'variables') {
                    $params[] = json_encode($data[$field], JSON_UNESCAPED_UNICODE);
                } else {
                    $params[] = $data[$field];
                }
                $types .= $field === 'is_active' ? 'i' : 's';
            }
        }

        if (empty($fields)) return false;

        $params[] = $id;
        $types .= 'i';

        $sql = "UPDATE wa_templates SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }
}