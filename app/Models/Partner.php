<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    protected $fillable = [
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
}
