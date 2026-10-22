<?php
declare(strict_types=1);

namespace App\Domain\Finance;

use App\Core\Database;
use App\Core\Clock;

class ClosingService {
    private ClosingRepository $repo;
    private LedgerService $ledger;
    private ActivityLogger $audit;

    public function __construct(
        ClosingRepository $repo,
        LedgerService $ledger,
        ActivityLogger $audit
    ) {
        $this->repo = $repo;
        $this->ledger = $ledger;
        $this->audit = $audit;
    }

    public function performClosing(string $date, int $accountId, int $closedBy, int $physicalCash, string $diffNote = ''): array {
        // Compute balances from ledger
        $ledger = $this->ledger->getDailyBalance($date, $accountId);

        $closing = $this->repo->findOrCreate($date, $accountId);
        if ($closing['status'] === 'CLOSED') {
            throw new \DomainException('Period already closed');
        }

        $systemBalance = $ledger['opening'] + $ledger['in'] - $ledger['out'];
        $difference = $physicalCash - $systemBalance;

        Database::getInstance()->begin();
        try {
            $this->repo->close($closing['id'], [
                'opening_balance' => $ledger['opening'],
                'total_in' => $ledger['in'],
                'total_out' => $ledger['out'],
                'closing_balance' => $systemBalance,
                'physical_cash' => $physicalCash,
                'difference' => $difference,
                'difference_note' => $diffNote,
                'closed_by' => $closedBy,
            ]);

            // Lock ledger entries for this date/account
            $this->ledger->lockEntries($date, $accountId);

            Database::getInstance()->commit();
            $this->audit->log('closing.perform', 'daily_closing', $closing['id'], [
                'date' => $date, 'account' => $accountId, 'diff' => $difference
            ]);

            return ['success' => true, 'closing' => $this->repo->findById($closing['id'])];
        } catch (\Throwable $e) {
            Database::getInstance()->rollback();
            throw $e;
        }
    }

    public function reopen(int $closingId, int $reopenedBy, string $reason): array {
        $closing = $this->repo->findById($closingId);
        if (!$closing) throw new \DomainException('Closing not found');
        if ($closing['status'] !== 'CLOSED') throw new \DomainException('Only CLOSED can be reopened');

        Database::getInstance()->begin();
        try {
            $this->repo->reopen($closingId, $reopenedBy, $reason);
            $this->ledger->unlockEntries($closing['closing_date'], $closing['cash_account_id']);

            Database::getInstance()->commit();
            $this->audit->log('closing.reopen', 'daily_closing', $closingId, ['reason' => $reason]);

            return ['success' => true, 'closing' => $this->repo->findById($closingId)];
        } catch (\Throwable $e) {
            Database::getInstance()->rollback();
            throw $e;
        }
    }
}