<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Mail\UserWelcomeMail;
use App\Services\MagicTokenService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $user = $this->record;

        try {
            $magicLink = app(MagicTokenService::class)->generate($user->email);

            Mail::to($user->email)->send(new UserWelcomeMail(
                userEmail: $user->email,
                tempPassword: $this->data['password'] ?? '(défini par l\'administrateur)',
                adminName: auth()->user()?->name ?? 'Suivi des Partenariats',
                magicLink: $magicLink,
            ));
        } catch (\Throwable $e) {
            Log::warning('Échec envoi email de bienvenue : '.$e->getMessage());
        }
    }
}
