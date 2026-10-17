<?php

namespace App\Filament\Resources\Churches;

use App\Filament\Resources\Churches\Pages\CreateChurch;
use App\Filament\Resources\Churches\Pages\EditChurch;
use App\Filament\Resources\Churches\Pages\ListChurches;
use App\Filament\Resources\Churches\Schemas\ChurchForm;
use App\Filament\Resources\Churches\Tables\ChurchesTable;
use App\Filament\Resources\Concerns\ScopesByChurch;
use App\Models\Church;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ChurchResource extends Resource
{
    use ScopesByChurch;

    protected static ?string $model = Church::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Structure';

    protected static ?string $modelLabel = 'église';

    protected static ?string $pluralModelLabel = 'églises';

    protected static ?string $navigationLabel = 'Églises';

    public static function churchScopeColumn(): string
    {
        return 'id';
    }

    public static function form(Schema $schema): Schema
    {
        return ChurchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChurchesTable::configure($table);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChurches::route('/'),
            'create' => CreateChurch::route('/create'),
            'edit' => EditChurch::route('/{record}/edit'),
        ];
    }
}
