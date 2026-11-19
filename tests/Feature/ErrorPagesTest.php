<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    /** @var array<int, array{title: string, message: string}> */
    private const ERRORS = [
        400 => ['title' => 'Requête invalide', 'message' => "La requête envoyée n'a pas pu être comprise. Vérifiez les informations saisies et réessayez."],
        401 => ['title' => 'Authentification requise', 'message' => 'Vous devez vous connecter pour accéder à cette page.'],
        402 => ['title' => 'Paiement requis', 'message' => "Cette ressource nécessite un paiement. Contactez l'administrateur pour y accéder."],
        403 => ['title' => 'Accès refusé', 'message' => "Vous n'avez pas les autorisations nécessaires pour accéder à cette page."],
        404 => ['title' => 'Page introuvable', 'message' => "La page recherchée n'existe pas ou a été déplacée."],
        405 => ['title' => 'Action non autorisée', 'message' => "Cette action n'est pas disponible pour cette page."],
        408 => ['title' => 'Délai dépassé', 'message' => 'La demande a mis trop de temps à aboutir. Vérifiez votre connexion et réessayez.'],
        419 => ['title' => 'Session expirée', 'message' => 'Votre session a expiré pour des raisons de sécurité. Rechargez la page et reconnectez-vous.'],
        422 => ['title' => 'Données invalides', 'message' => "Certaines informations envoyées ne sont pas valides. Corrigez-les et réessayez."],
        429 => ['title' => 'Trop de demandes', 'message' => 'Vous avez effectué trop de demandes. Patientez quelques instants avant de réessayer.'],
        500 => ['title' => 'Erreur interne du serveur', 'message' => 'Une erreur inattendue est survenue. Réessayez dans quelques instants.'],
        501 => ['title' => 'Fonctionnalité indisponible', 'message' => "Cette fonctionnalité n'est pas encore disponible."],
        502 => ['title' => 'Passerelle indisponible', 'message' => 'Le serveur a reçu une réponse invalide. Réessayez dans quelques instants.'],
        503 => ['title' => 'Service temporairement indisponible', 'message' => 'Le service est temporairement indisponible. Réessayez dans quelques instants.'],
        504 => ['title' => 'Délai de la passerelle dépassé', 'message' => "Le serveur n'a pas répondu à temps. Réessayez dans quelques instants."],
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
            $this->assertTrue(view()->exists("errors.{$code}"), "Vue errors.{$code} absente");
        }

        $this->assertTrue(view()->exists('errors.4xx'));
        $this->assertTrue(view()->exists('errors.5xx'));
        $this->assertTrue(view()->exists('errors.layout'));
    }

    public function test_specific_errors_render_a_french_page_with_the_right_status(): void
    {
        foreach (self::ERRORS as $code => $expected) {
            $response = $this->get('/__test-error/'.$code);

            $response->assertStatus($code)
                ->assertSee('ChurchManager', false)
                ->assertSee('Erreur '.substr((string) $code, 0, 1).'xx', false)
                ->assertSee((string) $code, false)
                ->assertSee($expected['title'])
                ->assertSee($expected['message'])
                ->assertSee(in_array($code, [401, 403], true) ? 'Se connecter' : 'Retour au tableau de bord', false)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('Symfony', false)
                ->assertDontSee('SQLSTATE', false)
                ->assertDontSee('stack trace', false);
        }
    }

    public function test_auth_errors_offer_a_login_action(): void
    {
        foreach ([401, 403] as $code) {
            $this->get('/__test-error/'.$code)
                ->assertStatus($code)
                ->assertSee('Se connecter', false)
                ->assertSee('href="/login"', false);
        }

        $this->get('/__test-error/404')
            ->assertStatus(404)
            ->assertDontSee('Se connecter', false)
            ->assertSee('href="/dashboard"', false);
    }

    public function test_unknown_4xx_and_5xx_codes_use_the_generic_pages(): void
    {
        $this->get('/__test-error/418')
            ->assertStatus(418)
            ->assertSee('Erreur 4xx', false)
            ->assertSee('418', false)
            ->assertSee('Requête invalide', false)
            ->assertSee("La requête n'a pas pu être traitée.");

        $this->get('/__test-error/599')
            ->assertStatus(599)
            ->assertSee('Erreur 5xx', false)
            ->assertSee('599', false)
            ->assertSee('Erreur du serveur', false)
            ->assertSee('Une erreur est survenue au niveau du serveur.', false);
    }

    public function test_error_pages_follow_the_light_dark_system_theme(): void
    {
        $response = $this->get('/__test-error/404');

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
