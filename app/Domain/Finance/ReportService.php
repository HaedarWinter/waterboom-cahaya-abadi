<?php
declare(strict_types=1);

namespace App\Domain\Finance;

class ReportService {
    private $conn;

    public function __construct($connection = null) {
        $this->conn = $connection ?? \App\Core\Database::getInstance()->getConnection();
    }

    public function getCashFlow(string $startDate, string $endDate, int $accountId = 0): array {
        $sql = "SELECT 
                    entry_date,
                    SUM(CASE WHEN direction = 'IN' THEN amount ELSE 0 END) as total_in,
                    SUM(CASE WHEN direction = 'OUT' THEN amount ELSE 0 END) as total_out
                FROM ledger_entries
                WHERE entry_date BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
        $types = 'ss';

        if ($accountId > 0) {
            $sql .= " AND cash_account_id = ?";
            $params[] = $accountId;
            $types .= 'i';
        }

        $sql .= " GROUP BY entry_date ORDER BY entry_date";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    }

    public function getRevenueByTicketType(string $startDate, string $endDate): array {
        $sql = "SELECT 
                    tt.name as ticket_type,
                    SUM(bi.qty) as qty_sold,
                    SUM(bi.subtotal) as revenue
                FROM booking_items bi
                JOIN ticket_types tt ON bi.ticket_type_id = tt.id
                JOIN reservasi r ON bi.booking_id = r.id
                WHERE r.status = 'LUNAS' AND r.visit_date BETWEEN ? AND ?
                GROUP BY tt.id, tt.name
                ORDER BY revenue DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $startDate, $endDate);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    }

    public function getExpensesByCategory(string $startDate, string $endDate): array {
        $sql = "SELECT 
                    category,
                    COUNT(*) as count,
                    SUM(amount) as total
                FROM expenses
                WHERE status = 'POSTED' AND expense_date BETWEEN ? AND ?
                GROUP BY category
                ORDER BY total DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $startDate, $endDate);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    }

    public function getBalanceSummary(int $accountId, string $endDate): array {
        $sql = "SELECT 
                    SUM(CASE WHEN direction = 'IN' THEN amount ELSE 0 END) as total_in,
                    SUM(CASE WHEN direction = 'OUT' THEN amount ELSE 0 END) as total_out
                FROM ledger_entries
                WHERE cash_account_id = ? AND entry_date <= ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('is', $accountId, $endDate);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $balance = ($row['total_in'] ?? 0) - ($row['total_out'] ?? 0);
        return [
            'total_in' => $row['total_in'] ?? 0,
            'total_out' => $row['total_out'] ?? 0,
            'balance' => $balance
        ];
    }
}