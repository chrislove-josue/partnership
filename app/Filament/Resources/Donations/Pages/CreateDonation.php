<?php

namespace App\Filament\Resources\Donations\Pages;

use App\Filament\Resources\Donations\DonationResource;
use App\Mail\DonationConfirmationMail;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CreateDonation extends CreateRecord
{
    protected static string $resource = DonationResource::class;

    protected function afterCreate(): void
    {
        $donation = $this->record->load(['partner', 'church', 'category']);

        if (! $donation->partner?->email) {
            return;
        }

        try {
            Mail::to($donation->partner->email)->send(new DonationConfirmationMail($donation));
        } catch (\Throwable $e) {
            Log::warning('Échec envoi confirmation de don : '.$e->getMessage());
        }
    }
}
