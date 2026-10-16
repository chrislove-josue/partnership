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
        'category_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'datetime',
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

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function scopeForChurches(Builder $query, array $churchIds): Builder
    {
        return $query->whereIn('church_id', $churchIds);
    }
}
