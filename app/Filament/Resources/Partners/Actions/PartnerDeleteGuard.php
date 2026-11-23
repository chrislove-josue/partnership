<?php

namespace App\Filament\Resources\Partners\Actions;

use App\Models\Partner;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;

/**
 * Empêche la suppression d'un partenaire miroir : il représente un compte
 * utilisateur et doit être géré depuis la liste des utilisateurs.
 */
final class PartnerDeleteGuard
{
    public static function beforeDelete(Action $action): void
    {
        $partner = $action->getRecord();

        if (! $partner instanceof Partner || ! self::isBlocked($partner)) {
            return;
        }

        self::notify($partner);
        $action->cancel();
    }

    public static function beforeBulkDelete(DeleteBulkAction $action): void
    {
        foreach ($action->getSelectedRecords() as $partner) {
            if (! $partner instanceof Partner || ! self::isBlocked($partner)) {
                continue;
            }

            self::notify($partner);
            $action->cancel();
        }
    }

    public static function isBlocked(Partner $partner): bool
    {
        return $partner->user_id !== null;
    }

    private static function notify(Partner $partner): void
    {
        Notification::make()
            ->title('Suppression impossible')
            ->body(sprintf(
                'Le partenaire « %s » est lié au compte utilisateur %s. Modifiez ou supprimez ce compte depuis la liste des utilisateurs.',
                $partner->full_name,
                $partner->user?->email ?? '—',
            ))
            ->danger()
            ->send();
    }
}
