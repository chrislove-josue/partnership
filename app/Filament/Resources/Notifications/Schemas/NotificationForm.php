<?php

namespace App\Filament\Resources\Notifications\Schemas;

use App\Enums\NotificationType;
use App\Models\User;
use App\Services\ChurchAccessService;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class NotificationForm
{
    public static function configure(Schema $schema): Schema
    {
        $access = app(ChurchAccessService::class);
        $actor = Auth::user();
        $churchIds = $actor ? $access->accessibleChurchIds($actor) : [];

        return $schema
            ->components([
                Select::make('type')
                    ->label('Type')
                    ->options(NotificationType::class)
                    ->required(),
                TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->maxLength(255),
                Textarea::make('message')
                    ->label('Message')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
                Select::make('user_id')
                    ->label('Destinataire')
                    ->relationship('user', 'name', fn ($query) => $query->whereIn('assigned_church_id', $churchIds))
                    ->searchable()
                    ->preload()
                    ->required(),
                Toggle::make('is_read')
                    ->label('Lue')
                    ->default(false),
                KeyValue::make('data')
                    ->label('Données additionnelles')
                    ->columnSpanFull(),
            ]);
    }
}
