<?php

namespace App\Filament\Resources\Churches\Pages;

use App\Filament\Resources\Churches\ChurchResource;
use App\Filament\Resources\Concerns\RedirectsToListAfterSave;
use Filament\Resources\Pages\CreateRecord;

class CreateChurch extends CreateRecord
{
    use RedirectsToListAfterSave;

    protected static string $resource = ChurchResource::class;
}
