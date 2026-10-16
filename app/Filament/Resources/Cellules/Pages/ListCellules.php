<?php

namespace App\Filament\Resources\Cellules\Pages;

use App\Filament\Resources\Cellules\CelluleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCellules extends ListRecords
{
    protected static string $resource = CelluleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
