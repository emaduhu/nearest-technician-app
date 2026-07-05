<?php

namespace Tests\Feature;

use App\Services\InfobipOtpService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InfobipOtpServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.infobip.base_url', 'https://example.api.infobip.com');
        config()->set('services.infobip.api_key', 'test-api-key');
        config()->set('services.infobip.sender', 'GASCO');
        config()->set('services.infobip.otp_ttl_minutes', 10);
        config()->set('services.infobip.otp_message', 'Your Nearest Technician verification code is :code. It expires in :minutes minutes.');
    }

    public function test_it_sends_backend_generated_otp_through_infobip_sms_api(): void
    {
        Http::fake([
            'example.api.infobip.com/sms/3/messages' => Http::response([
                'messages' => [[
                    'messageId' => 'test-message-id',
                    'status' => [
                        'groupName' => 'PENDING',
                    ],
                ]],
            ]),
            'example.api.infobip.com/sms/3/logs*' => Http::response([
                'results' => [[
                    'messageId' => 'test-message-id',
                    'status' => [
                        'groupName' => 'PENDING',
                    ],
                    'error' => [
                        'id' => 0,
                    ],
                ]],
            ]),
        ]);

        $result = app(InfobipOtpService::class)->send('+255 712 345 678');

        $this->assertNotEmpty($result['verificationId']);
        $this->assertSame(10, app(InfobipOtpService::class)->expiryMinutes());
        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://example.api.infobip.com/sms/3/messages') {
                return false;
            }

            $payload = $request->data();

            return $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'App test-api-key')
                && $request->hasHeader('Content-Type', 'application/json')
                && data_get($payload, 'messages.0.destinations.0.to') === '255712345678'
                && data_get($payload, 'messages.0.sender') === 'GASCO'
                && preg_match('/Your Nearest Technician verification code is \d{6}\. It expires in 10 minutes\./', (string) data_get($payload, 'messages.0.content.text')) === 1;
        });
        Http::assertSent(function (Request $request): bool {
            return str_starts_with($request->url(), 'https://example.api.infobip.com/sms/3/logs')
                && $request->method() === 'GET'
                && $request['messageId'] === 'test-message-id'
                && $request['limit'] === 1;
        });
        Http::assertSentCount(2);
    }

    public function test_it_verifies_the_cached_sms_otp_pin(): void
    {
        $verificationId = 'test-verification-id';
        Cache::put('infobip_phone_verification:'.hash('sha256', $verificationId), [
            'phone' => '255712345678',
            'pin_hash' => hash('sha256', '123456'),
        ], now()->addMinutes(10));

        app(InfobipOtpService::class)->verify($verificationId, '123456', '+255 712 345 678');

        $this->assertFalse(Cache::has('infobip_phone_verification:'.hash('sha256', $verificationId)));
    }
}
