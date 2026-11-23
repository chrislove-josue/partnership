<?php

namespace App\Filament\Resources\Churches\Schemas;

use App\Enums\ChurchType;
use App\Services\ChurchAccessService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ChurchForm
{
    public static function configure(Schema $schema): Schema
    {
        $access = app(ChurchAccessService::class);
        $actor = Auth::user();

        $typeOptions = collect(ChurchType::cases())
            ->filter(fn (ChurchType $type) => $actor && $access->canCreateChurch($actor, $type))
            ->mapWithKeys(fn (ChurchType $type) => [$type->value => $type->label()])
            ->all();

        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom de l\'église')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Select::make('type')->selectablePlaceholder(false)
                    ->label('Type')
                    ->options($typeOptions)
                    ->default(array_key_first($typeOptions))
                    ->required()
                    ->live(),
                Select::make('parent_id')->selectablePlaceholder(false)
                    ->label('Église parente')
                    ->relationship('parent', 'name', fn ($query) => $actor
                        ? $query->whereIn('id', $access->accessibleChurchIds($actor))
                        : $query)
                    ->default(fn (): ?int => $actor && ! $actor->isSuperAdmin()
                        ? $access->defaultChurchId($actor)
                        : null)
                    ->searchable()
                    ->preload(),
                Select::make('manager_id')->selectablePlaceholder(false)
                    ->label('Responsable (pasteur)')
                    ->relationship('manager', 'name', fn ($query) => $actor
                        ? $query->whereIn('assigned_church_id', $access->accessibleChurchIds($actor))
                        : $query)
                    ->searchable()
                    ->preload(),
            ]);
    }
}
