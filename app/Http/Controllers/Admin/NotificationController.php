<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Notification\OutboxRepository;
use App\Domain\Notification\OutboxService;
use App\Domain\Notification\WhatsAppClient;
use App\Domain\Audit\ActivityLogger;
use App\Core\Database;

class NotificationController {
    private OutboxService $service;
    private OutboxRepository $repo;

    public function __construct() {
        $conn = Database::getInstance()->getConnection();
        $this->repo = new OutboxRepository($conn);
        $this->service = new OutboxService(
            $this->repo,
            new WhatsAppClient(),
            new ActivityLogger($conn)
        );
    }

    public function index(): void {
        if (!has_canonical_permission('dashboard.view')) {
            http_response_code(403);
            echo "Akses ditolak.";
            return;
        }

        $stats = $this->repo->getStats();
        $schemaReady = $this->repo->isAvailable();

        require dirname(__DIR__, 4) . '/resources/views/admin/notifications/index.php';
    }

    public function retry(): void {
        if (!$this->requireWriteRequest()) return;
        if (!has_canonical_permission('settings.manage')) {
            http_response_code(403);
            return;
        }

        $count = $this->repo->retryFailed();
        $_SESSION['flash'] = "$count notifikasi gagal direset ke PENDING.";
        header('Location: ' . base_url('admin/notifications.php'));
    }

    public function processQueue(): void {
        if (!$this->requireWriteRequest()) return;
        if (!has_canonical_permission('settings.manage')) {
            http_response_code(403);
            return;
        }

        $results = $this->service->processQueue(50);
        $_SESSION['flash'] = "Sent: {$results['sent']}, Failed: {$results['failed']}";
        header('Location: ' . base_url('admin/notifications.php'));
    }

    private function requireWriteRequest(): bool {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            return false;
        }

        if (!validate_csrf($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            return false;
        }

        return true;
    }
}
