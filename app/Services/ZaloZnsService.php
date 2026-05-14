<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZaloZnsService
{
    public function sendBookingConfirmation(string $phone, array $templateData, ?string $trackingId = null): bool
    {
        $endpoint = trim((string) config('services.zalo_zns.endpoint'));
        $accessToken = trim((string) config('services.zalo_zns.access_token'));
        $templateId = config('services.zalo_zns.template_id');
        $apiKey = trim((string) config('services.zalo_zns.api_key'));

        if ($endpoint === '' || $accessToken === '' || empty($templateId)) {
            Log::warning('Zalo ZNS configuration missing. Skip notification.', [
                'endpoint_set' => $endpoint !== '',
                'access_token_set' => $accessToken !== '',
                'template_id_set' => ! empty($templateId),
            ]);

            return false;
        }

        $normalizedPhone = $this->normalizePhone($phone);
        if ($normalizedPhone === null) {
            Log::warning('Invalid phone number for Zalo ZNS notification.', [
                'phone' => $phone,
            ]);

            return false;
        }

        $headers = [
            'access_token' => $accessToken,
        ];

        if ($apiKey !== '') {
            $headers['x-api-key'] = $apiKey;
        }

        $payload = [
            'phone' => $normalizedPhone,
            'template_id' => (string) $templateId,
            'template_data' => $templateData,
            'tracking_id' => $trackingId ?: 'booking_' . now()->format('YmdHis'),
        ];

        try {
            $response = Http::withHeaders($headers)
                ->timeout(15)
                ->acceptJson()
                ->post($endpoint, $payload);

            if (! $response->successful()) {
                Log::warning('Zalo ZNS notification failed.', [
                    'phone' => $normalizedPhone,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            $responseData = $response->json();
            if ((int) ($responseData['error'] ?? 0) !== 0) {
                Log::warning('Zalo ZNS API returned error.', [
                    'phone' => $normalizedPhone,
                    'response' => $responseData,
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Zalo ZNS notification exception.', [
                'phone' => $normalizedPhone,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0084')) {
            $digits = '84' . substr($digits, 4);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '84' . substr($digits, 1);
        } elseif (! str_starts_with($digits, '84')) {
            return null;
        }

        if (strlen($digits) < 10 || strlen($digits) > 12) {
            return null;
        }

        return $digits;
    }
}
