<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MagicTokenService;

class MagicLoginController extends Controller
{
    public function __invoke(string $token, MagicTokenService $tokens)
    {
        $magic = $tokens->consume($token);

        if (! $magic) {
            return redirect('/dashboard/login')->withErrors([
                'email' => 'Ce lien de connexion est invalide ou a expiré.',
            ]);
        }

        $user = User::query()->where('email', $magic->email)->first();

        if (! $user || ! $user->is_active) {
            return redirect('/dashboard/login')->withErrors([
                'email' => 'Compte introuvable ou désactivé.',
            ]);
        }

        auth()->login($user);
        request()->session()->regenerate();

        return redirect('/dashboard');
    }
}
