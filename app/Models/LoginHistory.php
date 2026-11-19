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

    /** Libellé court de l’appareil, affiché dans l’historique. */
    public function deviceLabel(): string
    {
        $agent = $this->user_agent;

        if (! $agent) {
            return '—';
        }

        if (str_contains($agent, 'okhttp')) {
            return 'Application mobile';
        }

        $platform = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Appareil inconnu',
        };

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            str_contains($agent, 'PowerShell') => 'PowerShell',
            default => null,
        };

        return $browser ? $platform.' · '.$browser : $platform;
    }
}
