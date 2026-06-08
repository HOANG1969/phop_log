<?php

namespace App\Services;

use App\Models\IntegrationToken;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ZaloZbsTokenService
{
    private const PROVIDER = 'zalo_zns';

    public function refreshEnabled(): bool
    {
        return filter_var(config('services.zalo_zns.refresh_enabled', false), FILTER_VALIDATE_BOOL);
    }

    public function getAccessToken(): ?string
    {
        $state = $this->getTokenState();

        if ($state === null) {
            return $this->configAccessToken();
        }

        if ($this->needsRefresh($state) && $this->refreshEnabled()) {
            $refreshed = $this->refreshAccessToken();
            if ($refreshed !== null) {
                return $refreshed;
            }
        }

        return $state->access_token ?: $this->configAccessToken();
    }

    public function refreshAccessToken(bool $force = false): ?string
    {
        if (! $this->refreshEnabled()) {
            Log::info('Zalo ZBS token refresh skipped because refresh is disabled by configuration.');

            return $force ? null : ($this->getTokenState()?->access_token ?: $this->configAccessToken());
        }

        $state = $this->getTokenState();

        if ($state === null) {
            return $this->refreshWithoutPersistence($force);
        }

        if (! $force && ! $this->needsRefresh($state)) {
            return $state->access_token ?: $this->configAccessToken();
        }

        $configRefreshToken = $this->configRefreshToken();
        $refreshToken = $this->resolveRefreshToken($state->refresh_token);
        if ($refreshToken === null) {
            Log::warning('Zalo ZBS refresh token is missing.');

            if ($force) {
                return null;
            }

            return $state->access_token ?: $this->configAccessToken();
        }

        $refreshed = $this->requestNewToken($refreshToken);

        if (
            $refreshed === null
            && is_string($configRefreshToken)
            && trim($configRefreshToken) !== ''
            && trim($configRefreshToken) !== trim((string) $refreshToken)
        ) {
            Log::warning('Retry Zalo ZBS refresh with .env refresh token due to DB refresh failure.');

            $refreshed = $this->requestNewToken(trim($configRefreshToken));
        }

        if ($refreshed === null) {
            if ($force) {
                return null;
            }

            return $state->access_token ?: $this->configAccessToken();
        }

        $state->access_token = $refreshed['access_token'];
        if (! empty($refreshed['refresh_token'])) {
            $state->refresh_token = $refreshed['refresh_token'];
        }
        $state->expires_at = $refreshed['expires_at'];
        $state->meta = [
            'last_refreshed_at' => now()->toIso8601String(),
            'expires_in' => $refreshed['expires_in'],
        ];
        $state->save();

        return $state->access_token;
    }

    private function refreshWithoutPersistence(bool $force): ?string
    {
        if (! $this->refreshEnabled()) {
            return $force ? null : $this->configAccessToken();
        }

        $accessToken = $this->configAccessToken();

        if (! $force && $accessToken !== null) {
            return $accessToken;
        }

        $refreshToken = $this->resolveRefreshToken(null);
        if ($refreshToken === null) {
            if ($force) {
                return null;
            }

            return $accessToken;
        }

        $refreshed = $this->requestNewToken($refreshToken);

        if ($refreshed === null && $force) {
            return null;
        }

        return $refreshed['access_token'] ?? $accessToken;
    }

    private function getTokenState(): ?IntegrationToken
    {
        if (! $this->canPersistTokenState()) {
            return null;
        }

        try {
            $state = IntegrationToken::query()->firstOrCreate(
                ['provider' => self::PROVIDER],
                [
                    'access_token' => $this->configAccessToken(),
                    'refresh_token' => $this->configRefreshToken(),
                    'expires_at' => $this->configAccessTokenExpiresAt(),
                ]
            );

            if (empty($state->refresh_token) && $this->configRefreshToken() !== null) {
                $state->refresh_token = $this->configRefreshToken();
                $state->save();
            }

            if (empty($state->access_token) && $this->configAccessToken() !== null) {
                $state->access_token = $this->configAccessToken();
                $state->expires_at = $this->configAccessTokenExpiresAt();
                $state->save();
            }

            return $state;
        } catch (QueryException $e) {
            Log::warning('Unable to read integration token state.', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function canPersistTokenState(): bool
    {
        try {
            return Schema::hasTable('integration_tokens');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function needsRefresh(IntegrationToken $state): bool
    {
        if (empty($state->access_token)) {
            return true;
        }

        if (! $state->expires_at instanceof Carbon) {
            return false;
        }

        $leadTime = (int) config('services.zalo_zns.refresh_before_seconds', 300);

        return $state->expires_at->lte(now()->addSeconds(max(0, $leadTime)));
    }

    private function requestNewToken(string $refreshToken): ?array
    {
        $endpoint = trim((string) config('services.zalo_zns.token_endpoint', ''));
        $appId = trim((string) config('services.zalo_zns.app_id', ''));
        $appSecret = trim((string) config('services.zalo_zns.app_secret', ''));
        $verifySsl = filter_var(config('services.zalo_zns.verify_ssl', true), FILTER_VALIDATE_BOOL);

        if ($endpoint === '' || $appId === '' || $appSecret === '') {
            Log::warning('Zalo ZBS token refresh configuration missing.', [
                'endpoint_set' => $endpoint !== '',
                'app_id_set' => $appId !== '',
                'app_secret_set' => $appSecret !== '',
            ]);

            return null;
        }

        try {
            $request = Http::asForm()
                ->withHeaders(['secret_key' => $appSecret])
                ->timeout(15)
                ->acceptJson();

            if (! $verifySsl) {
                $request = $request->withOptions(['verify' => false]);
            }

            $response = $request->post($endpoint, [
                'app_id' => $appId,
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

            if (! $response->successful()) {
                Log::warning('Zalo ZBS token refresh request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $responseData = $response->json();
            if ((int) ($responseData['error'] ?? 0) !== 0) {
                Log::warning('Zalo ZBS token refresh API returned error.', [
                    'response' => $responseData,
                ]);

                return null;
            }

            $accessToken = (string) ($responseData['access_token'] ?? $responseData['data']['access_token'] ?? '');
            if ($accessToken === '') {
                Log::warning('Zalo ZBS token refresh response missing access token.', [
                    'response' => $responseData,
                ]);

                return null;
            }

            $nextRefreshToken = (string) ($responseData['refresh_token'] ?? $responseData['data']['refresh_token'] ?? '');
            $expiresIn = (int) ($responseData['expires_in'] ?? $responseData['data']['expires_in'] ?? 0);
            $expiresAt = $expiresIn > 0 ? now()->addSeconds($expiresIn) : null;

            return [
                'access_token' => $accessToken,
                'refresh_token' => $nextRefreshToken !== '' ? $nextRefreshToken : null,
                'expires_in' => $expiresIn,
                'expires_at' => $expiresAt,
            ];
        } catch (\Throwable $e) {
            Log::error('Zalo ZBS token refresh exception.', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function resolveRefreshToken(?string $dbRefreshToken): ?string
    {
        $token = trim((string) ($dbRefreshToken ?: $this->configRefreshToken()));

        return $token !== '' ? $token : null;
    }

    private function configAccessToken(): ?string
    {
        $token = trim((string) config('services.zalo_zns.access_token', ''));

        return $token !== '' ? $token : null;
    }

    private function configRefreshToken(): ?string
    {
        $token = trim((string) config('services.zalo_zns.refresh_token', ''));

        return $token !== '' ? $token : null;
    }

    private function configAccessTokenExpiresAt(): ?Carbon
    {
        $expiresAt = trim((string) config('services.zalo_zns.access_token_expires_at', ''));
        if ($expiresAt === '') {
            return null;
        }

        try {
            return Carbon::parse($expiresAt);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
