<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class ClickPesaPaymentService
{
    public function configured(): bool
    {
        return filled(config('services.clickpesa.client_id')) && filled(config('services.clickpesa.api_key'));
    }

    public function technicianRegistrationFee(): int
    {
        return max((int) config('services.clickpesa.technician_registration_fee', 5000), 0);
    }

    public function initiateTechnicianRegistrationPayment(string $phoneNumber, string $orderReference): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('ClickPesa credentials are not configured.');
        }

        $amount = $this->technicianRegistrationFee();
        if ($amount <= 0) {
            throw new RuntimeException('Technician registration fee must be greater than zero.');
        }

        $response = $this->client()
            ->withHeader('Authorization', $this->authorizationToken())
            ->post('/payments/initiate-ussd-push-request', [
                'amount' => (string) $amount,
                'currency' => config('services.clickpesa.currency', 'TZS'),
                'orderReference' => $orderReference,
                'phoneNumber' => $this->normalizePhoneNumber($phoneNumber),
            ]);

        if ($response->failed()) {
            throw new RuntimeException($response->json('message') ?: 'ClickPesa payment request failed.');
        }

        return $response->json() ?? [];
    }

    public function paymentStatus(string $orderReference): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('ClickPesa credentials are not configured.');
        }

        $response = $this->client()
            ->withHeader('Authorization', $this->authorizationToken())
            ->get("/payments/{$orderReference}");

        if ($response->failed()) {
            throw new RuntimeException($response->json('message') ?: 'ClickPesa payment status request failed.');
        }

        return $response->json() ?? [];
    }

    public function newOrderReference(int|string $technicianId): string
    {
        return sprintf('TECH-REG-%s-%s', $technicianId, Str::upper(Str::random(8)));
    }

    private function authorizationToken(): string
    {
        $response = $this->client()
            ->withHeaders([
                'client-id' => config('services.clickpesa.client_id'),
                'api-key' => config('services.clickpesa.api_key'),
            ])
            ->post('/generate-token');

        if ($response->failed() || blank($response->json('token'))) {
            throw new RuntimeException($response->json('message') ?: 'ClickPesa authorization failed.');
        }

        return $response->json('token');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.clickpesa.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }

    private function normalizePhoneNumber(string $phoneNumber): string
    {
        $phoneNumber = preg_replace('/\D+/', '', $phoneNumber) ?? '';

        if (str_starts_with($phoneNumber, '0')) {
            return '255'.substr($phoneNumber, 1);
        }

        if (strlen($phoneNumber) === 9) {
            return '255'.$phoneNumber;
        }

        return $phoneNumber;
    }
}
