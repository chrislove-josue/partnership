<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class RateLimitingSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const IP = '127.0.0.1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->clearLimiters('rate-limit@example.test');
    }

    protected function tearDown(): void
    {
        $this->clearLimiters('rate-limit@example.test');

        parent::tearDown();
    }

    private function clearLimiters(string $email): void
    {
        RateLimiter::clear(md5('login'.mb_strtolower($email).'|'.self::IP));
        RateLimiter::clear('web-login:'.sha1(self::IP.'|'.mb_strtolower($email)));
        RateLimiter::clear(md5('magic'.self::IP));
    }

    public function test_api_login_returns_429_after_five_failed_attempts(): void
    {
        User::create([
            'name' => 'Victime',
            'email' => 'rate-limit@example.test',
            'password' => Hash::make('azerty123'),
            'is_active' => true,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'rate-limit@example.test',
                'password' => 'mauvais-mot-de-passe',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/login', [
            'email' => 'rate-limit@example.test',
            'password' => 'azerty123',
        ])->assertStatus(429);
    }

    public function test_web_login_throttles_by_ip_and_email(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Livewire::test(Login::class)
                ->set('data.email', 'inconnu@example.test')
                ->set('data.password', 'mauvais-mot-de-passe')
                ->call('authenticate')
                ->assertSet('loginError', Login::GENERIC_ERROR);
        }

        Livewire::test(Login::class)
            ->set('data.email', 'inconnu@example.test')
            ->set('data.password', 'mauvais-mot-de-passe')
            ->call('authenticate')
            ->assertSet('loginError', Login::LOGIN_MESSAGES['rate_limited']);

        $this->clearLimiters('inconnu@example.test');
    }

    public function test_magic_endpoint_returns_429_after_repeated_invalid_tokens(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->get('/magic?token=jeton-invalide')->assertRedirect('/login');
        }

        $this->get('/magic?token=jeton-invalide')->assertStatus(429);
    }
}
