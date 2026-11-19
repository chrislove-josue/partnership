<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class LoginMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function makeUser(string $email, ?string $password, bool $isActive = true): User
    {
        $user = User::create([
            'name' => 'Utilisateur test',
            'email' => $email,
            'password' => Hash::make($password ?? 'azerty123'),
            'is_active' => $isActive,
        ]);

        // Un compte sans mot de passe (OAuth) est représenté par une chaîne vide en base.
        if ($password === null) {
            DB::table('users')->where('id', $user->id)->update(['password' => '']);
        }

        return $user;
    }

    /** @param array<string, mixed> $credentials */
    private function attempt(array $credentials): \Livewire\Features\SupportTesting\Testable
    {
        $test = Livewire::test(Login::class);

        foreach ($credentials as $field => $value) {
            $test->set("data.{$field}", $value);
        }

        return $test->call('authenticate');
    }

    private function assertLoginError(
        \Livewire\Features\SupportTesting\Testable $test,
        string $code,
    ): void {
        $test->assertSet('loginError', Login::LOGIN_MESSAGES[$code])
            ->assertHasNoErrors();
    }

    public function test_root_and_guests_are_redirected_directly_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    public function test_missing_fields_shows_an_explicit_message(): void
    {
        $this->assertLoginError($this->attempt([]), 'missing_fields');
        $this->assertLoginError($this->attempt(['email' => 'user@example.test']), 'missing_fields');
        $this->assertLoginError($this->attempt(['password' => 'secret']), 'missing_fields');
    }

    public function test_unknown_email_shows_an_explicit_message(): void
    {
        $this->assertLoginError(
            $this->attempt([
                'email' => 'inconnu@example.test',
                'password' => 'azerty123',
            ]),
            'user_not_found',
        );
    }

    public function test_wrong_password_shows_an_explicit_message(): void
    {
        $this->makeUser('admin@example.test', 'azerty123');

        $this->assertLoginError(
            $this->attempt([
                'email' => 'admin@example.test',
                'password' => 'mauvais-mot-de-passe',
            ]),
            'wrong_password',
        );
    }

    public function test_account_without_password_shows_an_explicit_message(): void
    {
        $this->makeUser('google-only@example.test', null);

        $this->assertLoginError(
            $this->attempt([
                'email' => 'google-only@example.test',
                'password' => 'azerty123',
            ]),
            'no_password',
        );
    }

    public function test_disabled_account_shows_an_explicit_message(): void
    {
        $this->makeUser('disabled@example.test', 'azerty123', false);

        $this->assertLoginError(
            $this->attempt([
                'email' => 'disabled@example.test',
                'password' => 'azerty123',
            ]),
            'account_disabled',
        );
    }

    public function test_no_account_is_created_for_sounesatchougo_email(): void
    {
        $this->seed();

        $this->assertDatabaseMissing('users', [
            'email' => 'sounesatchougo@gmail.com',
        ]);

        $this->assertLoginError(
            $this->attempt([
                'email' => 'sounesatchougo@gmail.com',
                'password' => 'azerty123',
            ]),
            'user_not_found',
        );
    }

    public function test_valid_credentials_log_the_user_in(): void
    {
        $user = $this->makeUser('admin@example.test', 'azerty123');

        Livewire::test(Login::class)
            ->set('data.email', 'admin@example.test')
            ->set('data.password', 'azerty123')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertSet('loginError', null);

        $this->assertAuthenticatedAs($user);
    }
}
