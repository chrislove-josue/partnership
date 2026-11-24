<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Donation extends Model
{
    protected $fillable = [
        'amount',
        'date',
        'partner_id',
        'church_id',
        'worship_church_id',
        'category_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryDon::class, 'category_id');
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    /** Église où le culte a eu lieu (peut différer de l'église créditée). */
    public function worshipChurch(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'worship_church_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('donations.is_active', true);
    }

    public function scopeForChurches(Builder $query, array $churchIds): Builder
    {
        return $query->whereIn('church_id', $churchIds);
    }
}
