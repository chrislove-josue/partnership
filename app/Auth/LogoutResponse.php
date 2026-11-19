<?php

namespace App\Auth;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Http\RedirectResponse;

/** Après déconnexion, retour direct sur /login plutôt que sur /dashboard/login. */
class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        return redirect('/login');
    }
}
