<?php

namespace App\Models;

use App\Enums\ChurchType;
use App\Services\ChurchDescendantService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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
        $totals = [];

        foreach (Church::query()->get(['id', 'type']) as $church) {
            $ids = $church->type === ChurchType::LOCAL
                ? [(int) $church->id]
                : app(ChurchDescendantService::class)->forChurch((int) $church->id);

            $totals[(int) $church->id] = (float) Donation::query()
                ->whereIn('church_id', $ids)
                ->where('is_active', true)
                ->sum('amount');
        }

        $sql = 'CASE churches.id ';
        foreach ($totals as $id => $total) {
            $sql .= 'WHEN '.(int) $id.' THEN '.(float) $total.' ';
        }
        $sql .= 'ELSE 0 END';

        return $query->addSelect([
            'total_donations' => DB::raw($sql.' as total_donations'),
        ]);
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
