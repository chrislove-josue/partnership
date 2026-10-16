<?php

namespace App\Filament\Resources\CategoryDons\Pages;

use App\Filament\Resources\CategoryDons\CategoryDonResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCategoryDon extends EditRecord
{
    protected static string $resource = CategoryDonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
