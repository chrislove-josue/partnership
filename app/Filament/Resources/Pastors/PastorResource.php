<?php

namespace App\Filament\Resources\Pastors;

use App\Filament\Resources\Concerns\ScopesByChurch;
use App\Filament\Resources\Pastors\Pages\CreatePastor;
use App\Filament\Resources\Pastors\Pages\EditPastor;
use App\Filament\Resources\Pastors\Pages\ListPastors;
use App\Filament\Resources\Pastors\Tables\PastorsTable;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PastorResource extends Resource
{
    use ScopesByChurch;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $modelLabel = 'pasteur';

    protected static ?string $pluralModelLabel = 'pasteurs';

    protected static ?string $navigationLabel = 'Pasteurs';

    protected static ?int $navigationSort = 11;

    public static function churchScopeColumn(): string
    {
        return 'assigned_church_id';
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PastorsTable::configure($table);
    }

    /** @return array<string, class-string> */
    public static function getPages(): array
    {
        return [
            'index' => ListPastors::route('/'),
            'create' => CreatePastor::route('/create'),
            'edit' => EditPastor::route('/{record}/edit'),
        ];
    }
}
