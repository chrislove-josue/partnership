<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Concerns\RedirectsToListAfterSave;
use App\Filament\Resources\Users\Pages\Concerns\ValidatesUserAssignment;
use App\Filament\Resources\Users\UserResource;
use App\Services\UserInviter;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use RedirectsToListAfterSave;
    use ValidatesUserAssignment;

    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->assertAssignableRoles($data);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncImpliedRoles();

        app(UserInviter::class)->sendWelcome(
            $this->record,
            $this->data['password'] ?? null,
            auth()->user()?->name,
        );
    }
}
