<?php

namespace App\Filament\Resources\CategoryDons\Pages;

use App\Filament\Resources\CategoryDons\CategoryDonResource;
use App\Filament\Resources\Concerns\RedirectsToListAfterSave;
use Filament\Resources\Pages\CreateRecord;

class CreateCategoryDon extends CreateRecord
{
    use RedirectsToListAfterSave;

    protected static string $resource = CategoryDonResource::class;
}
