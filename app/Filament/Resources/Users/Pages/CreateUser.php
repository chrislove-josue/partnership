<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Concerns\RedirectsToListAfterSave;
use App\Filament\Resources\Users\UserResource;
use App\Services\UserInviter;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use RedirectsToListAfterSave;

    protected static string $resource = UserResource::class;

    /** @var array<int, mixed> */
    private array $pendingRoles = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingRoles = $data['roles'] ?? [];
        unset($data['roles']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncRoles($this->pendingRoles);

        $this->record->syncImpliedRoles();

        app(UserInviter::class)->sendWelcome(
            $this->record,
            $this->data['password'] ?? null,
            auth()->user()?->name,
        );
    }
}
