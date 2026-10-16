<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryDon extends Model
{
    protected $fillable = [
        'name',
        'color',
    ];

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class, 'category_id');
    }
}
