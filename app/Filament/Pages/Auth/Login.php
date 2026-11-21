<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\HtmlString;

/**
 * Port de lib/auth.config.ts + components/login-form.tsx : les échecs
 * d'authentification partagent un message générique en français, affiché dans
 * une alerte globale sous les champs (et non dans le cadre d'un seul champ).
 */
class Login extends BaseLogin
{
    /** Évite l'enregistrement comme page navigable par la découverte Filament. */
    protected static bool $isDiscovered = false;

    public ?string $loginError = null;

    public const GENERIC_ERROR = 'Email ou mot de passe incorrect.';

    public const LOGIN_MESSAGES = [
        'missing_fields' => "Veuillez renseigner l'email et le mot de passe.",
        'user_not_found' => self::GENERIC_ERROR,
        'no_password' => self::GENERIC_ERROR,
        'account_disabled' => self::GENERIC_ERROR,
        'wrong_password' => self::GENERIC_ERROR,
        'rate_limited' => 'Trop de tentatives de connexion. Veuillez réessayer plus tard.',
    ];

    /** Hash fictif pour ne pas révéler l'existence d'un compte par le temps de réponse. */
    private const DUMMY_PASSWORD_HASH = '$2y$10$E/hDNQjXn6KcE985RembzeIJE02zO.rmkn40zTElBPXqleVCozHe6';

    public function getTitle(): string
    {
        return 'Connexion';
    }

    public function getHeading(): string
    {
        return 'Connexion';
    }

    public function authenticate(): ?LoginResponse
    {
        $this->loginError = null;

        $data = $this->form->getState();
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '') {
            return $this->failLogin('missing_fields');
        }

        // Limitation par IP et adresse email : un attaquant ne peut pas bloquer
        // tous les comptes depuis la même machine, ni tester un compte depuis
        // plusieurs adresses IP sans limite locale.
        $rateKey = 'web-login:'.sha1(request()->ip().'|'.mb_strtolower($email));

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            return $this->failLogin('rate_limited');
        }

        RateLimiter::hit($rateKey, 60);

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            Hash::check($password, self::DUMMY_PASSWORD_HASH);

            return $this->failLogin('user_not_found');
        }

        if (! $user->password) {
            Hash::check($password, self::DUMMY_PASSWORD_HASH);

            return $this->failLogin('no_password');
        }

        if (! Hash::check($password, $user->password)) {
            return $this->failLogin('wrong_password');
        }

        if (! $user->is_active) {
            return $this->failLogin('account_disabled');
        }

        RateLimiter::clear($rateKey);
        Filament::auth()->login($user, (bool) ($data['remember'] ?? false));
        session()->regenerate();

        return app(LoginResponse::class);
    }

    /** Stocke le message pour l'alerte globale au lieu de le rattacher à un champ. */
    private function failLogin(string $code): ?LoginResponse
    {
        $this->loginError = self::LOGIN_MESSAGES[$code]
            ?? 'Échec de la connexion. Vérifiez vos identifiants et réessayez.';

        return null;
    }

    public function form(Schema $schema): Schema
    {
        return parent::form($schema)
            ->components([
                ...$this->getFormComponents(),
                $this->getLoginErrorComponent(),
            ]);
    }

    /** @return array<int, Component> */
    private function getFormComponents(): array
    {
        return [
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getRememberFormComponent(),
        ];
    }

    private function getLoginErrorComponent(): Component
    {
        return Html::make(function (): ?HtmlString {
            if (! filled($this->loginError)) {
                return null;
            }

            return new HtmlString(
                '<div role="alert" class="rounded-lg border border-danger-500/40 bg-danger-500/10 p-3 text-sm text-danger-700 dark:text-danger-300">'
                . e($this->loginError)
                . '</div>',
            );
        })
            ->visible(fn (): bool => filled($this->loginError));
    }

    /** Sans `required()` : le champ vide remonte le code `missing_fields` depuis authenticate(). */
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Adresse email')
            ->email()
            ->autocomplete()
            ->autofocus();
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Mot de passe')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('current-password');
    }
}
