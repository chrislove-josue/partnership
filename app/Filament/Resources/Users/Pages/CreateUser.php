<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\UserInviter;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        app(UserInviter::class)->sendWelcome(
            $this->record,
            $this->data['password'] ?? null,
            auth()->user()?->name,
        );
    }
}
