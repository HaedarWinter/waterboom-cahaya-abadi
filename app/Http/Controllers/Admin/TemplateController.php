<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Notification\TemplateRepository;
use App\Domain\Notification\TemplateService;
use App\Domain\Audit\ActivityLogger;
use App\Core\Database;

class TemplateController {
    private TemplateService $service;

    public function __construct() {
        $conn = Database::getInstance()->getConnection();
        $this->service = new TemplateService(
            new TemplateRepository($conn),
            new ActivityLogger($conn)
        );
    }

    public function index(): void {
        if (!has_canonical_permission('settings.manage')) {
            http_response_code(403);
            echo "Akses ditolak.";
            return;
        }

        $templates = $this->service->getAll();
        $schemaReady = $this->service->isAvailable();

        require dirname(__DIR__, 4) . '/resources/views/admin/templates/index.php';
    }

    public function edit(): void {
        if (!has_canonical_permission('settings.manage')) {
            http_response_code(403);
            return;
        }

        $key = $_GET['key'] ?? '';
        $template = $this->service->get($key);

        if (!$template) {
            http_response_code(404);
            echo "Template not found.";
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->update($template['id']);
            return;
        }

        require dirname(__DIR__, 4) . '/resources/views/admin/templates/edit.php';
    }

    private function update(int $id): void {
        $this->service->update($id, [
            'name' => $_POST['name'] ?? '',
            'subject' => $_POST['subject'] ?? null,
            'body' => $_POST['body'] ?? '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'updated_by' => $_SESSION['user_id'] ?? 0,
        ]);

        $_SESSION['flash'] = 'Template berhasil diperbarui.';
        header("Location: " . base_url('admin/templates.php'));
    }
}
