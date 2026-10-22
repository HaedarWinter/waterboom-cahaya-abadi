<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Finance\ExpenseRepository;
use App\Domain\Finance\ExpenseService;
use App\Domain\Finance\LedgerService;
use App\Domain\Audit\ActivityLogger;
use App\Core\Database;

class ExpenseController {
    private ExpenseService $service;
    private ExpenseRepository $repo;

    public function __construct() {
        $conn = Database::getInstance()->getConnection();
        $this->repo = new ExpenseRepository($conn);
        $this->service = new ExpenseService(
            $this->repo,
            new LedgerService($conn),
            new ActivityLogger($conn)
        );
    }

    public function index(): void {
        if (!has_canonical_permission('ledger.entry_manual')) {
            http_response_code(403);
            echo "Akses ditolak.";
            return;
        }

        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');
        $expenses = $this->repo->findByDateRange($startDate, $endDate);

        require __DIR__ . '/../../../resources/views/admin/expenses/index.php';
    }

    public function create(): void {
        if (!has_canonical_permission('ledger.entry_manual')) {
            http_response_code(403);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->store();
            return;
        }

        require __DIR__ . '/../../../resources/views/admin/expenses/create.php';
    }

    public function store(): void {
        if (!has_canonical_permission('ledger.entry_manual')) {
            http_response_code(403);
            return;
        }

        $id = $this->service->createDraft([
            'expense_date' => $_POST['expense_date'] ?? date('Y-m-d'),
            'cash_account_id' => (int)($_POST['cash_account_id'] ?? 1),
            'category' => $_POST['category'] ?? 'LAINNYA',
            'description' => $_POST['description'] ?? '',
            'amount' => (int)($_POST['amount'] ?? 0),
            'receipt_path' => $_POST['receipt_path'] ?? null,
            'created_by' => $_SESSION['user_id'] ?? 0,
        ]);

        $_SESSION['flash'] = 'Pengeluaran dibuat sebagai draft.';
        header("Location: /ujikom1/admin/expenses.php?view=show&id=$id");
    }

    public function show(): void {
        if (!has_canonical_permission('ledger.entry_manual')) {
            http_response_code(403);
            return;
        }

        $id = (int)($_GET['id'] ?? 0);
        $expense = $this->repo->findById($id);
        if (!$expense) {
            http_response_code(404);
            echo "Not found.";
            return;
        }

        require __DIR__ . '/../../../resources/views/admin/expenses/show.php';
    }

    public function post(): void {
        if (!has_canonical_permission('ledger.entry_manual')) {
            http_response_code(403);
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        try {
            $this->service->post($id, $_SESSION['user_id'] ?? 0);
            $_SESSION['flash'] = 'Pengeluaran diposting dan ledger diperbarui.';
        } catch (\Throwable $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header("Location: /ujikom1/admin/expenses.php?view=show&id=$id");
    }

    public function void(): void {
        if (!has_canonical_permission('ledger.entry_manual')) {
            http_response_code(403);
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        $reason = $_POST['reason'] ?? '';
        try {
            $this->service->void($id, $_SESSION['user_id'] ?? 0, $reason);
            $_SESSION['flash'] = 'Pengeluaran dibatalkan; reversal entry dibuat.';
        } catch (\Throwable $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header("Location: /ujikom1/admin/expenses.php?view=show&id=$id");
    }
}