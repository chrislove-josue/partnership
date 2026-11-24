<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'handle_kc',
        'address',
        'birthdate',
        'cellule_id',
        'church_id',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    /** Compte utilisateur dont ce partenaire est le miroir, s'il existe. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUserAccount(): bool
    {
        return $this->user_id !== null;
    }

    public function cellule(): BelongsTo
    {
        return $this->belongsTo(Cellule::class);
    }

    public function leadsCellule(): HasMany
    {
        return $this->hasMany(Cellule::class, 'leader_id');
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    /**
     * Ajoute un attribut calculé `total_donations` = somme des dons du partenaire.
     * Si des églises sont fournies, seuls les dons crédités à ces églises
     * sont comptés (un visiteur ne doit pas révéler l'historique d'ailleurs).
     *
     * @param  array<int, int> | null  $churchIds
     */
    public function scopeWithTotalDonations(Builder $query, ?array $churchIds = null): Builder
    {
        $donations = Donation::query()
            ->selectRaw('COALESCE(SUM(donations.amount), 0)')
            ->whereColumn('donations.partner_id', 'partners.id');

        if ($churchIds !== null) {
            $donations->whereIn('donations.church_id', $churchIds);
        }

        return $query->addSelect(['total_donations' => $donations]);
    }
}
