<?php
declare(strict_types=1);

namespace App\Domain\Notification;

use App\Core\Database;
use App\Domain\Audit\ActivityLogger;

class TemplateService {
    private TemplateRepository $repo;
    private \App\Domain\Audit\ActivityLogger $audit;

    public function __construct(
        TemplateRepository $repo,
        \App\Domain\Audit\ActivityLogger $audit
    ) {
        $this->repo = $repo;
        $this->audit = $audit;
    }

    public function getAll(): array {
        return $this->repo->findAll();
    }

    public function get(string $key): ?array {
        return $this->repo->findByKey($key);
    }

    public function create(array $data): int {
        $id = $this->repo->create($data);
        $this->audit->log('template.create', 'wa_template', $id, ['key' => $data['key']]);
        return $id;
    }

    public function update(int $id, array $data): bool {
        $result = $this->repo->update($id, $data);
        if ($result) {
            $this->audit->log('template.update', 'wa_template', $id, ['fields' => array_keys($data)]);
        }
        return $result;
    }

    public function render(string $key, array $variables): string {
        $template = $this->repo->findByKey($key);
        if (!$template) throw new \DomainException("Template '$key' not found");

        return $this->renderTemplate($template['body'], $variables);
    }

    private function renderTemplate(string $template, array $vars): string {
        return preg_replace_callback('/\{(\w+)\}/', function($m) use ($vars) {
            return $vars[$m[1]] ?? $m[0];
        }, $template);
    }
}