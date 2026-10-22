<?php
declare(strict_types=1);

namespace App\Support;

class SignedUrl {
    private static function secret(): string {
        return getenv('SIGNED_URL_SECRET') ?: hash_hmac('sha256', 'waterboom-signed-url', 'default-secret-change-in-production');
    }

    public static function sign(array $payload, string $expiry = '+1 hour'): string {
        $payload['exp'] = strtotime($expiry);
        $payload['iat'] = time();
        
        $data = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $signature = hash_hmac('sha256', $data, self::secret());
        
        return $data . '.' . $signature;
    }

    public static function verify(string $token): ?array {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) return null;

        [$data, $signature] = $parts;
        $expected = hash_hmac('sha256', $data, self::secret());
        
        if (!hash_equals($expected, $signature)) return null;

        $payload = json_decode(base64_decode($data), true);
        if (!$payload) return null;

        if (isset($payload['exp']) && $payload['exp'] < time()) return null;

        return $payload;
    }
}