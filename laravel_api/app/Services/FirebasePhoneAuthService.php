<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class FirebasePhoneAuthService
{
    /**
     * @return array{uid: string, phone: string}
     */
    public function verifyIdToken(string $idToken, string $expectedPhone): array
    {
        try {
            $parts = explode('.', $idToken);
            if (count($parts) !== 3) {
                throw new \RuntimeException('Malformed Firebase ID token.');
            }

            [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
            $header = $this->decodeJsonPart($encodedHeader);
            $payload = $this->decodeJsonPart($encodedPayload);

            if (($header['alg'] ?? null) !== 'RS256' || blank($header['kid'] ?? null)) {
                throw new \RuntimeException('Unsupported Firebase ID token header.');
            }

            $cert = $this->certificates()[(string) $header['kid']] ?? null;
            if (! $cert) {
                throw new \RuntimeException('Firebase ID token certificate was not found.');
            }

            $signature = $this->base64UrlDecode($encodedSignature);
            $verified = openssl_verify($encodedHeader.'.'.$encodedPayload, $signature, $cert, OPENSSL_ALGO_SHA256);
            if ($verified !== 1) {
                throw new \RuntimeException('Firebase ID token signature is invalid.');
            }

            $projectId = $this->projectId();
            $now = time();
            if (($payload['aud'] ?? null) !== $projectId || ($payload['iss'] ?? null) !== "https://securetoken.google.com/{$projectId}") {
                throw new \RuntimeException('Firebase ID token is for a different project.');
            }
            if ((int) ($payload['exp'] ?? 0) < $now || (int) ($payload['iat'] ?? 0) > $now + 300) {
                throw new \RuntimeException('Firebase ID token is expired or not valid yet.');
            }

            $phone = $this->normalizePhone((string) ($payload['phone_number'] ?? ''));
            if ($phone === '' || $phone !== $this->normalizePhone($expectedPhone)) {
                throw new \RuntimeException('Firebase phone number does not match the submitted phone.');
            }

            $uid = (string) ($payload['user_id'] ?? $payload['sub'] ?? '');
            if ($uid === '') {
                throw new \RuntimeException('Firebase ID token does not contain a user id.');
            }

            return ['uid' => $uid, 'phone' => $phone];
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Firebase phone verification failed.', [
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'phoneVerificationIdToken' => ['Verify your phone number before creating the account.'],
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function certificates(): array
    {
        return Cache::remember('firebase_phone_auth_certs', now()->addHours(6), function (): array {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get('https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com');

            if ($response->failed() || ! is_array($response->json())) {
                throw new \RuntimeException('Unable to fetch Firebase Auth certificates.');
            }

            return $response->json();
        });
    }

    private function projectId(): string
    {
        $projectId = config('services.firebase.project_id') ?: config('services.fcm.project_id');
        if (filled($projectId)) {
            return (string) $projectId;
        }

        $account = $this->serviceAccount();
        if (filled($account['project_id'] ?? null)) {
            return (string) $account['project_id'];
        }

        throw new \RuntimeException('Firebase project id is not configured.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceAccount(): array
    {
        $json = config('services.fcm.service_account_json');
        if ($json) {
            $decoded = json_decode(base64_decode((string) $json, true) ?: (string) $json, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $path = config('services.fcm.service_account_path');
        if ($path && ! str_starts_with((string) $path, '/')) {
            $path = base_path((string) $path);
        }

        if ($path && is_readable((string) $path)) {
            $decoded = json_decode(file_get_contents((string) $path) ?: '', true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonPart(string $value): array
    {
        $decoded = json_decode($this->base64UrlDecode($value), true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Firebase ID token part is not valid JSON.');
        }

        return $decoded;
    }

    private function base64UrlDecode(string $value): string
    {
        $padded = str_pad(strtr($value, '-_', '+/'), (int) ceil(strlen($value) / 4) * 4, '=', STR_PAD_RIGHT);
        $decoded = base64_decode($padded, true);
        if ($decoded === false) {
            throw new \RuntimeException('Firebase ID token part is not valid base64.');
        }

        return $decoded;
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }
}
