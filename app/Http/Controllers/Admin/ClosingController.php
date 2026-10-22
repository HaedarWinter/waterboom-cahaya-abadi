<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Finance\ClosingRepository;
use App\Domain\Finance\ClosingService;
use App\Domain\Finance\LedgerService;
use App\Domain\Audit\ActivityLogger;
use App\Core\Database;

class ClosingController {
    private ClosingService $service;
    private ClosingRepository $repo;

    public function __construct() {
        $conn = Database::getInstance()->getConnection();
        $this->repo = new ClosingRepository($conn);
        $this->service = new ClosingService(
            $this->repo,
            new LedgerService($conn),
            new ActivityLogger($conn)
        );
    }

    public function index(): void {
        if (!has_canonical_permission('closing.perform')) {
            http_response_code(403);
            echo "Akses ditolak.";
            return;
        }

        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');
        $closings = $this->repo->findByDateRange($startDate, $endDate);

        require __DIR__ . '/../../../resources/views/admin/closings/index.php';
    }

    public function perform(): void {
        if (!has_canonical_permission('closing.perform')) {
            http_response_code(403);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $date = $_POST['closing_date'] ?? date('Y-m-d');
            $accountId = (int)($_POST['cash_account_id'] ?? 1);
            $physicalCash = (int)($_POST['physical_cash'] ?? 0);
            $note = $_POST['difference_note'] ?? '';

            try {
                $result = $this->service->performClosing($date, $accountId, $_SESSION['user_id'] ?? 0, $physicalCash, $note);
                $_SESSION['flash'] = 'Tutup harian berhasil. Periode dikunci.';
                header("Location: /ujikom1/admin/closings.php");
            } catch (\Throwable $e) {
                $_SESSION['error'] = $e->getMessage();
                header("Location: /ujikom1/admin/closings.php?view=form");
            }
            return;
        }

        require __DIR__ . '/../../../resources/views/admin/closings/form.php';
    }

    public function reopen(): void {
        if (!has_canonical_permission('closing.reopen')) {
            http_response_code(403);
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        $reason = $_POST['reason'] ?? '';

        try {
            $this->service->reopen($id, $_SESSION['user_id'] ?? 0, $reason);
            $_SESSION['flash'] = 'Periode dibuka kembali. Ledger entries unlocked.';
        } catch (\Throwable $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header("Location: /ujikom1/admin/closings.php");
    }
}