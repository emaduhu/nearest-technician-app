<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    public function verify(string $pinId, string $pin): void
    {
        $this->ensureConfigured();

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
            && $this->appId() > 0;
    }

    private function ensureConfigured(): void
    {
        if (! $this->configured()) {
            throw ValidationException::withMessages([
                'phone' => ['Beem Africa OTP is not configured.'],
            ]);
        }
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
