<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;

/**
 * Redirection directe vers /login (au lieu de /dashboard/login) pour tout visiteur
 * non authentifié, comme dans le middleware Next.js.
 */
class Authenticate extends FilamentAuthenticate
{
    protected function redirectTo($request): ?string
    {
        return route('login');
    }
}
