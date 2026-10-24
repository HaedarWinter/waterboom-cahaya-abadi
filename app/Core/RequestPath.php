<?php
declare(strict_types=1);

namespace App\Core;

final class RequestPath
{
    public static function relativeToBase(string $requestPath, string $basePath): string
    {
        $path = parse_url($requestPath, PHP_URL_PATH) ?: '/';
        $base = rtrim('/' . trim($basePath, '/'), '/');

        if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
            $path = substr($path, strlen($base)) ?: '/';
        }

        return '/' . ltrim($path, '/');
    }
}
