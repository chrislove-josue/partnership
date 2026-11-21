<?php

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        // Les champs sensibles ne sont modifiables que par un super administrateur :
        // un pasteur ne doit pas pouvoir s'élever en modifiant un rôle système.
        $locked = fn (): bool => ! auth()->user()?->isSuperAdmin();

        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom du rôle')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->disabled($locked),
                Hidden::make('guard_name')
                    ->default('web'),
                CheckboxList::make('permissions')
                    ->label('Permissions')
                    ->relationship('permissions', 'name')
                    ->columns(2)
                    ->bulkToggleable()
                    ->disabled($locked),
                Toggle::make('is_system')
                    ->label('Rôle système (non supprimable)')
                    ->default(false)
                    ->disabled($locked),
            ]);
    }
}
