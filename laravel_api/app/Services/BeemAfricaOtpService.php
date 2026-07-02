<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class BeemAfricaOtpService
{
    /**
     * @return array{pinId: string, response: array<string, mixed>}
     */
    public function send(string $phone): array
    {
        $this->ensureConfigured();

        return $this->sendSmsOtp($phone);
    }

    public function verify(string $pinId, string $pin, ?string $phone = null): void
    {
        $this->ensureConfigured();

        $this->verifySmsOtp($pinId, $pin, (string) $phone);
    }

    public function configured(): bool
    {
        return filled($this->accessKey())
            && filled($this->secretKey())
            && filled($this->sender());
    }

    private function ensureConfigured(): void
    {
        if (! $this->configured()) {
            throw ValidationException::withMessages([
                'phone' => ['Beem Africa OTP is not configured.'],
            ]);
        }
    }

    /**
     * @return array{pinId: string, response: array<string, mixed>}
     */
    private function sendSmsOtp(string $phone): array
    {
        $pinId = (string) Str::uuid();
        $pin = (string) random_int(100000, 999999);
        $normalizedPhone = $this->normalizePhone($phone);

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withBasicAuth($this->accessKey(), $this->secretKey())
                ->timeout(15)
                ->post((string) config('services.beem.sms_url'), [
                    'source_addr' => $this->sender(),
                    'schedule_time' => '',
                    'encoding' => 0,
                    'message' => $this->message($pin),
                    'recipients' => [[
                        'recipient_id' => 1,
                        'dest_addr' => $normalizedPhone,
                    ]],
                ]);

            $body = $response->json();
            if ($response->failed() || ! is_array($body)) {
                throw new RuntimeException($response->body() ?: 'Beem Africa SMS request failed.');
            }

            $code = (int) data_get($body, 'code', 0);
            $successful = (bool) data_get($body, 'successful', false);
            if (! $successful && $code !== 100) {
                throw new RuntimeException((string) data_get($body, 'message', 'Beem Africa SMS request failed.'));
            }

            Cache::put($this->cacheKey($pinId), [
                'phone' => $normalizedPhone,
                'pin_hash' => hash('sha256', $pin),
            ], now()->addMinutes($this->ttlMinutes()));

            return ['pinId' => $pinId, 'response' => $body];
        } catch (Throwable $exception) {
            Log::warning('Beem Africa SMS OTP send failed.', [
                'message' => $exception->getMessage(),
                'phone' => $this->phoneHint($phone),
            ]);

            throw ValidationException::withMessages([
                'phone' => [$this->publicFailureMessage($exception->getMessage())],
            ]);
        }
    }

    private function verifySmsOtp(string $pinId, string $pin, string $phone): void
    {
        $payload = Cache::get($this->cacheKey($pinId));
        $normalizedPhone = $this->normalizePhone($phone);

        if (! is_array($payload)
            || ($payload['phone'] ?? '') !== $normalizedPhone
            || ! hash_equals((string) ($payload['pin_hash'] ?? ''), hash('sha256', $pin))
        ) {
            throw ValidationException::withMessages([
                'code' => ['The phone verification code is invalid or expired.'],
            ]);
        }

        Cache::forget($this->cacheKey($pinId));
    }

    private function accessKey(): string
    {
        return trim((string) config('services.beem.access_key', ''));
    }

    private function secretKey(): string
    {
        return trim((string) config('services.beem.secret_key', ''));
    }

    private function sender(): string
    {
        return trim((string) config('services.beem.sender', 'INFO'));
    }

    private function ttlMinutes(): int
    {
        return 10;
    }

    private function message(string $pin): string
    {
        return "Your Nearest Technician verification code is {$pin}";
    }

    private function cacheKey(string $pinId): string
    {
        return 'beem_phone_verification:'.hash('sha256', $pinId);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($phone, '255')) {
            return $phone;
        }

        if (str_starts_with($phone, '0')) {
            return '255'.substr($phone, 1);
        }

        if (strlen($phone) === 9) {
            return '255'.$phone;
        }

        return $phone;
    }

    private function phoneHint(string $phone): string
    {
        $digits = $this->normalizePhone($phone);

        return strlen($digits) <= 5
            ? $digits
            : substr($digits, 0, 4).'***'.substr($digits, -2);
    }

    private function publicFailureMessage(string $message): string
    {
        $lower = strtolower($message);
        if (str_contains($lower, 'auth') || str_contains($lower, 'credential') || str_contains($lower, 'unauthorized')) {
            return 'Beem Africa rejected the SMS credentials. Check BEEM_ACCESS_KEY and BEEM_SECRET_KEY.';
        }

        return 'Beem Africa could not send the phone verification code. Please try again.';
    }
}
