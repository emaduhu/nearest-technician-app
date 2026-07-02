<?php

namespace Tests\Feature;

use App\Services\BeemAfricaOtpService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BeemAfricaOtpServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.beem.sms_url', 'https://apisms.beem.africa/v1/send');
        config()->set('services.beem.sender', 'GASCO');
        config()->set('services.beem.access_key', 'test-key');
        config()->set('services.beem.secret_key', 'test-secret');
    }

    public function test_it_sends_backend_generated_otp_through_beem_sms_api(): void
    {
        Http::fake([
            'apisms.beem.africa/v1/send' => Http::response([
                'successful' => true,
                'code' => 100,
                'message' => 'Message submitted successfully',
            ]),
        ]);

        $result = app(BeemAfricaOtpService::class)->send('0712 345 678');

        $this->assertNotEmpty($result['pinId']);
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://apisms.beem.africa/v1/send'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('test-key:test-secret'))
                && $request->hasHeader('Content-Type', 'application/json')
                && $payload['source_addr'] === 'GASCO'
                && $payload['encoding'] === 0
                && $payload['schedule_time'] === ''
                && preg_match('/Your Nearest Technician verification code is \d{6}/', $payload['message']) === 1
                && $payload['recipients'] === [[
                    'recipient_id' => 1,
                    'dest_addr' => '255712345678',
                ]];
        });
        Http::assertSentCount(1);
    }

    public function test_it_verifies_the_cached_sms_otp_pin(): void
    {
        $pinId = 'test-pin-id';
        Cache::put('beem_phone_verification:'.hash('sha256', $pinId), [
            'phone' => '255712345678',
            'pin_hash' => hash('sha256', '123456'),
        ], now()->addMinutes(10));

        app(BeemAfricaOtpService::class)->verify($pinId, '123456', '+255 712 345 678');

        $this->assertFalse(Cache::has('beem_phone_verification:'.hash('sha256', $pinId)));
    }
}
