<?php
declare(strict_types=1);

namespace App\Domain\Finance;

class ClosingRepository {
    private $conn;

    public function __construct($connection) {
        $this->conn = $connection;
    }

    public function findOrCreate(string $date, int $accountId): array {
        $sql = "SELECT * FROM daily_closings WHERE closing_date = ? AND cash_account_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('si', $date, $accountId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row) return $row;

        // Create new OPEN closing
        $sql = "INSERT INTO daily_closings (closing_date, cash_account_id, status) VALUES (?, ?, 'OPEN')";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('si', $date, $accountId);
        $stmt->execute();
        $id = (int)$this->conn->insert_id;

        return $this->findById($id);
    }

    public function findById(int $id): ?array {
        $sql = "SELECT * FROM daily_closings WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function findByDateRange(string $startDate, string $endDate): array {
        $sql = "SELECT dc.*, ca.name AS account_name FROM daily_closings dc LEFT JOIN cash_accounts ca ON ca.id = dc.cash_account_id WHERE dc.closing_date BETWEEN ? AND ? ORDER BY dc.closing_date DESC, dc.cash_account_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $startDate, $endDate);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    }

    public function findActiveAccounts(): array {
        $stmt = $this->conn->prepare('SELECT id, name FROM cash_accounts WHERE is_active = ? ORDER BY name');
        $active = 1;
        $stmt->bind_param('i', $active);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    }

    public function close(int $id, array $data): bool {
        $sql = "UPDATE daily_closings SET 
                opening_balance = ?, total_in = ?, total_out = ?, 
                closing_balance = ?, physical_cash = ?, difference = ?, 
                difference_note = ?, status = 'CLOSED', closed_by = ?, closed_at = NOW()
                WHERE id = ? AND status = 'OPEN'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('iiiiiiisii', 
            $data['opening_balance'], $data['total_in'], $data['total_out'],
            $data['closing_balance'], $data['physical_cash'], $data['difference'],
            $data['difference_note'], $data['closed_by'], $id);
        return $stmt->execute();
    }

    public function reopen(int $id, int $reopenedBy, string $reason): bool {
        $sql = "UPDATE daily_closings SET status = 'REOPENED', reopened_by = ?, reopened_at = NOW(), reopen_reason = ? WHERE id = ? AND status = 'CLOSED'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('isi', $reopenedBy, $reason, $id);
        return $stmt->execute();
    }
}
