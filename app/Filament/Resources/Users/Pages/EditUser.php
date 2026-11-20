<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Concerns\RedirectsToListAfterSave;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use RedirectsToListAfterSave;

    protected static string $resource = UserResource::class;

    /** @var array<int, mixed>|null */
    private ?array $pendingRoles = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('roles', $data)) {
            $this->pendingRoles = $data['roles'];
            unset($data['roles']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->pendingRoles !== null) {
            $this->record->syncRoles($this->pendingRoles);
        }

        $this->record->syncImpliedRoles();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
