<?php
declare(strict_types=1);

namespace App\Domain\Notification;

use App\Core\Database;
use App\Core\Clock;

class OutboxService {
    private OutboxRepository $repo;
    private WhatsAppClient $waClient;
    private \App\Domain\Audit\ActivityLogger $audit;

    public function __construct(
        OutboxRepository $repo,
        WhatsAppClient $waClient,
        \App\Domain\Audit\ActivityLogger $audit
    ) {
        $this->repo = $repo;
        $this->waClient = $waClient;
        $this->audit = $audit;
    }

    public function sendWhatsApp(string $recipient, string $body, string $templateKey = null, string $subject = null, string $idempotencyKey = null): int {
        $id = $this->repo->enqueue([
            'channel' => 'WHATSAPP',
            'recipient' => $recipient,
            'template_key' => $templateKey,
            'subject' => $subject,
            'body' => $body,
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->audit->log('notification.enqueue', 'notification_outbox', $id, [
            'recipient' => $recipient, 'template' => $templateKey
        ]);

        return $id;
    }

    public function sendFromTemplate(string $templateKey, string $recipient, array $variables, string $idempotencyKey = null): int {
        $template = $this->getTemplate($templateKey);
        if (!$template) throw new \DomainException("Template '$templateKey' not found");

        $body = $this->renderTemplate($template['body'], $variables);
        $subject = $template['subject'] ? $this->renderTemplate($template['subject'], $variables) : null;

        return $this->sendWhatsApp($recipient, $body, $templateKey, $subject, $idempotencyKey);
    }

    public function processQueue(int $batchSize = 50): array {
        $pending = $this->repo->findPending($batchSize);
        $results = ['sent' => 0, 'failed' => 0];

        foreach ($pending as $msg) {
            try {
                if ($msg['channel'] === 'WHATSAPP') {
                    $this->waClient->send($msg['recipient'], $msg['body']);
                }
                $this->repo->markSent($msg['id']);
                $results['sent']++;
                $this->audit->log('notification.sent', 'notification_outbox', $msg['id']);
            } catch (\Throwable $e) {
                $this->repo->markFailed($msg['id'], $e->getMessage());
                $results['failed']++;
                $this->audit->log('notification.failed', 'notification_outbox', $msg['id'], ['error' => $e->getMessage()]);
            }
        }

        return $results;
    }

    private function getTemplate(string $key): ?array {
        $conn = Database::getInstance()->getConnection();
        $stmt = $conn->prepare("SELECT * FROM wa_templates WHERE `key` = ? AND is_active = 1");
        $stmt->bind_param('s', $key);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    private function renderTemplate(string $template, array $vars): string {
        return preg_replace_callback('/\{(\w+)\}/', function($m) use ($vars) {
            return $vars[$m[1]] ?? $m[0];
        }, $template);
    }
}