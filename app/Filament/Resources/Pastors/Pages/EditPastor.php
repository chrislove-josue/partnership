<?php

namespace App\Filament\Resources\Pastors\Pages;

use App\Filament\Resources\Concerns\RedirectsToListAfterSave;
use App\Filament\Resources\Pastors\PastorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPastor extends EditRecord
{
    use RedirectsToListAfterSave;

    protected static string $resource = PastorResource::class;

    /** @var array<int, mixed>|null */
    private ?array $pendingRoles = null;

    /** @param array<string, mixed> $data */
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

    /** @return array<int, DeleteAction> */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
