<?php
declare(strict_types=1);

namespace App\Domain\Finance;

use App\Core\Database;
use App\Core\Clock;

class LedgerService {
    private $conn;

    public function __construct($connection = null) {
        $this->conn = $connection ?? Database::getInstance()->getConnection();
    }

    public function createEntry(array $data): int {
        $entryNo = $this->generateEntryNo();
        
        $sql = "INSERT INTO ledger_entries (entry_no, entry_date, cash_account_id, direction, category, amount, source_type, source_id, created_by, note)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ssiisissis', 
            $entryNo, $data['entry_date'], $data['cash_account_id'], $data['direction'],
            $data['category'], $data['amount'], $data['source_type'], $data['source_id'],
            $data['created_by'], $data['note'] ?? '');
        $stmt->execute();
        return (int)$this->conn->insert_id;
    }

    public function createReversal(int $sourceId, int $createdBy, string $reason): void {
        $sql = "SELECT * FROM ledger_entries WHERE source_type = 'EXPENSE' AND source_id = ? AND direction = 'OUT'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $sourceId);
        $stmt->execute();
        $entry = $stmt->get_result()->fetch_assoc();
        
        if (!$entry) return;

        $this->createEntry([
            'entry_date' => Clock::now()->format('Y-m-d'),
            'cash_account_id' => $entry['cash_account_id'],
            'direction' => 'IN',
            'category' => 'KOREKSI',
            'amount' => $entry['amount'],
            'source_type' => 'ADJUSTMENT',
            'source_id' => $sourceId,
            'created_by' => $createdBy,
            'note' => "Pembatalan: $reason (ref: {$entry['entry_no']})"
        ]);
    }

    public function getDailyBalance(string $date, int $accountId): array {
        $sql = "SELECT 
                    COALESCE(SUM(CASE WHEN direction = 'IN' AND entry_date < ? THEN amount ELSE 0 END), 0) as opening,
                    COALESCE(SUM(CASE WHEN direction = 'IN' AND entry_date = ? THEN amount ELSE 0 END), 0) as in,
                    COALESCE(SUM(CASE WHEN direction = 'OUT' AND entry_date = ? THEN amount ELSE 0 END), 0) as out
                FROM ledger_entries
                WHERE cash_account_id = ? AND is_locked = 0";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('sssi', $date, $date, $date, $accountId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: ['opening' => 0, 'in' => 0, 'out' => 0];
    }

    public function lockEntries(string $date, int $accountId): int {
        $sql = "UPDATE ledger_entries SET is_locked = 1 WHERE entry_date = ? AND cash_account_id = ? AND is_locked = 0";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('si', $date, $accountId);
        $stmt->execute();
        return $stmt->affected_rows;
    }

    public function unlockEntries(string $date, int $accountId): int {
        $sql = "UPDATE ledger_entries SET is_locked = 0 WHERE entry_date = ? AND cash_account_id = ? AND is_locked = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('si', $date, $accountId);
        $stmt->execute();
        return $stmt->affected_rows;
    }

    public function getEntries(string $startDate, string $endDate, int $accountId = 0): array {
        $sql = "SELECT * FROM ledger_entries WHERE entry_date BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
        $types = 'ss';

        if ($accountId > 0) {
            $sql .= " AND cash_account_id = ?";
            $params[] = $accountId;
            $types .= 'i';
        }

        $sql .= " ORDER BY entry_date, id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    }

    public function getBalance(int $accountId, string $startDate, string $endDate): int {
        $sql = "SELECT 
                    COALESCE(SUM(CASE WHEN direction = 'IN' THEN amount ELSE 0 END), 0) as total_in,
                    COALESCE(SUM(CASE WHEN direction = 'OUT' THEN amount ELSE 0 END), 0) as total_out
                FROM ledger_entries
                WHERE cash_account_id = ? AND entry_date BETWEEN ? AND ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('iss', $accountId, $startDate, $endDate);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return ($row['total_in'] ?? 0) - ($row['total_out'] ?? 0);
    }

    private function generateEntryNo(): string {
        $year = date('Y');
        $seq = $this->getNextSequence($year);
        return "LED-$year-" . str_pad($seq, 6, '0', STR_PAD_LEFT);
    }

    private function getNextSequence(int $year): int {
        $sql = "SELECT MAX(CAST(SUBSTRING_INDEX(entry_no, '-', -1) AS UNSIGNED)) as max_seq FROM ledger_entries WHERE entry_no LIKE ?";
        $stmt = $this->conn->prepare($sql);
        $pattern = "LED-$year-%";
        $stmt->bind_param('s', $pattern);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (($row['max_seq'] ?? 0) + 1);
    }
}