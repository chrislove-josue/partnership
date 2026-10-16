<?php

namespace App\Filament\Resources\CategoryDons\Pages;

use App\Filament\Resources\CategoryDons\CategoryDonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCategoryDons extends ListRecords
{
    protected static string $resource = CategoryDonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
