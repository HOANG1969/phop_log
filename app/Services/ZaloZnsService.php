<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZaloZnsService
{
    public function __construct(private readonly ZaloZbsTokenService $tokenService)
    {
    }

    public function sendBookingConfirmation(string $phone, array $templateData, ?string $trackingId = null): bool
    {
        $endpoint = trim((string) config('services.zalo_zns.endpoint'));
        $accessToken = trim((string) ($this->tokenService->getAccessToken() ?? ''));
        $templateId = config('services.zalo_zns.template_id');
        $apiKey = trim((string) config('services.zalo_zns.api_key'));
        $verifySsl = filter_var(config('services.zalo_zns.verify_ssl', true), FILTER_VALIDATE_BOOL);

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

        $payload = [
            'phone' => $normalizedPhone,
            'template_id' => (string) $templateId,
            'template_data' => $templateData,
            'tracking_id' => $trackingId ?: 'booking_' . now()->format('YmdHis'),
        ];

        $result = $this->dispatchTemplateRequest($endpoint, $accessToken, $apiKey, $verifySsl, $payload, $normalizedPhone);

        if (! $result['ok'] && $this->isTokenError($result['response_data'], $result['status'])) {
            $newAccessToken = $this->tokenService->refreshAccessToken(true);
            if (is_string($newAccessToken) && trim($newAccessToken) !== '') {
                $result = $this->dispatchTemplateRequest($endpoint, trim($newAccessToken), $apiKey, $verifySsl, $payload, $normalizedPhone);
            }
        }

        return $result['ok'];
    }

    /**
     * @return array{ok:bool,status:int,response_data:array<string,mixed>|null}
     */
    private function dispatchTemplateRequest(
        string $endpoint,
        string $accessToken,
        string $apiKey,
        bool $verifySsl,
        array $payload,
        string $normalizedPhone
    ): array {
        $headers = [
            'access_token' => $accessToken,
        ];

        if ($apiKey !== '') {
            $headers['x-api-key'] = $apiKey;
        }

        try {
            $request = Http::withHeaders($headers)
                ->timeout(15)
                ->acceptJson();

            if (! $verifySsl) {
                $request = $request->withOptions(['verify' => false]);
            }

            $response = $request->post($endpoint, $payload);
            $status = $response->status();
            $responseData = $response->json();

            if (! $response->successful()) {
                Log::warning('Zalo ZNS notification failed.', [
                    'phone' => $normalizedPhone,
                    'status' => $status,
                    'body' => $response->body(),
                ]);

                return [
                    'ok' => false,
                    'status' => $status,
                    'response_data' => is_array($responseData) ? $responseData : null,
                ];
            }

            if ((int) ($responseData['error'] ?? 0) !== 0) {
                Log::warning('Zalo ZNS API returned error.', [
                    'phone' => $normalizedPhone,
                    'response' => $responseData,
                ]);

                return [
                    'ok' => false,
                    'status' => $status,
                    'response_data' => is_array($responseData) ? $responseData : null,
                ];
            }

            return [
                'ok' => true,
                'status' => $status,
                'response_data' => is_array($responseData) ? $responseData : null,
            ];
        } catch (\Throwable $e) {
            Log::error('Zalo ZNS notification exception.', [
                'phone' => $normalizedPhone,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'status' => 0,
                'response_data' => null,
            ];
        }
    }

    /**
     * @param array<string,mixed>|null $responseData
     */
    private function isTokenError(?array $responseData, int $status): bool
    {
        if (in_array($status, [401, 403], true)) {
            return true;
        }

        if (! is_array($responseData)) {
            return false;
        }

        $message = strtolower((string) ($responseData['message'] ?? ''));
        $tokenHints = ['token', 'expired', 'invalid'];

        foreach ($tokenHints as $hint) {
            if (str_contains($message, $hint)) {
                return true;
            }
        }

        return false;
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
