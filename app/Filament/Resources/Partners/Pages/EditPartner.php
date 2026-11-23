<?php

namespace App\Filament\Resources\Partners\Pages;

use App\Filament\Resources\Concerns\RedirectsToListAfterSave;
use App\Filament\Resources\Partners\Actions\PartnerDeleteGuard;
use App\Filament\Resources\Partners\PartnerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPartner extends EditRecord
{
    use RedirectsToListAfterSave;

    protected static string $resource = PartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(fn (DeleteAction $action) => PartnerDeleteGuard::beforeDelete($action)),
        ];
    }
}
