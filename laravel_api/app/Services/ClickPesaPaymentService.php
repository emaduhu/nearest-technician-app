<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class ClickPesaPaymentService
{
    public function configured(): bool
    {
        return filled($this->clientId()) && filled($this->apiKey());
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
        return sprintf('TECHREG%s%s', $technicianId, Str::upper(Str::random(10)));
    }

    private function authorizationToken(): string
    {
        $response = $this->client()
            ->withHeaders([
                'client-id' => $this->clientId(),
                'api-key' => $this->apiKey(),
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

    private function clientId(): ?string
    {
        return $this->secret('client_id');
    }

    private function apiKey(): ?string
    {
        return $this->secret('api_key');
    }

    private function secret(string $key): ?string
    {
        $encrypted = config("services.clickpesa.{$key}_encrypted");
        if (filled($encrypted)) {
            return Crypt::decryptString((string) $encrypted);
        }

        $plain = config("services.clickpesa.{$key}");

        return filled($plain) ? (string) $plain : null;
    }

    private function normalizePhoneNumber(string $phoneNumber): string
    {
        $phoneNumber = preg_replace('/\D+/', '', $phoneNumber) ?? '';

        if (str_starts_with($phoneNumber, '255')) {
            return $phoneNumber;
        }

        if (str_starts_with($phoneNumber, '0')) {
            return '255'.substr($phoneNumber, 1);
        }

        if (strlen($phoneNumber) === 9) {
            return '255'.$phoneNumber;
        }

        return $phoneNumber;
    }
}
