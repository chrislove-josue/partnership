<?php

namespace App\Services;

use App\Mail\DonationConfirmationMail;
use App\Models\Donation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DonationNotifier
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Traite un nouveau don : email de confirmation au partenaire + notifications dashboard.
     */
    public function handle(Donation $donation): void
    {
        $this->sendConfirmation($donation);

        try {
            $this->notifications->notifyNewDonation($donation);
        } catch (\Throwable $e) {
            Log::warning('Échec création des notifications de don : '.$e->getMessage());
        }
    }

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
