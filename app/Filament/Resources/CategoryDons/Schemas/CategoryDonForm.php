<?php

namespace App\Filament\Resources\CategoryDons\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CategoryDonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('color')
                    ->required()
                    ->default('#3B82F6'),
            ]);
    }
}
