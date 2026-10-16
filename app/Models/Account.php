<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Account extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'provider_account_id',
        'type',
        'refresh_token',
        'access_token',
        'expires_at',
        'token_type',
        'scope',
        'id_token',
        'session_state',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
