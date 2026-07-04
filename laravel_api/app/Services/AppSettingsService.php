<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AppSettingsService
{
    public const TECHNICIAN_REGISTRATION_FEE = 'technician_registration_fee';
    public const SMS_PROVIDER = 'sms_provider';
    public const MIN_TECHNICIAN_REGISTRATION_FEE = 5000;
    public const SMS_PROVIDER_FIREBASE = 'firebase';
    public const SMS_PROVIDER_BEEM = 'beem_africa';
    public const SMS_PROVIDER_INFOBIP = 'infobip';
    public const SMS_PROVIDERS = [
        self::SMS_PROVIDER_FIREBASE,
        self::SMS_PROVIDER_BEEM,
        self::SMS_PROVIDER_INFOBIP,
    ];

    public function technicianRegistrationFee(): int
    {
        $fallback = (int) config('services.clickpesa.technician_registration_fee', self::MIN_TECHNICIAN_REGISTRATION_FEE);

        return max(
            $this->integer(self::TECHNICIAN_REGISTRATION_FEE, $fallback),
            self::MIN_TECHNICIAN_REGISTRATION_FEE
        );
    }

    public function setTechnicianRegistrationFee(int $amount): int
    {
        $amount = max($amount, self::MIN_TECHNICIAN_REGISTRATION_FEE);

        if (! $this->settingsTableExists()) {
            return $amount;
        }

        $now = now();

        try {
            DB::table('app_settings')->updateOrInsert(
                ['key' => self::TECHNICIAN_REGISTRATION_FEE],
                [
                    'value' => (string) $amount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        } catch (Throwable) {
            return $amount;
        }

        return $amount;
    }

    public function smsProvider(): string
    {
        $provider = $this->string(self::SMS_PROVIDER, (string) config('services.sms.provider', self::SMS_PROVIDER_FIREBASE));

        return in_array($provider, self::SMS_PROVIDERS, true)
            ? $provider
            : self::SMS_PROVIDER_FIREBASE;
    }

    public function setSmsProvider(string $provider): string
    {
        $provider = in_array($provider, self::SMS_PROVIDERS, true)
            ? $provider
            : self::SMS_PROVIDER_FIREBASE;

        if (! $this->settingsTableExists()) {
            return $provider;
        }

        $this->set(self::SMS_PROVIDER, $provider);

        return $provider;
    }

    /**
     * @return array<string, string>
     */
    public function smsProviderOptions(): array
    {
        return [
            self::SMS_PROVIDER_FIREBASE => 'Firebase',
            self::SMS_PROVIDER_BEEM => 'Beem Africa',
            self::SMS_PROVIDER_INFOBIP => 'Infobip',
        ];
    }

    private function integer(string $key, int $default): int
    {
        if (! $this->settingsTableExists()) {
            return $default;
        }

        try {
            $value = DB::table('app_settings')
                ->where('key', $key)
                ->value('value');
        } catch (Throwable) {
            return $default;
        }

        return is_numeric($value) ? (int) $value : $default;
    }

    private function string(string $key, string $default): string
    {
        if (! $this->settingsTableExists()) {
            return $default;
        }

        try {
            $value = DB::table('app_settings')
                ->where('key', $key)
                ->value('value');
        } catch (Throwable) {
            return $default;
        }

        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : $default;
    }

    private function settingsTableExists(): bool
    {
        try {
            return Schema::hasTable('app_settings');
        } catch (Throwable) {
            return false;
        }
    }

    private function set(string $key, string $value): void
    {
        $now = now();

        try {
            DB::table('app_settings')->updateOrInsert(
                ['key' => $key],
                [
                    'value' => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        } catch (Throwable) {
            //
        }
    }
}
