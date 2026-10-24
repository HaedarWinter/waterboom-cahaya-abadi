<?php
declare(strict_types=1);

namespace App\Domain\Finance;

class ExpenseRepository {
    private $conn;

    public function __construct($connection) {
        $this->conn = $connection;
    }

    public function create(array $data): int {
        $sql = "INSERT INTO expenses (expense_date, cash_account_id, category, description, amount, receipt_path, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('sississi', $data['expense_date'], $data['cash_account_id'], $data['category'],
            $data['description'], $data['amount'], $data['receipt_path'], $data['status'], $data['created_by']);
        $stmt->execute();
        return (int)$this->conn->insert_id;
    }

    public function findById(int $id): ?array {
        $sql = "SELECT * FROM expenses WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function assertSchemaAvailable(): void {
        $stmt = $this->conn->prepare('SELECT id FROM expenses LIMIT 0');
        if (!$stmt) {
            throw new \RuntimeException('Expenses schema unavailable: ' . $this->conn->error);
        }
        $stmt->close();
    }

    public function findAccountName(int $accountId): string {
        $stmt = $this->conn->prepare('SELECT name FROM cash_accounts WHERE id = ?');
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (string)($row['name'] ?? '-');
    }

    public function findActiveAccounts(): array {
        $stmt = $this->conn->prepare('SELECT id, name FROM cash_accounts WHERE is_active = ? ORDER BY name');
        $active = 1;
        $stmt->bind_param('i', $active);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    }

    public function findByDateRange(string $startDate, string $endDate, int $accountId = 0): array {
        $sql = "SELECT * FROM expenses WHERE expense_date BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
        $types = 'ss';

        if ($accountId > 0) {
            $sql .= " AND cash_account_id = ?";
            $params[] = $accountId;
            $types .= 'i';
        }

        $sql .= " ORDER BY expense_date DESC, id DESC";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Expense listing query prepare failed: ' . $this->conn->error);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    }

    public function approve(int $id, int $approverId): bool {
        $sql = "UPDATE expenses SET status = 'POSTED', approved_by = ?, approved_at = NOW() WHERE id = ? AND status = 'DRAFT'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ii', $approverId, $id);
        return $stmt->execute();
    }

    public function void(int $id, int $voidById, string $reason): bool {
        $sql = "UPDATE expenses SET status = 'VOIDED', voided_by = ?, voided_at = NOW(), void_reason = ? WHERE id = ? AND status = 'POSTED'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('isi', $voidById, $reason, $id);
        return $stmt->execute();
    }
}
