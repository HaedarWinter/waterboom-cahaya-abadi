<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Response;

class RequirePermission
{
    public function __construct(private string $permission) {}

    public function handle(callable $next): void
    {
        if (!has_canonical_permission($this->permission)) {
            Response::forbidden('Akses ditolak: izin tidak cukup.');
            return;
        }
        $next();
    }
}