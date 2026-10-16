<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cellule extends Model
{
    protected $fillable = [
        'name',
        'church_id',
        'leader_id',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'leader_id');
    }

    public function partners(): HasMany
    {
        return $this->hasMany(Partner::class);
    }

    /**
     * Ajoute un attribut calculé `total_donations` = somme des dons des
     * partenaires membres de la cellule ou dont elle est le leader.
     */
    public function scopeWithTotalDonations(Builder $query): Builder
    {
        return $query->addSelect([
            'total_donations' => Donation::query()
                ->selectRaw('COALESCE(SUM(donations.amount), 0)')
                ->join('partners', 'partners.id', '=', 'donations.partner_id')
                ->where(function (Builder $q) {
                    $q->whereColumn('partners.cellule_id', 'cellules.id')
                        ->orWhereColumn('partners.id', 'cellules.leader_id');
                }),
        ]);
    }
}
