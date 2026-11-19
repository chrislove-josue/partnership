<?php

namespace App\Services;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;

class LoginHistoryService
{
    public function record(
        User $user,
        string $event,
        string $source,
        ?Request $request = null,
        ?string $reference = null,
    ): LoginHistory {
        $request ??= request();

        return $user->loginHistories()->create([
            'event' => $event,
            'source' => $source,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent()
                ? mb_substr($request->userAgent(), 0, 1000)
                : null,
            'reference' => $reference,
            'created_at' => now(),
        ]);
    }
}
