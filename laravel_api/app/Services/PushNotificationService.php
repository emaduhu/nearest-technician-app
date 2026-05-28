<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PushNotificationService
{
    public function configured(): bool
    {
        return (bool) $this->serviceAccount() || filled(config('services.fcm.server_key'));
    }

    public function send(?string $token, array $notification, array $data = []): bool
    {
        if (! $token) {
            return false;
        }

        return $this->sendV1($token, $notification, $data) || $this->sendLegacy($token, $notification, $data);
    }

    private function sendV1(string $token, array $notification, array $data): bool
    {
        $account = $this->serviceAccount();
        if (! $account) {
            return false;
        }

        try {
            $accessToken = $this->accessToken($account);
            $projectId = $account['project_id'];
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => (string) ($notification['title'] ?? ''),
                            'body' => (string) ($notification['body'] ?? ''),
                        ],
                        'data' => collect($data)->map(fn ($value) => (string) $value)->all(),
                        'android' => [
                            'priority' => 'HIGH',
                            'notification' => ['sound' => 'default'],
                        ],
                        'apns' => [
                            'payload' => [
                                'aps' => ['sound' => 'default'],
                            ],
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('Firebase Cloud Messaging v1 delivery failed.', [
                    'project_id' => $projectId,
                    'status' => $response->status(),
                    'token' => $this->tokenHint($token),
                    'body' => $response->json() ?: $response->body(),
                ]);
            }

            return $response->successful();
        } catch (Throwable $exception) {
            Log::warning('Firebase Cloud Messaging v1 delivery exception.', [
                'token' => $this->tokenHint($token),
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function sendLegacy(string $token, array $notification, array $data): bool
    {
        $serverKey = config('services.fcm.server_key');
        if (! $serverKey) {
            return false;
        }

        try {
            $response = Http::withToken($serverKey)
                ->timeout(10)
                ->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => $token,
                    'notification' => $notification,
                    'data' => collect($data)->map(fn ($value) => (string) $value)->all(),
                ]);

            if ($response->failed()) {
                Log::warning('Firebase Cloud Messaging legacy delivery failed.', [
                    'status' => $response->status(),
                    'token' => $this->tokenHint($token),
                    'body' => $response->json() ?: $response->body(),
                ]);
            }

            return $response->successful();
        } catch (Throwable $exception) {
            Log::warning('Firebase Cloud Messaging legacy delivery exception.', [
                'token' => $this->tokenHint($token),
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array{project_id: string, client_email: string, private_key: string, token_uri: string}|null
     */
    private function serviceAccount(): ?array
    {
        $json = config('services.fcm.service_account_json');
        if ($json) {
            $decoded = json_decode(base64_decode((string) $json, true) ?: (string) $json, true);
            if (is_array($decoded)) {
                return $this->normalizeAccount($decoded);
            }
        }

        $path = config('services.fcm.service_account_path');
        if ($path && ! str_starts_with((string) $path, '/')) {
            $path = base_path((string) $path);
        }

        if ($path && is_readable((string) $path)) {
            $decoded = json_decode(file_get_contents((string) $path) ?: '', true);
            if (is_array($decoded)) {
                return $this->normalizeAccount($decoded);
            }
        }

        return $this->normalizeAccount([
            'project_id' => config('services.fcm.project_id'),
            'client_email' => config('services.fcm.client_email'),
            'private_key' => config('services.fcm.private_key'),
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]);
    }

    private function normalizeAccount(array $account): ?array
    {
        $projectId = $account['project_id'] ?? null;
        $clientEmail = $account['client_email'] ?? null;
        $privateKey = isset($account['private_key']) ? str_replace('\\n', "\n", (string) $account['private_key']) : null;
        $tokenUri = $account['token_uri'] ?? 'https://oauth2.googleapis.com/token';

        if (! $projectId || ! $clientEmail || ! $privateKey) {
            return null;
        }

        return [
            'project_id' => (string) $projectId,
            'client_email' => (string) $clientEmail,
            'private_key' => $privateKey,
            'token_uri' => (string) $tokenUri,
        ];
    }

    private function accessToken(array $account): string
    {
        return Cache::remember('fcm_v1_access_token_'.sha1($account['client_email']), now()->addMinutes(50), function () use ($account): string {
            $now = time();
            $jwt = $this->jwt([
                'alg' => 'RS256',
                'typ' => 'JWT',
            ], [
                'iss' => $account['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $account['token_uri'],
                'iat' => $now,
                'exp' => $now + 3600,
            ], $account['private_key']);

            $response = Http::asForm()
                ->acceptJson()
                ->timeout(10)
                ->post($account['token_uri'], [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

            if ($response->failed() || blank($response->json('access_token'))) {
                throw new \RuntimeException('Firebase access token request failed.');
            }

            return (string) $response->json('access_token');
        });
    }

    private function jwt(array $header, array $payload, string $privateKey): string
    {
        $unsigned = $this->base64Url(json_encode($header, JSON_THROW_ON_ERROR)).'.'.$this->base64Url(json_encode($payload, JSON_THROW_ON_ERROR));
        if (! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Firebase JWT signing failed.');
        }

        return $unsigned.'.'.$this->base64Url($signature);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function tokenHint(string $token): string
    {
        return substr($token, 0, 10).'...'.substr($token, -6);
    }
}
