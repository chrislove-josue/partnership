<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MagicTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get('/dashboard/login')->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/dashboard/login');
    }

    public function test_magic_link_authenticates_the_user(): void
    {
        $this->seed();

        $user = User::where('email', 'libertsinzo7@gmail.com')->firstOrFail();

        $link = app(MagicTokenService::class)->generate($user->email);
        $path = parse_url($link, PHP_URL_PATH).'?'.parse_url($link, PHP_URL_QUERY);

        $this->get($path)->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_magic_link_cannot_be_used_twice(): void
    {
        $this->seed();

        $user = User::where('email', 'libertsinzo7@gmail.com')->firstOrFail();

        $link = app(MagicTokenService::class)->generate($user->email);
        $path = parse_url($link, PHP_URL_PATH).'?'.parse_url($link, PHP_URL_QUERY);

        $this->get($path)->assertRedirect('/dashboard');
        $this->post('/dashboard/logout');

        $this->get($path)->assertRedirect('/dashboard/login');
        $this->assertGuest();
    }
}
