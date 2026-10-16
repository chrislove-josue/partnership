<?php

namespace App\Services;

use App\Mail\UserWelcomeMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UserInviter
{
    public function __construct(private readonly MagicTokenService $tokens) {}

    public function sendWelcome(User $user, ?string $tempPassword, ?string $adminName = null): bool
    {
        try {
            $magicLink = $this->tokens->generate($user->email);

            Mail::to($user->email)->send(new UserWelcomeMail(
                userEmail: $user->email,
                tempPassword: $tempPassword ?: '(défini par l\'administrateur)',
                adminName: $adminName ?: 'Suivi des Partenariats',
                magicLink: $magicLink,
            ));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Échec envoi email de bienvenue : '.$e->getMessage());

            return false;
        }
    }
}
