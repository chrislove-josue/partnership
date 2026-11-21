<?php

namespace App\Services\Import;

use App\Enums\ChurchType;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Partner;
use App\Models\User;
use App\Services\ChurchAccessService;
use App\Support\DonationCategoryNames;
use RuntimeException;

/** Références partagées par les imports partenaires et dons. */
abstract class BaseImportService
{
    public function __construct(
        protected readonly ImportHelpers $helpers,
        protected readonly ChurchAccessService $access,
    ) {}

    /**
     * Recherche insensible à la casse, avec auto-création d'une église locale.
     * L'auto-création reste limitée à une église du périmètre de l'acteur.
     */
    protected function resolveChurch(string $name, User $actor): Church
    {
        $name = trim($name);
        $church = Church::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        if ($church) {
            return $church;
        }

        if (! $this->access->canCreateChurch($actor, ChurchType::LOCAL)) {
            throw new RuntimeException('Création automatique d’église non autorisée.');
        }

        $parent = $this->access->accessibleChurches($actor)->first(
            fn (Church $candidate): bool => in_array($candidate->type, [ChurchType::GROUP, ChurchType::SUB_GROUP], true),
        );

        if (! $parent) {
            throw new RuntimeException('Aucune église parente accessible pour la création automatique.');
        }

        return Church::create([
            'name' => $name,
            'type' => ChurchType::LOCAL,
            'parent_id' => $parent->id,
        ]);
    }

    /** Recherche insensible à la casse, avec auto-création (couleur #1F4E78). */
    protected function resolveCategory(string $name): CategoryDon
    {
        $name = DonationCategoryNames::normalize($name);
        $category = CategoryDon::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        return $category ?? CategoryDon::create([
            'name' => $name,
            'color' => '#1F4E78',
        ]);
    }

    /**
     * Partenaire existant reconnu par email, téléphone OU handle KingsChat,
     * uniquement parmi les églises accessibles à l'acteur.
     *
     * @param  array<string, mixed>  $row
     */
    protected function findPartner(User $actor, array $row, ?string $phone = null, ?string $handleKC = null): ?Partner
    {
        $conditions = [];

        if (! empty($row['email'])) {
            $conditions[] = ['column' => 'email', 'value' => mb_strtolower(trim((string) $row['email']))];
        }
        if ($phone) {
            $conditions[] = ['column' => 'phone', 'value' => mb_strtolower($phone)];
        }
        if ($handleKC) {
            $conditions[] = ['column' => 'handle_kc', 'value' => mb_strtolower($handleKC)];
        }

        if ($conditions === []) {
            return null;
        }

        return Partner::query()
            ->whereIn('church_id', $this->access->accessibleChurchIds($actor))
            ->where(function ($query) use ($conditions): void {
                foreach ($conditions as $condition) {
                    $query->orWhereRaw("LOWER({$condition['column']}) = ?", [$condition['value']]);
                }
            })
            ->first();
    }

    protected function assertCanModify(User $actor, Church $church): void
    {
        if (! $this->access->canModifyChurchData($actor, $church->id)) {
            throw new RuntimeException('Permission refusée pour l\'église '.$church->name);
        }
    }

    protected function assertCanModifyPartner(User $actor, Partner $partner): void
    {
        if (! $this->access->canModifyChurchData($actor, $partner->church_id)) {
            throw new RuntimeException('Permission refusée pour modifier ce partenaire.');
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function text(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        return ($value === null || $value === '') ? null : trim((string) $value);
    }
}
