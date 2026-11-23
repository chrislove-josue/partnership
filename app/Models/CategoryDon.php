<?php

namespace App\Models;

use App\Enums\CategoryGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryDon extends Model
{
    protected $fillable = [
        'name',
        'color',
        'group',
    ];

    protected function casts(): array
    {
        return [
            'group' => CategoryGroup::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $category): void {
            if (blank($category->group)) {
                $category->group = CategoryGroup::fromName((string) $category->name);
            }
        });
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class, 'category_id');
    }
}
