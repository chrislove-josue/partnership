<?php

namespace App\Filament\Resources\Churches\Pages;

use App\Filament\Resources\Churches\Actions\ChurchDeleteGuard;
use App\Filament\Resources\Churches\Actions\ChurchStatusActions;
use App\Filament\Resources\Churches\ChurchResource;
use App\Filament\Resources\Concerns\RedirectsToListAfterSave;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditChurch extends EditRecord
{
    use RedirectsToListAfterSave;

    protected static string $resource = ChurchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ChurchStatusActions::deactivate(),
            ChurchStatusActions::activate(),
            DeleteAction::make()
                ->before(fn (DeleteAction $action) => ChurchDeleteGuard::beforeDelete($action)),
        ];
    }
}
