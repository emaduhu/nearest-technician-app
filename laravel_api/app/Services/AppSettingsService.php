<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AppSettingsService
{
    public const TECHNICIAN_REGISTRATION_FEE = 'technician_registration_fee';
    public const MIN_TECHNICIAN_REGISTRATION_FEE = 5000;

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

        if (! Schema::hasTable('app_settings')) {
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

    private function integer(string $key, int $default): int
    {
        if (! Schema::hasTable('app_settings')) {
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
}
