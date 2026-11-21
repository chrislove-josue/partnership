<?php

namespace App\Filament\Resources\Pastors\Pages;

use App\Enums\RoleName;
use App\Filament\Resources\Concerns\RedirectsToListAfterSave;
use App\Filament\Resources\Pastors\PastorResource;
use App\Services\UserInviter;
use Filament\Resources\Pages\CreateRecord;

class CreatePastor extends CreateRecord
{
    use RedirectsToListAfterSave;

    protected static string $resource = PastorResource::class;

    /** @var array<int, mixed> */
    private array $pendingRoles = [];

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $roles = $data['roles'] ?? [];
        $this->pendingRoles = is_array($roles) && $roles !== []
            ? $roles
            : [RoleName::LOCAL_PASTOR->value];
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
