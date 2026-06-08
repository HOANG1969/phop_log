<?php

namespace App\Console\Commands;

use App\Services\ZaloZbsTokenService;
use Illuminate\Console\Command;

class ZaloZbsRefreshTokenCommand extends Command
{
    protected $signature = 'zalo:zbs-refresh-token {--force : Buoc refresh token ngay lap tuc}';

    protected $description = 'Lam moi access token Zalo ZBS tu refresh token';

    public function handle(ZaloZbsTokenService $tokenService): int
    {
        $force = (bool) $this->option('force');

        if (! $tokenService->refreshEnabled()) {
            $this->warn('ZBS refresh dang bi khoa boi cau hinh. Bat ZALO_ZNS_REFRESH_ENABLED=true tren VPS de cho phep refresh.');

            return self::FAILURE;
        }

        $token = $tokenService->refreshAccessToken($force);

        if (! is_string($token) || trim($token) === '') {
            $this->error('Khong the lam moi ZBS token. Kiem tra log va cau hinh refresh token.');

            return self::FAILURE;
        }

        $preview = substr($token, 0, 8) . '...';
        $this->info('ZBS access token hop le: ' . $preview);

        return self::SUCCESS;
    }
}
