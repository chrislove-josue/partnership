<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Port de lib/auth.config.ts + components/login-form.tsx : la connexion renvoie
 * un code explicite traduit en message français, affiché directement sur la page.
 */
class Login extends BaseLogin
{
    /** Évite l'enregistrement comme page navigable par la découverte Filament. */
    protected static bool $isDiscovered = false;

    public const LOGIN_MESSAGES = [
        'missing_fields' => "Veuillez renseigner l'email et le mot de passe.",
        'user_not_found' => "Aucun compte n'existe avec cette adresse email.",
        'no_password' => "Ce compte n'a pas de mot de passe. Utilisez un autre moyen de connexion.",
        'account_disabled' => 'Ce compte est désactivé. Contactez l\'administrateur.',
        'wrong_password' => 'Mot de passe incorrect.',
    ];

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
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '') {
            $this->failLogin('missing_fields');
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->failLogin('user_not_found');
        }

        if (! $user->password) {
            $this->failLogin('no_password');
        }

        if (! $user->is_active) {
            $this->failLogin('account_disabled');
        }

        if (! Hash::check($password, $user->password)) {
            $this->failLogin('wrong_password');
        }

        Filament::auth()->login($user, (bool) ($data['remember'] ?? false));
        session()->regenerate();

        return app(LoginResponse::class);
    }

    private function failLogin(string $code): never
    {
        throw ValidationException::withMessages([
            'data.email' => self::LOGIN_MESSAGES[$code]
                ?? 'Échec de la connexion. Vérifiez vos identifiants et réessayez.',
        ]);
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
