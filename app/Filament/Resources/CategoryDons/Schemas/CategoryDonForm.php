<?php

namespace App\Filament\Resources\CategoryDons\Schemas;

use App\Enums\CategoryGroup;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CategoryDonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom de la catégorie')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Select::make('group')->selectablePlaceholder(false)
                    ->label('Famille')
                    ->options(collect(CategoryGroup::cases())->mapWithKeys(
                        fn (CategoryGroup $group): array => [$group->value => $group->label()],
                    )->all())
                    ->default(CategoryGroup::LOCAL_PARTNERSHIP->value)
                    ->required(),
                ColorPicker::make('color')
                    ->label('Couleur')
                    ->required()
                    ->default('#3B82F6'),
            ]);
    }
}
