<?php

namespace App\Filament\Resources\Concerns;

/** Ramène à la liste après une création simple ou une mise à jour. */
trait RedirectsToListAfterSave
{
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
