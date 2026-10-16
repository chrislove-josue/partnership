<?php

namespace App\Filament\Resources\Partners\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PartnerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name')
                    ->required(),
                TextInput::make('last_name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->default(null),
                TextInput::make('phone')
                    ->tel()
                    ->default(null),
                TextInput::make('handle_kc')
                    ->default(null),
                TextInput::make('address')
                    ->default(null),
                DatePicker::make('birthdate'),
                Select::make('cellule_id')
                    ->relationship('cellule', 'name')
                    ->default(null),
                Select::make('church_id')
                    ->relationship('church', 'name')
                    ->required(),
            ]);
    }
}
