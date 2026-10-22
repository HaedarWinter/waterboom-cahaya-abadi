<?php
declare(strict_types=1);

namespace App\Domain\Finance;

use App\Core\Database;
use App\Core\Clock;

class ExpenseService {
    private ExpenseRepository $repo;
    private LedgerService $ledger;
    private ActivityLogger $audit;

    public function __construct(
        ExpenseRepository $repo,
        LedgerService $ledger,
        ActivityLogger $audit
    ) {
        $this->repo = $repo;
        $this->ledger = $ledger;
        $this->audit = $audit;
    }

    public function createDraft(array $data): int {
        $data['status'] = 'DRAFT';
        $id = $this->repo->create($data);
        $this->audit->log('expense.create', 'expense', $id, ['created_by' => $data['created_by']]);
        return $id;
    }

    public function post(int $id, int $approverId): void {
        $expense = $this->repo->findById($id);
        if (!$expense) throw new \DomainException('Expense not found');
        if ($expense['status'] !== 'DRAFT') throw new \DomainException('Only DRAFT can be posted');

        Database::getInstance()->begin();
        try {
            $this->repo->approve($id, $approverId);

            $this->ledger->createEntry([
                'entry_date' => $expense['expense_date'],
                'cash_account_id' => $expense['cash_account_id'],
                'direction' => 'OUT',
                'category' => 'BIAYA_OPERASIONAL',
                'amount' => (int)$expense['amount'],
                'source_type' => 'EXPENSE',
                'source_id' => $id,
                'created_by' => $approverId,
                'note' => $expense['description']
            ]);

            Database::getInstance()->commit();
            $this->audit->log('expense.post', 'expense', $id, ['amount' => $expense['amount']]);
        } catch (\Throwable $e) {
            Database::getInstance()->rollback();
            throw $e;
        }
    }

    public function void(int $id, int $voidById, string $reason): void {
        $expense = $this->repo->findById($id);
        if (!$expense) throw new \DomainException('Expense not found');
        if ($expense['status'] !== 'POSTED') throw new \DomainException('Only POSTED can be voided');

        Database::getInstance()->begin();
        try {
            $this->repo->void($id, $voidById, $reason);

            // Reversing ledger entry
            $this->ledger->createReversal($id, $voidById, $reason);

            Database::getInstance()->commit();
            $this->audit->log('expense.void', 'expense', $id, ['reason' => $reason]);
        } catch (\Throwable $e) {
            Database::getInstance()->rollback();
            throw $e;
        }
    }
}