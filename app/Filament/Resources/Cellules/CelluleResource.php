<?php

namespace App\Filament\Resources\Cellules;

use App\Filament\Resources\Cellules\Pages\CreateCellule;
use App\Filament\Resources\Cellules\Pages\EditCellule;
use App\Filament\Resources\Cellules\Pages\ListCellules;
use App\Filament\Resources\Cellules\Schemas\CelluleForm;
use App\Filament\Resources\Cellules\Tables\CellulesTable;
use App\Filament\Resources\Concerns\ScopesByChurch;
use App\Models\Cellule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CelluleResource extends Resource
{
    use ScopesByChurch;

    protected static ?string $model = Cellule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|UnitEnum|null $navigationGroup = 'Structure';

    protected static ?string $modelLabel = 'cellule';

    protected static ?string $pluralModelLabel = 'cellules';

    protected static ?string $navigationLabel = 'Cellules';

    public static function form(Schema $schema): Schema
    {
        return CelluleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CellulesTable::configure($table);
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
