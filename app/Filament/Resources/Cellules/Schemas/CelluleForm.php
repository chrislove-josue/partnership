<?php

namespace App\Filament\Resources\Cellules\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CelluleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('church_id')
                    ->relationship('church', 'name')
                    ->required(),
                Select::make('leader_id')
                    ->relationship('leader', 'id')
                    ->default(null),
            ]);
    }
}
