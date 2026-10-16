<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MagicTokenService;
use Illuminate\Http\Request;

class MagicLoginController extends Controller
{
    public function __invoke(Request $request, MagicTokenService $tokens)
    {
        $token = (string) $request->query('token', '');

        $magic = $token !== '' ? $tokens->consume($token) : null;

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
        $request->session()->regenerate();

        return redirect('/dashboard');
    }
}
