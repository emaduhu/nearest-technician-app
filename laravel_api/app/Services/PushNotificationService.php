<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PushNotificationService
{
    public function send(?string $token, array $notification, array $data = []): bool
    {
        $serverKey = config('services.fcm.server_key');

        if (!$serverKey || !$token) {
            return false;
        }

        try {
            $response = Http::withToken($serverKey)
                ->timeout(10)
                ->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => $token,
                    'notification' => $notification,
                    'data' => collect($data)
                        ->map(fn ($value) => (string) $value)
                        ->all(),
                ]);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
