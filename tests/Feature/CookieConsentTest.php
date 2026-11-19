<?php

namespace Tests\Feature;

use Illuminate\Cookie\Middleware\EncryptCookies;
use Tests\TestCase;

class CookieConsentTest extends TestCase
{
    public function test_login_page_explains_the_cookies_used(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Cookies et confidentialité')
            ->assertSee('Aucun cookie publicitaire ou de suivi n’est déposé.', false)
            ->assertSee(config('session.consent_cookie'));
    }

    public function test_the_consent_cookie_is_not_encrypted_by_laravel(): void
    {
        $this->assertTrue(
            app(EncryptCookies::class)->isDisabled(config('session.consent_cookie')),
            'Le cookie de consentement est écrit par le navigateur : il ne doit pas être chiffré.',
        );
    }
}
