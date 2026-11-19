<?php

namespace App\Services;

use App\Enums\PeriodType;
use App\Mail\PartnershipReportMail;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Port de POST /api/reports/partnership/send.
 *
 * Aucun envoi automatique : sans confirmation, on renvoie uniquement la liste
 * des destinataires pour validation (sent:false, confirmRequired:true).
 */
class PartnershipReportSender
{
    public function __construct(
        private readonly PartnershipReportService $reports,
        private readonly PartnershipReportExporter $exporter,
        private readonly ChurchAccessService $access,
    ) {}

    /**
     * Aperçu des destinataires et des totaux, sans rien envoyer.
     *
     * @return array<string, mixed>
     */
    public function preview(User $user, int $churchId, PeriodType $type, int $year, ?int $index): array
    {
        $this->assertAccess($user, $churchId);

        $resolved = $this->reports->resolveRecipients($user, $churchId);

        if ($resolved['recipients'] === []) {
            throw new RuntimeException('Aucun destinataire trouvé dans la hiérarchie.');
        }

        $report = $this->reports->buildReport($churchId, $type, $year, $index);

        return [
            'sent' => false,
            'confirmRequired' => true,
            'recipients' => $resolved['recipients'],
            'cc' => $resolved['cc'] ?? [],
            'targetLabel' => $resolved['targetLabel'],
            'chain' => $resolved['chain'],
            'church' => $report['church'],
            'period' => $report['period'],
            'totals' => $report['totals'],
            'message' => "Envoi NON effectué — confirmez l'envoi en re-postant cette requête avec `confirm: true`.",
        ];
    }

    /**
     * Envoie le rapport (uniquement si $confirm vaut true).
     *
     * @param  array<int, string> | null  $ccEmails  Copies cochées par l'utilisateur ; null conserve toutes les copies proposées.
     * @return array<string, mixed>
     */
    public function send(User $user, int $churchId, PeriodType $type, int $year, ?int $index, bool $confirm, ?array $ccEmails = null): array
    {
        if (! $confirm) {
            return $this->preview($user, $churchId, $type, $year, $index);
        }

        $preview = $this->preview($user, $churchId, $type, $year, $index);
        $cc = $preview['cc'];

        if ($ccEmails !== null) {
            $cc = array_values(array_filter(
                $cc,
                fn (array $recipient): bool => in_array($recipient['email'], $ccEmails, true),
            ));
        }

        $report = $this->reports->buildReport($churchId, $type, $year, $index);
        $xlsx = $this->exporter->toXlsx($report);
        $fileName = $this->reports->fileName($report);

        $mail = Mail::to(array_column($preview['recipients'], 'email'));

        if ($cc !== []) {
            $mail->cc(array_column($cc, 'email'));
        }

        $mail->send(new PartnershipReportMail($report, $preview['recipients'], $user, $fileName, $xlsx));

        Log::info('Rapport de partenariat envoyé : '.$report['church']['name'].' ('.$report['period']['label'].') → '.implode(', ', array_column($preview['recipients'], 'email'))
            .($cc !== [] ? ' (copie : '.implode(', ', array_column($cc, 'email')).')' : ''));

        return [
            'sent' => true,
            'to' => $preview['recipients'],
            'cc' => $cc,
            'targetLabel' => $preview['targetLabel'],
            'fileName' => $fileName,
            'report' => $report['totals'],
        ];
    }

    private function assertAccess(User $user, int $churchId): void
    {
        if (! $this->access->canAccessChurchData($user, $churchId)) {
            throw new AuthorizationException('Accès refusé à cette église');
        }
    }
}
