<?php

namespace App\Services;

use App\Models\PushToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi de notifications push via Firebase Cloud Messaging (HTTP v1).
 *
 * Nécessite un fichier de compte de service Firebase dans
 * storage/app/firebase-service-account.json (ou la variable d'environnement
 * FIREBASE_SERVICE_ACCOUNT_JSON contenant le JSON brut). Sans configuration,
 * les envois sont silencieusement ignorés (pas d'erreur).
 */
class PushNotificationService
{
    private ?array $serviceAccount = null;

    private ?string $projectId = null;

    private ?string $accessToken = null;

    private int $accessTokenExpires = 0;

    public function __construct()
    {
        $json = null;

        $env = env('FIREBASE_SERVICE_ACCOUNT_JSON');
        if (is_string($env) && $env !== '') {
            $json = $env;
        } else {
            $path = storage_path('app/firebase-service-account.json');
            if (is_file($path)) {
                $json = @file_get_contents($path) ?: null;
            }
        }

        if (is_string($json) && $json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded) && isset($decoded['project_id'], $decoded['client_email'], $decoded['private_key'])) {
                $this->serviceAccount = $decoded;
                $this->projectId = $decoded['project_id'];
            }
        }
    }

    public function isConfigured(): bool
    {
        return $this->serviceAccount !== null;
    }

    /**
     * Envoie une notification push aux utilisateurs donnés.
     *
     * @param  array<int, int|string>  $userIds
     * @param  array<string, mixed>  $data  Données supplémentaires (valeurs string uniquement côté FCM)
     */
    public function sendToUsers(array $userIds, string $title, string $message, array $data = []): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $userIds = array_values(array_unique($userIds));

        if ($userIds === []) {
            return;
        }

        $tokens = PushToken::query()
            ->whereIn('user_id', $userIds)
            ->get();

        if ($tokens->isEmpty()) {
            return;
        }

        $accessToken = $this->getAccessToken();

        if ($accessToken === null) {
            return;
        }

        foreach ($tokens as $token) {
            try {
                $response = Http::withToken($accessToken)
                    ->timeout(10)
                    ->post(
                        sprintf('https://fcm.googleapis.com/v1/projects/%s/messages:send', $this->projectId),
                        [
                            'message' => [
                                'token' => $token->token,
                                'notification' => [
                                    'title' => $title,
                                    'body' => $message,
                                ],
                                'data' => $this->stringify($data),
                            ],
                        ],
                    );

                if (! $response->successful()) {
                    $body = $response->json();
                    $error = $body['error']['details'][0]['errorCode'] ?? $body['error']['status'] ?? null;

                    if (in_array($error, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'], true)) {
                        $token->delete();
                    } else {
                        Log::warning('Échec envoi push FCM', [
                            'user_id' => $token->user_id,
                            'status' => $response->status(),
                            'body' => $response->body(),
                        ]);
                    }
                } else {
                    $token->forceFill(['last_used_at' => now()])->save();
                }
            } catch (\Throwable $e) {
                Log::warning('Exception envoi push FCM : '.$e->getMessage());
            }
        }
    }

    private function getAccessToken(): ?string
    {
        if ($this->accessToken !== null && time() < $this->accessTokenExpires) {
            return $this->accessToken;
        }

        $account = $this->serviceAccount;

        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $now = time();
        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $account['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signature = '';
        $privateKey = openssl_pkey_get_private($account['private_key']);

        if (! $privateKey) {
            Log::warning('Clé privée Firebase invalide.');

            return null;
        }

        openssl_sign($header.'.'.$payload, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        $jwt = $header.'.'.$payload.'.'.$this->base64UrlEncode($signature);

        try {
            $response = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (! $response->successful()) {
                Log::warning('Impossible d\'obtenir le jeton d\'accès Firebase', ['body' => $response->body()]);

                return null;
            }

            $this->accessToken = $response->json('access_token');
            $this->accessTokenExpires = time() + 3500;

            return $this->accessToken;
        } catch (\Throwable $e) {
            Log::warning('Exception jeton Firebase : '.$e->getMessage());

            return null;
        }
    }

    /**
     * FCM exige des valeurs string dans data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function stringify(array $data): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            $out[(string) $key] = is_scalar($value) || $value === null
                ? (string) $value
                : json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $out;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
