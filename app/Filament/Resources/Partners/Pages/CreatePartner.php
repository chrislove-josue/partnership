<?php

namespace App\Filament\Resources\Partners\Pages;

use App\Filament\Resources\Concerns\RedirectsToListAfterSave;
use App\Filament\Resources\Partners\PartnerResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePartner extends CreateRecord
{
    use RedirectsToListAfterSave;

    protected static string $resource = PartnerResource::class;
}
