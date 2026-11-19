<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Profil complet : en plus de l'identité, l'utilisateur peut mettre à jour
 * son téléphone et son identifiant KingsChat.
 */
class EditProfile extends BaseEditProfile
{
    /** Évite l'enregistrement comme page navigable par la découverte Filament. */
    protected static bool $isDiscovered = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            TextInput::make('phone')
                ->label('Téléphone')
                ->tel()
                ->unique(ignoreRecord: true)
                ->maxLength(255),
            TextInput::make('handle_kc')
                ->label('Identifiant KingsChat (handle KC)')
                ->unique(ignoreRecord: true)
                ->maxLength(255),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }
}
