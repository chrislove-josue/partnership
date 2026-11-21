<?php

namespace App\Services;

use App\Models\MagicToken;

class MagicTokenService
{
    /** Les liens magiques expirent rapidement pour limiter la fenêtre d'abus. */
    public const TTL_MINUTES = 30;

    public function generate(string $email, ?string $baseUrl = null): string
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);

        MagicToken::query()->where('email', $email)->delete();

        MagicToken::create([
            'token' => $hash,
            'email' => $email,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'used' => false,
        ]);

        $base = $baseUrl ?: config('app.url');

        return rtrim($base, '/').'/magic?token='.$token;
    }

    public function consume(string $plainToken): ?MagicToken
    {
        $hash = hash('sha256', $plainToken);

        $magic = MagicToken::query()
            ->where('token', $hash)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (! $magic) {
            return null;
        }

        $magic->update(['used' => true]);

        return $magic;
    }
}
