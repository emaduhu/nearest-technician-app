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

        if (! $this->otpConfigured()) {
            return $this->sendSmsOtp($phone);
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withBasicAuth($this->accessKey(), $this->secretKey())
                ->timeout(15)
                ->post((string) config('services.beem.otp_request_url'), [
                    'msisdn' => $this->normalizePhone($phone),
                    'appId' => $this->appId(),
                ]);

            $body = $response->json();
            if ($response->failed() || ! is_array($body)) {
                throw new RuntimeException($response->body() ?: 'Beem Africa OTP request failed.');
            }

            $pinId = (string) data_get($body, 'data.pinId', '');
            $code = (int) data_get($body, 'data.message.code', data_get($body, 'code', 0));
            if ($pinId === '' || ($code !== 0 && $code !== 100)) {
                throw new RuntimeException((string) data_get($body, 'data.message.message', data_get($body, 'message', 'Beem Africa OTP request failed.')));
            }

            return ['pinId' => $pinId, 'response' => $body];
        } catch (Throwable $exception) {
            Log::warning('Beem Africa OTP send failed.', [
                'message' => $exception->getMessage(),
                'phone' => $this->phoneHint($phone),
            ]);

            throw ValidationException::withMessages([
                'phone' => [$this->publicFailureMessage($exception->getMessage())],
            ]);
        }
    }

    public function verify(string $pinId, string $pin, ?string $phone = null): void
    {
        $this->ensureConfigured();

        if (! $this->otpConfigured()) {
            $this->verifySmsOtp($pinId, $pin, (string) $phone);

            return;
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withBasicAuth($this->accessKey(), $this->secretKey())
                ->timeout(15)
                ->post((string) config('services.beem.otp_verify_url'), [
                    'pinId' => $pinId,
                    'pin' => $pin,
                ]);

            $body = $response->json();
            if ($response->failed() || ! is_array($body)) {
                throw new RuntimeException($response->body() ?: 'Beem Africa OTP verification failed.');
            }

            $code = (int) data_get($body, 'data.message.code', data_get($body, 'code', 0));
            $message = strtolower((string) data_get($body, 'data.message.message', data_get($body, 'message', '')));
            if ($code !== 117 && ! str_contains($message, 'valid pin')) {
                throw new RuntimeException((string) data_get($body, 'data.message.message', 'Invalid phone verification code.'));
            }
        } catch (Throwable $exception) {
            Log::warning('Beem Africa OTP verify failed.', [
                'message' => $exception->getMessage(),
                'pin_id' => $pinId,
            ]);

            throw ValidationException::withMessages([
                'code' => ['The phone verification code is invalid or expired.'],
            ]);
        }
    }

    public function configured(): bool
    {
        return filled($this->accessKey())
            && filled($this->secretKey())
            && ($this->otpConfigured() || filled($this->sender()));
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

    private function otpConfigured(): bool
    {
        return $this->appId() > 0;
    }

    private function accessKey(): string
    {
        return trim((string) config('services.beem.access_key', ''));
    }

    private function secretKey(): string
    {
        return trim((string) config('services.beem.secret_key', ''));
    }

    private function appId(): int
    {
        return (int) config('services.beem.otp_app_id', 0);
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
        return preg_replace('/\D+/', '', $phone) ?? '';
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
            return 'Beem Africa rejected the OTP credentials. Check BEEM_ACCESS_KEY, BEEM_SECRET_KEY, and BEEM_OTP_APP_ID.';
        }

        return 'Beem Africa could not send the phone verification code. Please try again.';
    }
}
