<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\User;

/**
 * Recopie chaque compte utilisateur (pasteur, personnel, administrateur) dans la
 * liste des partenaires de l'église qui lui est affectée.
 *
 * Le compte reste la source de vérité : le partenaire en est le miroir, afin que
 * les pasteurs et le personnel apparaissent dans les listes de partenaires et
 * puissent recevoir des dons comme n'importe quel autre partenaire.
 */
class UserPartnerSync
{
    /** Champs du compte dont la modification déclenche une mise à jour. */
    public const SYNCED_FIELDS = ['name', 'email', 'phone', 'handle_kc', 'assigned_church_id'];

    /**
     * Crée ou met à jour le partenaire lié au compte.
     * Retourne null lorsque le compte n'est affecté à aucune église.
     */
    public function sync(User $user): ?Partner
    {
        if (! $user->assigned_church_id) {
            return null;
        }

        $partner = Partner::query()->where('user_id', $user->id)->first()
            ?? $this->findUnlinkedPartner($user)
            ?? new Partner();

        $partner->fill($this->attributesFor($user, $partner->id));
        $partner->user_id = $user->id;
        $partner->save();

        return $partner;
    }

    /**
     * Cherche un partenaire déjà enregistré (import, saisie manuelle) portant
     * les mêmes coordonnées, pour le rattacher au compte au lieu de le dupliquer.
     */
    private function findUnlinkedPartner(User $user): ?Partner
    {
        foreach (['email' => $user->email, 'phone' => $user->phone, 'handle_kc' => $user->handle_kc] as $column => $value) {
            $value = $this->clean($value);

            if ($value === null) {
                continue;
            }

            $partner = Partner::query()
                ->whereNull('user_id')
                ->where($column, $value)
                ->first();

            if ($partner) {
                return $partner;
            }
        }

        return null;
    }

    /**
     * Attributs du partenaire recopiés depuis le compte. Une valeur déjà utilisée
     * par un autre partenaire est ignorée pour ne pas violer les index uniques.
     *
     * @return array<string, string|int|null>
     */
    private function attributesFor(User $user, ?int $partnerId): array
    {
        [$firstName, $lastName] = $this->splitName((string) $user->name);

        $attributes = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'church_id' => $user->assigned_church_id,
        ];

        foreach (['email', 'phone', 'handle_kc'] as $field) {
            $value = $this->clean($user->{$field});

            // Ne pas effacer une coordonnée saisie directement sur le partenaire
            // lorsque le compte ne renseigne pas le champ.
            if ($value === null || $this->isTaken($field, $value, $partnerId)) {
                continue;
            }

            $attributes[$field] = $value;
        }

        return $attributes;
    }

    private function isTaken(string $column, string $value, ?int $partnerId): bool
    {
        return Partner::query()
            ->where($column, $value)
            ->when($partnerId !== null, fn ($query) => $query->whereKeyNot($partnerId))
            ->exists();
    }

    private function clean(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Le compte ne stocke qu'un nom complet : le dernier mot devient le nom,
     * le reste le prénom. Un nom d'un seul mot reste le nom de famille.
     *
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        if ($name === '') {
            return ['', ''];
        }

        $parts = explode(' ', $name);

        if (count($parts) === 1) {
            return ['', $parts[0]];
        }

        $lastName = (string) array_pop($parts);

        return [implode(' ', $parts), $lastName];
    }
}
