<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginHistory extends Model
{
    use HasFactory;

    public const EVENT_LOGIN = 'login';

    public const EVENT_LOGOUT = 'logout';

    public const SOURCE_WEB = 'web';

    public const SOURCE_MOBILE = 'mobile';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event',
        'source',
        'ip_address',
        'user_agent',
        'reference',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventLabel(): string
    {
        return $this->event === self::EVENT_LOGOUT ? 'Déconnexion' : 'Connexion';
    }

    public function sourceLabel(): string
    {
        return $this->source === self::SOURCE_MOBILE ? 'Application mobile' : 'Site web';
    }
}
