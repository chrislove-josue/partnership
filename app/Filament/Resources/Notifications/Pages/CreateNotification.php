<?php

namespace App\Filament\Resources\Notifications\Pages;

use App\Filament\Resources\Concerns\RedirectsToListAfterSave;
use App\Filament\Resources\Notifications\NotificationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNotification extends CreateRecord
{
    use RedirectsToListAfterSave;

    protected static string $resource = NotificationResource::class;
}
