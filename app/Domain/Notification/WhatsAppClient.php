<?php
declare(strict_types=1);

namespace App\Domain\Notification;

class WhatsAppClient {
    private string $driver;
    private string $apiUrl;
    private string $token;
    private $log = [];

    public function __construct() {
        $this->driver = getenv('WA_DRIVER') ?: 'log';
        
        if ($this->driver === 'fonnte') {
            $this->apiUrl = getenv('FONNTE_API_URL') ?: 'https://api.fonnte.com/send';
            $this->token = getenv('FONNTE_TOKEN') ?: '';
        }
    }

    public function send(string $recipient, string $body): void {
        if ($this->driver === 'fonnte') {
            $this->sendViaFonnte($recipient, $body);
        } else {
            $this->logDemo($recipient, $body);
        }
    }

    private function sendViaFonnte(string $recipient, string $body): void {
        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'target' => $recipient,
                'message' => $body,
            ]),
            CURLOPT_HTTPHEADER => ['Authorization: ' . $this->token],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException("Fonnte API error: $httpCode - $response");
        }

        $data = json_decode($response, true);
        if (!($data['status'] ?? false)) {
            throw new \RuntimeException("Fonnte send failed: " . ($data['reason'] ?? 'unknown'));
        }
    }

    private function logDemo(string $recipient, string $body): void {
        $entry = [
            'time' => date('Y-m-d H:i:s'),
            'driver' => 'log',
            'recipient' => $recipient,
            'body' => $body,
        ];

        $this->log[] = $entry;
        error_log('[WA-DEMO] ' . json_encode($entry, JSON_UNESCAPED_UNICODE));
    }

    public function getDemoLog(): array {
        return $this->log;
    }
}