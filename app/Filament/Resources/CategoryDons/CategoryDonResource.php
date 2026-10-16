<?php

namespace App\Filament\Resources\CategoryDons;

use App\Filament\Resources\CategoryDons\Pages\CreateCategoryDon;
use App\Filament\Resources\CategoryDons\Pages\EditCategoryDon;
use App\Filament\Resources\CategoryDons\Pages\ListCategoryDons;
use App\Filament\Resources\CategoryDons\Schemas\CategoryDonForm;
use App\Filament\Resources\CategoryDons\Tables\CategoryDonsTable;
use App\Models\CategoryDon;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CategoryDonResource extends Resource
{
    protected static ?string $model = CategoryDon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return CategoryDonForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoryDonsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategoryDons::route('/'),
            'create' => CreateCategoryDon::route('/create'),
            'edit' => EditCategoryDon::route('/{record}/edit'),
        ];
    }
}
