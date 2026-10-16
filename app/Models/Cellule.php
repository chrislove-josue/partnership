<?php

namespace App\Models;

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
}
