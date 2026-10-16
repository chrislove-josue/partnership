<?php

namespace App\Filament\Resources\Donations\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DonationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                DateTimePicker::make('date')
                    ->required(),
                Select::make('partner_id')
                    ->relationship('partner', 'id')
                    ->required(),
                Select::make('church_id')
                    ->relationship('church', 'name')
                    ->required(),
                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->required(),
            ]);
    }
}
