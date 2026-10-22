<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Finance\ReportService;
use App\Core\Database;

class ReportController {
    private ReportService $service;

    public function __construct() {
        $this->service = new ReportService(Database::getInstance()->getConnection());
    }

    public function cashFlow(): void {
        if (!has_canonical_permission('report.finance.view')) {
            http_response_code(403);
            echo "Akses ditolak.";
            return;
        }

        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');
        $accountId = (int)($_GET['account_id'] ?? 0);

        $data = $this->service->getCashFlow($startDate, $endDate, $accountId);

        require __DIR__ . '/../../../resources/views/admin/reports/cash_flow.php';
    }

    public function revenue(): void {
        if (!has_canonical_permission('report.finance.view')) {
            http_response_code(403);
            echo "Akses ditolak.";
            return;
        }

        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');

        $data = $this->service->getRevenueByTicketType($startDate, $endDate);

        require __DIR__ . '/../../../resources/views/admin/reports/revenue.php';
    }

    public function expenses(): void {
        if (!has_canonical_permission('report.finance.view')) {
            http_response_code(403);
            echo "Akses ditolak.";
            return;
        }

        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');

        $data = $this->service->getExpensesByCategory($startDate, $endDate);

        require __DIR__ . '/../../../resources/views/admin/reports/expenses.php';
    }

    public function balance(): void {
        if (!has_canonical_permission('report.finance.view')) {
            http_response_code(403);
            echo "Akses ditolak.";
            return;
        }

        $accountId = (int)($_GET['account_id'] ?? 1);
        $endDate = $_GET['end_date'] ?? date('Y-m-d');

        $data = $this->service->getBalanceSummary($accountId, $endDate);

        require __DIR__ . '/../../../resources/views/admin/reports/balance.php';
    }
}