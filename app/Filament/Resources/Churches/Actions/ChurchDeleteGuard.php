<?php

namespace App\Filament\Resources\Churches\Actions;

use App\Models\Church;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;

final class ChurchDeleteGuard
{
    public static function beforeDelete(Action $action): void
    {
        $church = $action->getRecord();

        if (! $church instanceof Church || ! self::isBlocked($church)) {
            return;
        }

        self::notify($church);
        $action->cancel();
    }

    public static function beforeBulkDelete(DeleteBulkAction $action): void
    {
        foreach ($action->getSelectedRecords() as $church) {
            if (! $church instanceof Church || ! self::isBlocked($church)) {
                continue;
            }

            self::notify($church);
            $action->cancel();
        }
    }

    private static function isBlocked(Church $church): bool
    {
        return $church->children()->exists()
            || User::query()->where('assigned_church_id', $church->id)->exists();
    }

    private static function notify(Church $church): void
    {
        Notification::make()
            ->title('Suppression impossible')
            ->body(sprintf(
                'L’église « %s » contient encore des sous-églises ou des utilisateurs.',
                $church->name,
            ))
            ->danger()
            ->send();
    }
}
