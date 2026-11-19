<?php

namespace App\Filament\Resources\Cellules\Pages;

use App\Filament\Resources\Cellules\CelluleResource;
use App\Filament\Resources\Concerns\RedirectsToListAfterSave;
use Filament\Resources\Pages\CreateRecord;

class CreateCellule extends CreateRecord
{
    use RedirectsToListAfterSave;

    protected static string $resource = CelluleResource::class;
}
