<?php

namespace App\Filament\Resources\Cellules\Pages;

use App\Filament\Resources\Cellules\CelluleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCellule extends EditRecord
{
    protected static string $resource = CelluleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
