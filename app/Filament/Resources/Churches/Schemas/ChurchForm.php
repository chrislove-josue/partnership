<?php

namespace App\Filament\Resources\Churches\Schemas;

use App\Enums\ChurchType;
use App\Models\Church;
use App\Services\ChurchAccessService;
use App\Services\ChurchHierarchyService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ChurchForm
{
    public static function configure(Schema $schema): Schema
    {
        $access = app(ChurchAccessService::class);
        $hierarchy = app(ChurchHierarchyService::class);
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

                Select::make('parent_id')
                    ->label('Église parente')
                    ->options(function (Get $get) use ($access, $hierarchy, $actor): array {
                        $type = ChurchType::tryFrom((string) $get('type')) ?? ChurchType::LOCAL;
                        $allowedTypes = $hierarchy->allowedParentTypes($type);

                        if ($allowedTypes === []) {
                            return [];
                        }

                        if (! $actor) {
                            return [];
                        }

                        $recordId = $get('id');
                        $excludedIds = $recordId
                            ? array_merge([(int) $recordId], Church::query()->find((int) $recordId)?->descendantIds() ?? [])
                            : [];

                        return Church::query()
                            ->whereIn('id', $access->accessibleChurchIds($actor))
                            ->whereIn('type', array_map(fn (ChurchType $t): string => $t->value, $allowedTypes))
                            ->when($excludedIds !== [], fn ($query) => $query->whereNotIn('id', $excludedIds))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->visible(fn (Get $get): bool => ChurchType::tryFrom((string) $get('type')) !== ChurchType::GROUP)
                    ->required(fn (Get $get): bool => ChurchType::tryFrom((string) $get('type')) !== ChurchType::GROUP)
                    ->default(fn (): ?int => $actor && ! $actor->isSuperAdmin()
                        ? $access->defaultChurchId($actor)
                        : null)
                    ->searchable()
                    ->preload()
                    ->helperText(fn (Get $get): string => ChurchType::tryFrom((string) $get('type')) === ChurchType::GROUP
                        ? 'Un groupe est à la racine de l’arbre : aucun parent.'
                        : 'Un sous-groupe ou une église locale doit être rattaché à un groupe ou un sous-groupe.'),

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
