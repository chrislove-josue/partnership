<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    /** @var array<int, array{title: string, message: string}> */
    private const ERRORS = [
        400 => ['Requête invalide', "La requête envoyée n'a pas pu être comprise."],
        401 => ['Authentification requise', 'Vous devez vous connecter pour accéder à cette page.'],
        402 => ['Paiement requis', 'Cette ressource nécessite un paiement.'],
        403 => ['Accès refusé', "Vous n'avez pas les autorisations nécessaires."],
        404 => ['Page introuvable', "La page recherchée n'existe pas ou a été déplacée."],
        405 => ['Action non autorisée', "Cette action n'est pas disponible pour cette page."],
        408 => ['Délai dépassé', 'La demande a mis trop de temps à aboutir.'],
        419 => ['Session expirée', 'Votre session a expiré pour des raisons de sécurité.'],
        422 => ['Données invalides', "Certaines informations envoyées ne sont pas valides."],
        429 => ['Trop de demandes', 'Vous avez effectué trop de demandes.'],
        500 => ['Erreur interne du serveur', 'Une erreur inattendue est survenue.'],
        501 => ['Fonctionnalité indisponible', "Cette fonctionnalité n'est pas encore disponible."],
        502 => ['Passerelle indisponible', 'Le serveur a reçu une réponse invalide.'],
        503 => ['Service temporairement indisponible', 'Le service est temporairement indisponible.'],
        504 => ['Délai de la passerelle dépassé', "Le serveur n'a pas répondu à temps."],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__test-error/{code}', fn (string $code) => abort((int) $code))
            ->whereNumber('code')
            ->name('test.error');
    }

    public function test_every_error_view_is_registered(): void
    {
        foreach (array_keys(self::ERRORS) as $code) {
            $this->assertTrue(view()->exists("errors::{$code}"), "Vue errors::{$code} absente");
        }

        $this->assertTrue(view()->exists('errors::4xx'));
        $this->assertTrue(view()->exists('errors::5xx'));
        $this->assertTrue(view()->exists('errors::layout'));
    }

    public function test_specific_errors_render_a_french_page_with_the_right_status(): void
    {
        foreach (self::ERRORS as $code => $expected) {
            $response = $this->get(route('test.error', ['code' => $code]));

            $response->assertStatus($code)
                ->assertSee('ChurchManager', false)
                ->assertSee('Erreur '.substr((string) $code, 0, 1).'xx', false)
                ->assertSee((string) $code, false)
                ->assertSee($expected['title'], false)
                ->assertSee($expected['message'], false)
                ->assertSee('Retour au tableau de bord', false)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('Symfony', false)
                ->assertDontSee('SQLSTATE', false)
                ->assertDontSee('stack trace', false);
        }
    }

    public function test_auth_errors_offer_a_login_action(): void
    {
        foreach ([401, 403] as $code) {
            $this->get(route('test.error', ['code' => $code]))
                ->assertStatus($code)
                ->assertSee('Se connecter', false)
                ->assertSee('href="/login"', false);
        }

        $this->get(route('test.error', ['code' => 404]))
            ->assertStatus(404)
            ->assertDontSee('Se connecter', false)
            ->assertSee('href="/dashboard"', false);
    }

    public function test_unknown_4xx_and_5xx_codes_use_the_generic_pages(): void
    {
        $this->get(route('test.error', ['code' => 418]))
            ->assertStatus(418)
            ->assertSee('Erreur 4xx', false)
            ->assertSee('418', false)
            ->assertSee('Requête invalide', false)
            ->assertSee("La requête n'a pas pu être traitée.", false);

        $this->get(route('test.error', ['code' => 599]))
            ->assertStatus(599)
            ->assertSee('Erreur 5xx', false)
            ->assertSee('599', false)
            ->assertSee('Erreur du serveur', false)
            ->assertSee('Une erreur est survenue au niveau du serveur.', false);
    }

    public function test_error_pages_follow_the_light_dark_system_theme(): void
    {
        $response = $this->get(route('test.error', ['code' => 404]));

        $response->assertStatus(404)
            ->assertSee("localStorage.getItem('theme')", false)
            ->assertSee('light', false)
            ->assertSee('prefers-color-scheme', false)
            ->assertSee("classList.add('dark')", false)
            ->assertSee('background-color: #f9fafb', false)
            ->assertSee('html.dark body', false)
            ->assertSee('background-color: #030712', false);
    }
}
