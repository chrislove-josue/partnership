<?php

namespace Tests\Feature;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TempLoginCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_compte_les_evenements(): void
    {
        $user = User::create([
            'name' => 'Test doublon',
            'email' => 'doublon@example.test',
            'password' => bcrypt('azerty123'),
            'is_active' => true,
        ]);

        dump([
            'listeners_login' => collect(Event::getListeners(Login::class))
                ->map(function ($listener): array {
                    if ($listener instanceof \Closure) {
                        $reflection = new \ReflectionFunction($listener);

                        return [
                            'type' => 'Closure',
                            'file' => $reflection->getFileName(),
                            'line' => $reflection->getStartLine(),
                        ];
                    }

                    return ['type' => is_string($listener) ? $listener : get_class($listener)];
                })
                ->all(),
        ]);

        Auth::login($user);

        dump([
            'login_count' => LoginHistory::where('user_id', $user->id)
                ->where('event', LoginHistory::EVENT_LOGIN)
                ->count(),
        ]);

        Auth::logout();

        dump([
            'logout_count' => LoginHistory::where('user_id', $user->id)
                ->where('event', LoginHistory::EVENT_LOGOUT)
                ->count(),
        ]);

        $this->assertTrue(true);
    }
}
