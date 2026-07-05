<?php

namespace Tests\Feature;

use App\Services\BeemAfricaOtpService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BeemAfricaOtpServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.beem.sms_url', 'https://apisms.beem.africa/v1/send');
        config()->set('services.beem.otp_request_url', 'https://apiotp.beem.africa/v1/request');
        config()->set('services.beem.otp_verify_url', 'https://apiotp.beem.africa/v1/verify');
        config()->set('services.beem.otp_app_id', '4695');
        config()->set('services.beem.sender', 'GASCO');
        config()->set('services.beem.access_key', 'test-key');
        config()->set('services.beem.secret_key', 'test-secret');
        config()->set('services.beem.otp_ttl_minutes', 10);
        config()->set('services.beem.otp_message', 'Your Nearest Technician verification code is :code. It expires in :minutes minutes.');
    }

    public function test_it_requests_otp_pin_through_beem_otp_api(): void
    {
        Http::fake([
            'apiotp.beem.africa/v1/request' => Http::response([
                'data' => [
                    'pinId' => 'cdb29912-c874-4d8b-9f02-88b904ae4eeb',
                    'message' => [
                        'code' => 100,
                        'message' => 'SMS sent successfully',
                    ],
                ],
            ]),
        ]);

        $result = app(BeemAfricaOtpService::class)->send('0712 345 678');

        $this->assertSame('cdb29912-c874-4d8b-9f02-88b904ae4eeb', $result['pinId']);
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://apiotp.beem.africa/v1/request'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('test-key:test-secret'))
                && $request->hasHeader('Content-Type', 'application/json')
                && $payload === [
                    'appId' => '4695',
                    'msisdn' => '255712345678',
                ];
        });
        Http::assertSentCount(1);
    }

    public function test_it_sends_backend_generated_otp_through_beem_sms_api_when_no_otp_app_is_configured(): void
    {
        config()->set('services.beem.otp_app_id', '');

        Http::fake([
            'apisms.beem.africa/v1/send' => Http::response([
                'successful' => true,
                'request_id' => 35918915,
                'code' => 100,
                'message' => 'Message submitted successfully',
                'valid' => 1,
                'invalid' => 0,
            ]),
        ]);

        $result = app(BeemAfricaOtpService::class)->send('0712 345 678');

        $this->assertNotEmpty($result['pinId']);
        $this->assertSame(10, app(BeemAfricaOtpService::class)->expiryMinutes());
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://apisms.beem.africa/v1/send'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('test-key:test-secret'))
                && $request->hasHeader('Content-Type', 'application/json')
                && $payload['source_addr'] === 'GASCO'
                && $payload['encoding'] === 0
                && $payload['schedule_time'] === ''
                && preg_match('/Your Nearest Technician verification code is \d{6}\. It expires in 10 minutes\./', $payload['message']) === 1
                && $payload['recipients'] === [[
                    'recipient_id' => 1,
                    'dest_addr' => '255712345678',
                ]];
        });
        Http::assertSentCount(1);
    }

    public function test_it_rejects_invalid_beem_destination_responses(): void
    {
        config()->set('services.beem.otp_app_id', '');

        Http::fake([
            'apisms.beem.africa/v1/send' => Http::response([
                'successful' => true,
                'request_id' => 35918915,
                'code' => 100,
                'message' => 'Message submitted successfully',
                'valid' => 0,
                'invalid' => 1,
            ]),
        ]);

        $this->expectException(ValidationException::class);

        app(BeemAfricaOtpService::class)->send('0712 345 678');
    }

    public function test_it_verifies_beem_otp_api_pin(): void
    {
        Http::fake([
            'apiotp.beem.africa/v1/verify' => Http::response([
                'data' => [
                    'message' => [
                        'code' => 117,
                        'message' => 'Valid Pin',
                    ],
                ],
            ]),
        ]);

        app(BeemAfricaOtpService::class)->verify('test-pin-id', '123456', '+255 712 345 678');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://apiotp.beem.africa/v1/verify'
                && $request->method() === 'POST'
                && $request->data() === [
                    'pinId' => 'test-pin-id',
                    'pin' => '123456',
                ];
        });
    }

    public function test_it_verifies_the_cached_sms_otp_pin_when_no_otp_app_is_configured(): void
    {
        config()->set('services.beem.otp_app_id', '');

        $pinId = 'test-pin-id';
        Cache::put('beem_phone_verification:'.hash('sha256', $pinId), [
            'phone' => '255712345678',
            'pin_hash' => hash('sha256', '123456'),
        ], now()->addMinutes(10));

        app(BeemAfricaOtpService::class)->verify($pinId, '123456', '+255 712 345 678');

        $this->assertFalse(Cache::has('beem_phone_verification:'.hash('sha256', $pinId)));
    }
}
