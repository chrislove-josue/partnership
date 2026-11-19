<?php

namespace App\Mail;

use App\Models\User;
use App\Services\PartnershipReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Rapport de partenariat envoyé à la hiérarchie, avec le classeur Excel joint. */
class PartnershipReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $report
     * @param  array<int, array{email: string, name: string}>  $recipients
     */
    public function __construct(
        public array $report,
        public array $recipients,
        public User $sender,
        public string $fileName,
        public string $xlsx,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Partenariat] '.$this->report['church']['name'].' — Rapport '.$this->report['period']['label'],
        );
    }

    public function content(): Content
    {
        $format = fn (float|int $amount): string => app(PartnershipReportService::class)->formatAmount($amount);

        return new Content(
            view: 'emails.partnership-report',
            with: [
                'churchName' => $this->report['church']['name'],
                'periodLabel' => $this->report['period']['label'],
                'totals' => $this->report['totals'],
                'categories' => $this->report['categories'],
                'senderName' => $this->sender->name ?: $this->sender->email,
                'format' => $format,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->xlsx, $this->fileName)
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }
}
