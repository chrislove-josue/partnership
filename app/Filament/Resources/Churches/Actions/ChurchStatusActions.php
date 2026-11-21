<?php

namespace App\Filament\Resources\Churches\Actions;

use App\Models\Church;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Gate;

final class ChurchStatusActions
{
    public static function deactivate(): Action
    {
        return Action::make('deactivate')
            ->label('Désactiver')
            ->icon('heroicon-o-lock-closed')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Désactiver cette église ?')
            ->modalDescription(fn (Church $record): string => sprintf(
                'L’église « %s » sera désactivée. Ses églises filles ne sont pas concernées.',
                $record->name,
            ))
            ->modalSubmitActionLabel('Désactiver')
            ->authorize(fn (Church $record): bool => Gate::allows('toggleActive', $record))
            ->visible(fn (Church $record): bool => $record->is_active)
            ->action(fn (Church $record): bool => $record->update(['is_active' => false]));
    }

    public static function activate(): Action
    {
        return Action::make('activate')
            ->label('Réactiver')
            ->icon('heroicon-o-lock-open')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Réactiver cette église ?')
            ->modalDescription(fn (Church $record): string => sprintf(
                'L’église « %s » sera réactivée.',
                $record->name,
            ))
            ->modalSubmitActionLabel('Réactiver')
            ->authorize(fn (Church $record): bool => Gate::allows('toggleActive', $record))
            ->visible(fn (Church $record): bool => ! $record->is_active)
            ->action(fn (Church $record): bool => $record->update(['is_active' => true]));
    }
}
