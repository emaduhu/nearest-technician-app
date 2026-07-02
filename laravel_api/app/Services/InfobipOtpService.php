<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class InfobipOtpService
{
    /**
     * @return array{verificationId: string, response: array<string, mixed>}
     */
    public function send(string $phone): array
    {
        $this->ensureConfigured();

        $verificationId = (string) Str::uuid();
        $pin = (string) random_int(100000, 999999);
        $normalizedPhone = $this->normalizePhone($phone);

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders([
                    'Authorization' => $this->authorizationHeader(),
                ])
                ->timeout(15)
                ->post($this->endpoint(), [
                    'messages' => [[
                        'from' => $this->sender(),
                        'destinations' => [[
                            'to' => $normalizedPhone,
                        ]],
                        'text' => $this->message($pin),
                    ]],
                ]);

            $body = $response->json();
            if ($response->failed() || ! is_array($body)) {
                throw new RuntimeException($this->failureMessage($body, $response->body()));
            }

            Cache::put($this->cacheKey($verificationId), [
                'phone' => $normalizedPhone,
                'pin_hash' => hash('sha256', $pin),
            ], now()->addMinutes($this->ttlMinutes()));

            return ['verificationId' => $verificationId, 'response' => $body];
        } catch (Throwable $exception) {
            Log::warning('Infobip OTP send failed.', [
                'message' => $exception->getMessage(),
                'phone' => $this->phoneHint($phone),
            ]);

            throw ValidationException::withMessages([
                'phone' => [$this->publicFailureMessage($exception->getMessage())],
            ]);
        }
    }

    public function verify(string $verificationId, string $pin, string $phone): void
    {
        $payload = Cache::get($this->cacheKey($verificationId));
        $normalizedPhone = $this->normalizePhone($phone);

        if (! is_array($payload)
            || ($payload['phone'] ?? '') !== $normalizedPhone
            || ! hash_equals((string) ($payload['pin_hash'] ?? ''), hash('sha256', $pin))
        ) {
            throw ValidationException::withMessages([
                'code' => ['The phone verification code is invalid or expired.'],
            ]);
        }

        Cache::forget($this->cacheKey($verificationId));
    }

    public function configured(): bool
    {
        return filled($this->baseUrl())
            && filled($this->apiKey())
            && filled($this->sender());
    }

    private function ensureConfigured(): void
    {
        if (! $this->configured()) {
            throw ValidationException::withMessages([
                'phone' => ['Infobip OTP is not configured.'],
            ]);
        }
    }

    private function endpoint(): string
    {
        return rtrim($this->baseUrl(), '/').'/sms/3/messages';
    }

    private function baseUrl(): string
    {
        return trim((string) config('services.infobip.base_url', ''));
    }

    private function apiKey(): string
    {
        return trim((string) config('services.infobip.api_key', ''));
    }

    private function authorizationHeader(): string
    {
        $apiKey = $this->apiKey();
        if (preg_match('/^(App|Bearer|Basic)\s+/i', $apiKey)) {
            return $apiKey;
        }

        return 'App '.$apiKey;
    }

    private function sender(): string
    {
        return trim((string) config('services.infobip.sender', 'InfoSMS'));
    }

    private function ttlMinutes(): int
    {
        return max((int) config('services.infobip.otp_ttl_minutes', 10), 1);
    }

    private function message(string $pin): string
    {
        $template = (string) config('services.infobip.otp_message', 'Your Nearest Technician verification code is :code');

        return str_replace(':code', $pin, $template);
    }

    private function cacheKey(string $verificationId): string
    {
        return 'infobip_phone_verification:'.hash('sha256', $verificationId);
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function phoneHint(string $phone): string
    {
        $digits = $this->normalizePhone($phone);

        return strlen($digits) <= 5
            ? $digits
            : substr($digits, 0, 4).'***'.substr($digits, -2);
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function failureMessage(?array $body, string $rawBody): string
    {
        $errorCode = (string) data_get($body, 'errorCode', '');
        $description = (string) data_get($body, 'description', '');
        if ($errorCode !== '' || $description !== '') {
            return trim("{$errorCode} {$description}");
        }

        return $rawBody !== '' ? $rawBody : 'Infobip SMS request failed.';
    }

    private function publicFailureMessage(string $message): string
    {
        $lower = strtolower($message);
        if (str_contains($lower, 'e401') || str_contains($lower, 'authentication')) {
            return 'Infobip rejected the SMS credentials. Check INFOBIP_BASE_URL and INFOBIP_API_KEY.';
        }
        if (str_contains($lower, 'sender')) {
            return 'Infobip rejected the sender name. Check INFOBIP_SENDER.';
        }

        return 'Infobip could not send the phone verification code. Please try again.';
    }
}
