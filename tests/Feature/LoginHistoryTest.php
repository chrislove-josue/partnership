<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class LoginHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email = 'historique@example.test'): User
    {
        return User::create([
            'name' => 'Utilisateur historique',
            'email' => $email,
            'password' => Hash::make('azerty123'),
            'is_active' => true,
        ]);
    }

    public function test_web_login_is_recorded(): void
    {
        $user = $this->user();

        Livewire::test(Login::class)
            ->set('data.email', $user->email)
            ->set('data.password', 'azerty123')
            ->call('authenticate');

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'event' => LoginHistory::EVENT_LOGIN,
            'source' => LoginHistory::SOURCE_WEB,
        ]);
        $this->assertDatabaseCount('login_histories', 1);
    }

    public function test_web_logout_is_recorded(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post('/dashboard/logout')
            ->assertRedirect('/login');

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'event' => LoginHistory::EVENT_LOGOUT,
            'source' => LoginHistory::SOURCE_WEB,
        ]);
    }

    public function test_mobile_login_and_logout_are_recorded(): void
    {
        $user = $this->user();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'azerty123',
        ])->assertOk();

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'event' => LoginHistory::EVENT_LOGIN,
            'source' => LoginHistory::SOURCE_MOBILE,
        ]);

        $token = $response->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'event' => LoginHistory::EVENT_LOGOUT,
            'source' => LoginHistory::SOURCE_MOBILE,
        ]);
    }

    public function test_mobile_history_is_private_to_the_authenticated_user(): void
    {
        $user = $this->user();
        $other = $this->user('autre@example.test');

        $user->loginHistories()->create([
            'event' => LoginHistory::EVENT_LOGIN,
            'source' => LoginHistory::SOURCE_MOBILE,
            'created_at' => now(),
        ]);
        $other->loginHistories()->create([
            'event' => LoginHistory::EVENT_LOGIN,
            'source' => LoginHistory::SOURCE_WEB,
            'created_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/history')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonFragment(['source_label' => 'Application mobile'])
            ->assertJsonMissing(['source_label' => 'Site web']);
    }

    public function test_filament_history_page_shows_only_the_authenticated_user_entries(): void
    {
        $this->seed();

        $user = $this->user();
        $other = $this->user('autre-page@example.test');

        $user->loginHistories()->create([
            'event' => LoginHistory::EVENT_LOGIN,
            'source' => LoginHistory::SOURCE_MOBILE,
            'ip_address' => '10.0.0.1',
            'created_at' => now(),
        ]);
        $other->loginHistories()->create([
            'event' => LoginHistory::EVENT_LOGOUT,
            'source' => LoginHistory::SOURCE_WEB,
            'ip_address' => '10.0.0.2',
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/dashboard/login-history')
            ->assertOk()
            ->assertSee('Historique de connexion')
            ->assertSee('Application mobile')
            ->assertSee('10.0.0.1')
            ->assertDontSee('10.0.0.2');
    }
}
