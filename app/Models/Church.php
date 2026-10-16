<?php

namespace App\Models;

use App\Enums\ChurchType;
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
    ];

    protected function casts(): array
    {
        return [
            'type' => ChurchType::class,
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
