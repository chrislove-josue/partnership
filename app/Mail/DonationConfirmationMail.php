<?php

namespace App\Mail;

use App\Models\Donation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DonationConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public const GROUP_PASTOR = 'Nicolas YAO ADJAKA';

    public function __construct(public Donation $donation) {}

    public function envelope(): Envelope
    {
        $amount = number_format((float) $this->donation->amount, 0, ',', ' ');

        return new Envelope(
            subject: 'Confirmation de don - '.$amount.' FCFA',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.donation-confirmation',
            with: [
                'partner' => $this->donation->partner,
                'church' => $this->donation->church,
                'category' => $this->donation->category,
                'amount' => (float) $this->donation->amount,
                'date' => $this->donation->date,
                'groupPastor' => 'Nicolas YAO ADJAKA',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
