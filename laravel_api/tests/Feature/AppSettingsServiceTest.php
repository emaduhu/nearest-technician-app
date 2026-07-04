<?php

namespace Tests\Feature;

use App\Services\AppSettingsService;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class AppSettingsServiceTest extends TestCase
{
    public function test_sms_provider_falls_back_to_config_when_settings_table_is_unreachable(): void
    {
        config()->set('services.sms.provider', AppSettingsService::SMS_PROVIDER_BEEM);

        Schema::shouldReceive('hasTable')
            ->with('app_settings')
            ->andThrow(new RuntimeException('Database unavailable.'));

        $this->assertSame(
            AppSettingsService::SMS_PROVIDER_BEEM,
            app(AppSettingsService::class)->smsProvider(),
        );
    }

    public function test_setting_sms_provider_does_not_crash_when_settings_table_is_unreachable(): void
    {
        Schema::shouldReceive('hasTable')
            ->with('app_settings')
            ->andThrow(new RuntimeException('Database unavailable.'));

        $this->assertSame(
            AppSettingsService::SMS_PROVIDER_BEEM,
            app(AppSettingsService::class)->setSmsProvider(AppSettingsService::SMS_PROVIDER_BEEM),
        );
    }
}
