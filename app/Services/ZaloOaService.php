<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZaloOaService
{
    public function sendBookingNotification(string $zaloUserId, array $bookingData): bool
    {
        $endpoint = (string) config('services.zalo_oa.message_url');
        $accessToken = (string) config('services.zalo_oa.access_token');

        if ($endpoint === '' || $accessToken === '') {
            Log::warning('Zalo OA configuration missing. Skip notification.', [
                'endpoint_set' => $endpoint !== '',
                'token_set' => $accessToken !== '',
            ]);

            return false;
        }

        $normalizedUserId = trim($zaloUserId);

        if ($normalizedUserId === '') {
            Log::warning('Invalid phone number for Zalo OA notification.', [
                'zalo_user_id' => $zaloUserId,
            ]);

            return false;
        }

        $message = sprintf(
            "[%s] %s\nPhong: %s\nThoi gian: %s\nNguoi dang ky: %s\nTrang thai: %s",
            $bookingData['app_name'] ?? 'PHOP LOG',
            $bookingData['title'] ?? 'Dang ky phong hop',
            $bookingData['room_name'] ?? '-',
            $bookingData['time_label'] ?? '-',
            $bookingData['organizer_name'] ?? '-',
            strtoupper((string) ($bookingData['status'] ?? 'pending'))
        );

        $payload = [
            'recipient' => [
                'user_id' => $normalizedUserId,
            ],
            'message' => [
                'text' => $message,
            ],
        ];

        try {
            $response = Http::withHeaders([
                'access_token' => $accessToken,
            ])
                ->timeout(15)
                ->acceptJson()
                ->post($endpoint, $payload);

            if (! $response->successful()) {
                Log::warning('Zalo OA notification failed.', [
                    'zalo_user_id' => $normalizedUserId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }
        } catch (\Throwable $e) {
            Log::error('Zalo OA notification exception.', [
                'zalo_user_id' => $normalizedUserId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }
}
