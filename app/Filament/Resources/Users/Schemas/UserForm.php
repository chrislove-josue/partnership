<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\RoleName;
use App\Models\Church;
use App\Services\ChurchAccessService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        $access = app(ChurchAccessService::class);
        $actor = Auth::user();

        $churchOptions = $actor
            ? $access->accessibleChurches($actor)->pluck('name', 'id')->all()
            : Church::query()->pluck('name', 'id')->all();

        $roleOptions = collect(RoleName::cases())
            ->filter(fn (RoleName $role) => $actor && $access->canManageUser($actor, $role->value))
            ->mapWithKeys(fn (RoleName $role) => [$role->value => $role->label()])
            ->all();

        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom complet')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Adresse email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('Téléphone')
                    ->tel()
                    ->unique(ignoreRecord: true),
                TextInput::make('handle_kc')
                    ->label('Identifiant KingsChat (handle KC)')
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->label('Mot de passe')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->maxLength(255),
                Select::make('roles')
                    ->label('Rôle')
                    ->options($roleOptions)
                    ->multiple()
                    ->maxItems(1)
                    ->required()
                    ->relationship('roles', 'name'),
                Select::make('assigned_church_id')
                    ->label('Église assignée')
                    ->options($churchOptions)
                    ->searchable()
                    ->required(),
                Toggle::make('is_active')
                    ->label('Compte actif')
                    ->default(true),
                DateTimePicker::make('email_verified_at')
                    ->label('Email vérifié le')
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }
}
