<?php

namespace App\Models;

use App\Enums\ChurchType;
use App\Services\ChurchDescendantService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Church extends Model
{
    protected $fillable = [
        'name',
        'type',
        'parent_id',
        'manager_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ChurchType::class,
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Church::class, 'parent_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function agents(): HasMany
    {
        return $this->hasMany(User::class, 'assigned_church_id');
    }

    public function cellules(): HasMany
    {
        return $this->hasMany(Cellule::class);
    }

    public function partners(): HasMany
    {
        return $this->hasMany(Partner::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    /**
     * `total_donations` : une église locale affiche ses dons directs ; un
     * groupe ou sous-groupe agrège ceux de toute sa descendance.
     */
    public function scopeWithTotalDonations(Builder $query): Builder
    {
        // Sous-requête : pour chaque don, trouver l'église "réceptrice" (le
        // parent GROUP/SUB_GROUP le plus proche si l'église créditée est une
        // locale, sinon l'église elle-même), puis additionner.
        $localIds = Church::query()
            ->where('type', ChurchType::LOCAL)
            ->pluck('id')
            ->all();

        $totals = [];

        // IMPORTANT : utiliser un builder séparé (newQuery) pour ne pas écraser
        // le select de la requête principale, sinon Filament perd la clé id.
        foreach (Church::query()->newQuery()->select('id', 'type')->get() as $item) {
            $ids = $item->type === ChurchType::LOCAL
                ? [(int) $item->id]
                : app(ChurchDescendantService::class)->forChurch((int) $item->id);

            $totals[(int) $item->id] = (float) Donation::query()
                ->whereIn('church_id', $ids)
                ->where('is_active', true)
                ->sum('amount');
        }

        $cases = '';
        foreach ($totals as $id => $total) {
            $cases .= 'WHEN '.(int) $id.' THEN '.(float) $total.' ';
        }
        $expression = 'CASE churches.id '.$cases.'ELSE 0 END';

        return $query
            ->select('churches.*')
            ->selectRaw($expression.' as total_donations');
    }

    /**
     * Retourne tous les identifiants des églises descendantes (récursif).
     *
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        $ids = [];

        foreach ($this->children()->get(['id']) as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->descendantIds());
        }

        return $ids;
    }

    /**
     * Retourne tous les identifiants des églises ascendantes (récursif).
     *
     * @return array<int, int>
     */
    public function ancestorIds(): array
    {
        $ids = [];
        $current = $this->parent;

        while ($current) {
            $ids[] = $current->id;
            $current = $current->parent;
        }

        return $ids;
    }
}
