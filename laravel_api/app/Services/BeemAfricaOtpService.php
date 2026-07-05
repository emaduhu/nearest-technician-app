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

        if ($this->usesOtpApi()) {
            return $this->requestOtpPin($phone);
        }

        return $this->sendSmsOtp($phone);
    }

    public function verify(string $pinId, string $pin, ?string $phone = null): void
    {
        $this->ensureConfigured();

        if ($this->usesOtpApi()) {
            $this->verifyOtpPin($pinId, $pin);

            return;
        }

        $this->verifySmsOtp($pinId, $pin, (string) $phone);
    }

    public function configured(): bool
    {
        return filled($this->accessKey())
            && filled($this->secretKey())
            && ($this->usesOtpApi() || filled($this->sender()));
    }

    public function expiryMinutes(): int
    {
        return $this->ttlMinutes();
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

            $validRecipients = (int) data_get($body, 'valid', 0);
            $invalidRecipients = (int) data_get($body, 'invalid', 0);
            if ($validRecipients === 0 && $invalidRecipients > 0) {
                throw new RuntimeException((string) data_get($body, 'message', 'Beem Africa rejected the destination phone number.'));
            }

            Cache::put($this->cacheKey($pinId), [
                'phone' => $normalizedPhone,
                'pin_hash' => hash('sha256', $pin),
            ], now()->addMinutes($this->ttlMinutes()));

            Log::info('Beem Africa SMS OTP submitted.', [
                'request_id' => data_get($body, 'request_id'),
                'message' => data_get($body, 'message'),
                'valid' => $validRecipients,
                'invalid' => $invalidRecipients,
                'phone' => $this->phoneHint($phone),
                'sender' => $this->sender(),
            ]);

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

    /**
     * @return array{pinId: string, response: array<string, mixed>}
     */
    private function requestOtpPin(string $phone): array
    {
        $normalizedPhone = $this->normalizePhone($phone);

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withBasicAuth($this->accessKey(), $this->secretKey())
                ->timeout(15)
                ->post($this->otpRequestUrl(), [
                    'appId' => $this->otpAppId(),
                    'msisdn' => $normalizedPhone,
                ]);

            $body = $response->json();
            if ($response->failed() || ! is_array($body)) {
                throw new RuntimeException($this->responseFailureMessage($body, $response->body()));
            }

            $pinId = (string) data_get($body, 'data.pinId', '');
            $code = (int) data_get($body, 'data.message.code', data_get($body, 'code', 0));
            if ($pinId === '' || $code !== 100) {
                throw new RuntimeException($this->responseFailureMessage($body, 'Beem Africa OTP request failed.'));
            }

            Log::info('Beem Africa OTP PIN requested.', [
                'pin_id' => $pinId,
                'message' => data_get($body, 'data.message.message'),
                'code' => $code,
                'phone' => $this->phoneHint($phone),
                'app_id' => $this->otpAppId(),
            ]);

            return ['pinId' => $pinId, 'response' => $body];
        } catch (Throwable $exception) {
            Log::warning('Beem Africa OTP request failed.', [
                'message' => $exception->getMessage(),
                'phone' => $this->phoneHint($phone),
                'app_id' => $this->otpAppId(),
            ]);

            throw ValidationException::withMessages([
                'phone' => [$this->publicFailureMessage($exception->getMessage())],
            ]);
        }
    }

    private function verifyOtpPin(string $pinId, string $pin): void
    {
        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withBasicAuth($this->accessKey(), $this->secretKey())
                ->timeout(15)
                ->post($this->otpVerifyUrl(), [
                    'pinId' => $pinId,
                    'pin' => $pin,
                ]);

            $body = $response->json();
            $code = (int) data_get($body, 'data.message.code', data_get($body, 'code', 0));
            if ($response->failed() || ! is_array($body) || $code !== 117) {
                throw new RuntimeException($this->responseFailureMessage($body, 'Beem Africa OTP verification failed.'));
            }
        } catch (Throwable $exception) {
            Log::warning('Beem Africa OTP verify failed.', [
                'message' => $exception->getMessage(),
                'pin_id' => $pinId,
            ]);

            throw ValidationException::withMessages([
                'code' => [$this->publicVerificationFailureMessage($exception->getMessage())],
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

    private function usesOtpApi(): bool
    {
        return filled($this->otpAppId());
    }

    private function otpAppId(): string
    {
        return trim((string) config('services.beem.otp_app_id', ''));
    }

    private function otpRequestUrl(): string
    {
        return trim((string) config('services.beem.otp_request_url', 'https://apiotp.beem.africa/v1/request'));
    }

    private function otpVerifyUrl(): string
    {
        return trim((string) config('services.beem.otp_verify_url', 'https://apiotp.beem.africa/v1/verify'));
    }

    private function ttlMinutes(): int
    {
        return max((int) config('services.beem.otp_ttl_minutes', 10), 1);
    }

    private function message(string $pin): string
    {
        $template = (string) config(
            'services.beem.otp_message',
            'Your Nearest Technician verification code is :code. It expires in :minutes minutes.'
        );

        return str_replace(
            [':code', ':minutes'],
            [$pin, (string) $this->ttlMinutes()],
            $template
        );
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

        if (str_contains($lower, 'sender')) {
            return 'Beem Africa rejected the sender ID. Check the approved Beem sender/template for this account.';
        }

        return 'Beem Africa could not send the phone verification code. Please try again.';
    }

    private function publicVerificationFailureMessage(string $message): string
    {
        $lower = strtolower($message);
        if (str_contains($lower, 'timeout') || str_contains($lower, 'expired')) {
            return 'The phone verification code has expired. Please request a new code.';
        }

        if (str_contains($lower, 'attempt')) {
            return 'Too many incorrect verification attempts. Please request a new code.';
        }

        return 'The phone verification code is invalid or expired.';
    }

    /**
     * @param  array<string, mixed>|mixed  $body
     */
    private function responseFailureMessage(mixed $body, string $fallback): string
    {
        if (is_array($body)) {
            $message = data_get($body, 'data.message.message')
                ?? data_get($body, 'data.message')
                ?? data_get($body, 'data.error_code')
                ?? data_get($body, 'message');

            if (filled($message)) {
                $code = data_get($body, 'data.message.code') ?? data_get($body, 'code');

                return filled($code) ? "{$code} {$message}" : (string) $message;
            }
        }

        return $fallback;
    }
}
