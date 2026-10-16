<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userEmail,
        public string $tempPassword,
        public string $adminName,
        public string $magicLink,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bienvenue sur Suivi des Partenariats',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user-welcome',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
