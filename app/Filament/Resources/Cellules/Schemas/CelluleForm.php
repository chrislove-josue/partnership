<?php

namespace App\Filament\Resources\Cellules\Schemas;

use App\Services\ChurchAccessService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class CelluleForm
{
    public static function configure(Schema $schema): Schema
    {
        $access = app(ChurchAccessService::class);
        $actor = Auth::user();
        $churchIds = $actor ? $access->accessibleChurchIds($actor) : [];

        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom de la cellule')
                    ->required()
                    ->maxLength(255),
                Select::make('church_id')
                    ->label('Église')
                    ->relationship('church', 'name', fn ($query) => $query->whereIn('id', $churchIds))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                Select::make('leader_id')
                    ->label('Responsable (partenaire)')
                    ->relationship('leader', 'first_name', fn ($query, $get) => $query
                        ->when($get('church_id'), fn ($q, $churchId) => $q->where('church_id', $churchId))
                        ->whereIn('church_id', $churchIds))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                    ->searchable(['first_name', 'last_name'])
                    ->preload(),
            ]);
    }
}
