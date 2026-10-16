<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\RoleName;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Port de lib/notificationService.ts — création des notifications dashboard.
 */
class NotificationService
{
    /**
     * @param  array<int, int|string>  $userIds
     * @param  array<string, mixed>  $data
     */
    public function createNotifications(
        NotificationType $type,
        string $title,
        string $message,
        array $data,
        array $userIds,
    ): void {
        $userIds = array_values(array_unique($userIds));

        if ($userIds === []) {
            return;
        }

        // Ne conserver que des utilisateurs réellement existants : une clé
        // étrangère invalide ferait échouer l'insertion en bloc et on perdrait
        // toutes les notifications.
        $existing = User::query()
            ->whereIn('id', $userIds)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $userIds = array_values(array_intersect($userIds, $existing));

        if ($userIds === []) {
            return;
        }

        $now = now();

        $rows = array_map(fn ($userId) => [
            'id' => (string) Str::uuid(),
            'type' => $type->value,
            'title' => $title,
            'message' => $message,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'is_read' => false,
            'user_id' => $userId,
            'created_at' => $now,
        ], $userIds);

        try {
            Notification::query()->insert($rows);
        } catch (\Throwable $e) {
            // Repli ligne par ligne : on sauvegarde au moins celles qui passent.
            foreach ($rows as $row) {
                try {
                    Notification::query()->insert([$row]);
                } catch (\Throwable) {
                    Log::warning('Impossible de sauvegarder la notification pour l\'utilisateur '.$row['user_id'].' : '.$e->getMessage());
                }
            }
        }
    }

    /**
     * Rassemble les utilisateurs à notifier pour une église donnée :
     * super admins + responsables remontant la hiérarchie + agents actifs de l'église.
     *
     * @return array<int, int>
     */
    public function collectStaffUserIds(Church $church): array
    {
        $userIds = [];

        $superAdmins = User::query()
            ->where('is_active', true)
            ->role(RoleName::SUPER_ADMIN->value)
            ->pluck('id')
            ->all();

        $userIds = array_merge($userIds, $superAdmins);

        $current = $church;

        while ($current) {
            if ($current->manager_id) {
                $userIds[] = $current->manager_id;
            }

            if ($current->id === $church->id) {
                $agents = $current->agents()
                    ->where('is_active', true)
                    ->pluck('id')
                    ->all();

                $userIds = array_merge($userIds, $agents);
            }

            $current = $current->parent;
        }

        return array_values(array_unique($userIds));
    }

    public function notifyNewDonation(Donation $donation): void
    {
        $donation->loadMissing(['partner', 'church', 'category']);

        $partner = $donation->partner;
        $church = $donation->church;
        $category = $donation->category;

        if (! $church) {
            return;
        }

        $amountFormatted = number_format((float) $donation->amount, 0, ',', ' ');
        $partnerFullName = $partner?->full_name ?? 'Un partenaire';

        $donorUserId = $partner?->email
            ? User::query()->where('email', $partner->email)->value('id')
            : null;

        $staffIds = $this->collectStaffUserIds($church);
        $staffIds = array_values(array_filter($staffIds, fn ($id) => $id !== $donorUserId));

        if ($staffIds !== []) {
            $this->createNotifications(
                NotificationType::NEW_DONATION,
                '💰 Nouveau don enregistré',
                sprintf(
                    '%s a effectué un don de %s FCFA (%s) — %s',
                    $partnerFullName,
                    $amountFormatted,
                    $category?->name ?? '',
                    $church->name,
                ),
                [
                    'donationId' => $donation->id,
                    'amount' => (float) $donation->amount,
                    'partnerName' => $partnerFullName,
                    'partnerHandleKC' => $partner?->handle_kc,
                    'churchName' => $church->name,
                    'categoryName' => $category?->name,
                    'isForDonor' => false,
                ],
                $staffIds,
            );
        }

        if ($donorUserId) {
            $this->createNotifications(
                NotificationType::NEW_DONATION,
                '✅ Votre don a été enregistré',
                sprintf(
                    'Votre don de %s FCFA (%s) pour %s a bien été reçu. Merci pour votre générosité !',
                    $amountFormatted,
                    $category?->name ?? '',
                    $church->name,
                ),
                [
                    'donationId' => $donation->id,
                    'amount' => (float) $donation->amount,
                    'categoryName' => $category?->name,
                    'churchName' => $church->name,
                    'isForDonor' => true,
                ],
                [$donorUserId],
            );
        }
    }
}
