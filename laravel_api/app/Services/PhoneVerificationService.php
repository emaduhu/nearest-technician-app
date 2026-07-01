<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PhoneVerificationService
{
    public function __construct(
        private readonly AppSettingsService $settings,
        private readonly FirebasePhoneAuthService $firebase,
    ) {
    }

    /**
     * @return array{provider: string, uid: string|null, phone: string}
     */
    public function verifyRegistrationToken(string $token, string $expectedPhone, ?string $provider = null): array
    {
        $provider = $provider ?: $this->settings->smsProvider();
        if ($provider === AppSettingsService::SMS_PROVIDER_FIREBASE) {
            $verified = $this->firebase->verifyIdToken($token, $expectedPhone);

            return [
                'provider' => AppSettingsService::SMS_PROVIDER_FIREBASE,
                'uid' => $verified['uid'],
                'phone' => $verified['phone'],
            ];
        }

        if (in_array($provider, [
            AppSettingsService::SMS_PROVIDER_BEEM,
            AppSettingsService::SMS_PROVIDER_INFOBIP,
        ], true)) {
            return $this->verifyLocalToken($token, $expectedPhone);
        }

        throw ValidationException::withMessages([
            'phoneVerificationIdToken' => ['Verify your phone number before creating the account.'],
        ]);
    }

    public function issueLocalToken(string $phone, string $provider): string
    {
        $token = Str::random(64);
        Cache::put($this->cacheKey($token), [
            'provider' => $provider,
            'phone' => $this->normalizePhone($phone),
            'uid' => $provider.':'.hash('sha256', $this->normalizePhone($phone)),
        ], now()->addMinutes(30));

        return $token;
    }

    public function forgetLocalToken(string $token): void
    {
        Cache::forget($this->cacheKey($token));
    }

    /**
     * @return array{provider: string, uid: string|null, phone: string}
     */
    private function verifyLocalToken(string $token, string $expectedPhone): array
    {
        $payload = Cache::get($this->cacheKey($token));
        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'phoneVerificationIdToken' => ['Verify your phone number before creating the account.'],
            ]);
        }

        $phone = $this->normalizePhone((string) ($payload['phone'] ?? ''));
        if ($phone === '' || $phone !== $this->normalizePhone($expectedPhone)) {
            throw ValidationException::withMessages([
                'phoneVerificationIdToken' => ['Verify your phone number before creating the account.'],
            ]);
        }

        return [
            'provider' => (string) ($payload['provider'] ?? ''),
            'uid' => (string) ($payload['uid'] ?? ''),
            'phone' => $phone,
        ];
    }

    private function cacheKey(string $token): string
    {
        return 'phone_verification_token:'.hash('sha256', $token);
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }
}
