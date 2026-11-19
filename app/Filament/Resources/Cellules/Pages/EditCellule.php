<?php

namespace App\Filament\Resources\Cellules\Pages;

use App\Filament\Resources\Cellules\CelluleResource;
use App\Filament\Resources\Concerns\RedirectsToListAfterSave;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCellule extends EditRecord
{
    use RedirectsToListAfterSave;

    protected static string $resource = CelluleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
