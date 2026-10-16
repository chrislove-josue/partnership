<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MagicToken extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'token',
        'email',
        'expires_at',
        'used',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used' => 'boolean',
        ];
    }
}
