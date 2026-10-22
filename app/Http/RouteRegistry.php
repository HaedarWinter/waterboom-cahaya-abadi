<?php
declare(strict_types=1);

namespace App\Http;

class RouteRegistry
{
    private static array $routes = [];

    public static function register(string $path, string $permission): void
    {
        self::$routes[$path] = $permission;
    }

    public static function getRequired(string $path): ?string
    {
        return self::$routes[$path] ?? null;
    }

    public static function all(): array
    {
        return self::$routes;
    }
}
