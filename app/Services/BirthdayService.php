<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Partner;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Alertes d'anniversaire des partenaires : J-7, J-3 et le jour J, envoyées à
 * toute la hiérarchie (staff de l'église du partenaire jusqu'au super admin),
 * plus la liste mensuelle des anniversaires.
 */
class BirthdayService
{
    /** Délais d'alerte, en jours avant l'anniversaire. */
    public const array DELAYS = [7, 3, 0];

    public function __construct(private NotificationService $notifications)
    {
    }

    /**
     * Envoie les alertes dues aujourd'hui (J-7, J-3, jour J).
     * Idempotent : une alerte déjà créée aujourd'hui pour le même partenaire
     * et le même délai n'est pas recréée.
     *
     * @return int Nombre d'alertes créées
     */
    public function notifyUpcoming(): int
    {
        $today = Carbon::today();
        $created = 0;

        foreach (self::DELAYS as $delay) {
            $target = $today->copy()->addDays($delay);

            foreach ($this->partnersBirthdayOn($target) as $partner) {
                if ($this->alreadyNotifiedToday($partner->id, $delay, $today)) {
                    continue;
                }

                if ($this->sendAlert($partner, $delay, $target)) {
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * Liste des anniversaires d'un mois, triée par jour.
     *
     * @param  array<int, int>|null  $churchIds  Églises autorisées (null = toutes)
     * @return Collection<int, array{day: int, date: string, name: string, age: int|null, church: string|null, cellule: string|null, phone: string|null, email: string|null, is_user: bool}>
     */
    public function monthBirthdays(int $month, ?int $year = null, ?array $churchIds = null): Collection
    {
        $year = $year ?? Carbon::today()->year;

        $query = Partner::query()
            ->with(['church:id,name', 'cellule:id,name'])
            ->whereNotNull('birthdate')
            ->whereMonth('birthdate', $month);

        if ($churchIds !== null) {
            $query->whereIn('church_id', $churchIds);
        }

        return $query->get()
            ->map(fn (Partner $partner): array => [
                'day' => $this->effectiveDay($partner->birthdate, $month, $year),
                'date' => sprintf('%04d-%02d-%02d', $year, $month, $this->effectiveDay($partner->birthdate, $month, $year)),
                'name' => $partner->full_name,
                'age' => $this->ageAt($partner->birthdate, $year),
                'church' => $partner->church?->name,
                'cellule' => $partner->cellule?->name,
                'phone' => $partner->phone,
                'email' => $partner->email,
                'is_user' => $partner->isUserAccount(),
            ])
            ->sortBy([['day', 'asc'], ['name', 'asc']])
            ->values();
    }

    /**
     * Partenaires dont l'anniversaire tombe sur la date donnée.
     * Un 29 février est fêté le 28 février les années non bissextiles.
     *
     * @return EloquentCollection<int, Partner>
     */
    private function partnersBirthdayOn(Carbon $date): EloquentCollection
    {
        $query = Partner::query()
            ->with(['church:id,name,manager_id,parent_id'])
            ->whereNotNull('birthdate')
            ->whereMonth('birthdate', $date->month)
            ->whereDay('birthdate', $date->day);

        if ($date->month === 2 && $date->day === 28 && ! $date->isLeapYear()) {
            $query = Partner::query()
                ->with(['church:id,name,manager_id,parent_id'])
                ->whereNotNull('birthdate')
                ->whereMonth('birthdate', 2)
                ->where(function ($builder): void {
                    $builder->whereDay('birthdate', 28)
                        ->orWhereDay('birthdate', 29);
                });
        }

        return $query->get();
    }

    private function alreadyNotifiedToday(int $partnerId, int $delay, Carbon $today): bool
    {
        return Notification::query()
            ->where('type', NotificationType::BIRTHDAY->value)
            ->where('created_at', '>=', $today->copy()->startOfDay())
            ->where('data->partnerId', $partnerId)
            ->where('data->delay', $delay)
            ->exists();
    }

    private function sendAlert(Partner $partner, int $delay, Carbon $target): bool
    {
        $church = $partner->church;

        if (! $church) {
            return false;
        }

        $userIds = $this->notifications->collectStaffUserIds($church);

        if ($userIds === []) {
            return false;
        }

        $age = $this->ageAt($partner->birthdate, $target->year);
        $dateLabel = $target->format('d/m/Y');

        [$title, $message] = match ($delay) {
            7 => [
                '🎂 Anniversaire dans 7 jours',
                sprintf('%s fêtera %s le %s (%s).', $partner->full_name, $this->ageLabel($age), $dateLabel, $church->name),
            ],
            3 => [
                '🎂 Anniversaire dans 3 jours',
                sprintf('%s fêtera %s le %s (%s).', $partner->full_name, $this->ageLabel($age), $dateLabel, $church->name),
            ],
            default => [
                '🎉 Anniversaire aujourd’hui !',
                sprintf('%s fête %s aujourd’hui (%s) !', $partner->full_name, $this->ageLabel($age), $church->name),
            ],
        };

        $this->notifications->createNotifications(
            NotificationType::BIRTHDAY,
            $title,
            $message,
            [
                'partnerId' => $partner->id,
                'partnerName' => $partner->full_name,
                'churchId' => $church->id,
                'churchName' => $church->name,
                'delay' => $delay,
                'birthdayDate' => $target->toDateString(),
                'age' => $age,
            ],
            $userIds,
        );

        return true;
    }

    /** Jour du mois à afficher : le 29 février devient 28 en année non bissextile. */
    private function effectiveDay(Carbon $birthdate, int $month, int $year): int
    {
        if ($birthdate->month === 2 && $birthdate->day === 29 && ! Carbon::create($year, 2, 1)->isLeapYear()) {
            return 28;
        }

        return $birthdate->day;
    }

    /** Âge accompli à l'anniversaire de l'année donnée, ou null si inconnu. */
    private function ageAt(?Carbon $birthdate, int $year): ?int
    {
        if (! $birthdate) {
            return null;
        }

        // Âge accompli au jour de l'anniversaire dans l'année affichée.
        $age = $year - $birthdate->year;

        return $age < 0 ? null : $age;
    }

    private function ageLabel(?int $age): string
    {
        return $age !== null ? $age.' ans' : 'ses anniversaires';
    }
}
