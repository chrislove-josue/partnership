<?php

namespace App\Filament\Resources\Donations\Pages;

use App\Filament\Resources\Donations\DonationResource;
use App\Services\DonationNotifier;
use Filament\Resources\Pages\CreateRecord;

class CreateDonation extends CreateRecord
{
    protected static string $resource = DonationResource::class;

    protected function afterCreate(): void
    {
        app(DonationNotifier::class)->handle($this->record);
    }
}
