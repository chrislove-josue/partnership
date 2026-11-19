<?php

namespace App\Services\Import;

use App\Enums\ChurchType;
use App\Models\CategoryDon;
use App\Models\Church;
use App\Models\Partner;
use App\Models\User;
use App\Services\ChurchAccessService;
use RuntimeException;

/** Références partagées par les imports partenaires et dons. */
abstract class BaseImportService
{
    public function __construct(
        protected readonly ImportHelpers $helpers,
        protected readonly ChurchAccessService $access,
    ) {}

    /** Recherche insensible à la casse, avec auto-création d'une église locale. */
    protected function resolveChurch(string $name): Church
    {
        $name = trim($name);
        $church = Church::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        if ($church) {
            return $church;
        }

        $group = Church::where('type', ChurchType::GROUP)->first();

        return Church::create([
            'name' => $name,
            'type' => ChurchType::LOCAL,
            'parent_id' => $group?->id,
        ]);
    }

    /** Recherche insensible à la casse, avec auto-création (couleur #1F4E78). */
    protected function resolveCategory(string $name): CategoryDon
    {
        $name = trim($name);
        $category = CategoryDon::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        return $category ?? CategoryDon::create([
            'name' => $name,
            'color' => '#1F4E78',
        ]);
    }

    /**
     * Partenaire existant reconnu par email, téléphone OU handle KingsChat.
     *
     * @param  array<string, mixed>  $row
     */
    protected function findPartner(array $row, ?string $phone = null, ?string $handleKC = null): ?Partner
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

    /**
     * @param  array<string, mixed>  $row
     */
    protected function text(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        return ($value === null || $value === '') ? null : trim((string) $value);
    }
}
