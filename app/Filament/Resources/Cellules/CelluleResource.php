<?php

namespace App\Filament\Resources\Cellules;

use App\Filament\Resources\Cellules\Pages\CreateCellule;
use App\Filament\Resources\Cellules\Pages\EditCellule;
use App\Filament\Resources\Cellules\Pages\ListCellules;
use App\Filament\Resources\Cellules\Schemas\CelluleForm;
use App\Filament\Resources\Cellules\Tables\CellulesTable;
use App\Models\Cellule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CelluleResource extends Resource
{
    protected static ?string $model = Cellule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return CelluleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CellulesTable::configure($table);
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
            'index' => ListCellules::route('/'),
            'create' => CreateCellule::route('/create'),
            'edit' => EditCellule::route('/{record}/edit'),
        ];
    }
}
