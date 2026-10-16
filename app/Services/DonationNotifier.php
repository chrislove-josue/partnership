<?php

namespace App\Services;

use App\Mail\DonationConfirmationMail;
use App\Models\Donation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DonationNotifier
{
    public function sendConfirmation(Donation $donation): bool
    {
        $donation->loadMissing(['partner', 'church', 'category']);

        $email = $donation->partner?->email;

        if (! $email) {
            return false;
        }

        try {
            Mail::to($email)->send(new DonationConfirmationMail($donation));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Échec envoi confirmation de don : '.$e->getMessage());

            return false;
        }
    }
}
