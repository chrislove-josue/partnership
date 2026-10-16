<?php

namespace App\Filament\Resources\Churches\Schemas;

use App\Enums\ChurchType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ChurchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('type')
                    ->options(ChurchType::class)
                    ->required(),
                Select::make('parent_id')
                    ->relationship('parent', 'name')
                    ->default(null),
                Select::make('manager_id')
                    ->relationship('manager', 'name')
                    ->default(null),
            ]);
    }
}
