<?php

namespace App\Console\Commands;

use App\Models\MeetingBooking;
use App\Services\ZaloZbsTokenService;
use App\Services\ZaloZnsService;
use Illuminate\Console\Command;

class ZaloZnsTestCommand extends Command
{
    protected $signature = 'zalo:zns-test
        {phone : Phone number to receive ZNS test message}
        {--booking_id= : Optional booking ID to build template data from real booking}
        {--datetime= : Optional datetime value to override template datetime field}
        {--diag : Print config/token diagnostics before sending}';

    protected $description = 'Send a direct Zalo ZNS test and print API response details';

    public function handle(ZaloZnsService $znsService, ZaloZbsTokenService $tokenService): int
    {
        $phone = (string) $this->argument('phone');
        $bookingId = $this->option('booking_id');

        $templateData = $this->buildTemplateData($bookingId);
        $datetimeOverride = trim((string) ($this->option('datetime') ?? ''));
        if ($datetimeOverride !== '') {
            $templateData['datetime'] = $datetimeOverride;
        }

        if ((bool) $this->option('diag')) {
            $this->printDiagnostics($tokenService);
        }

        $this->line('Phone      : ' . $phone);
        $this->line('Template   : ' . json_encode($templateData, JSON_UNESCAPED_UNICODE));
        $this->line('Sending...');

        $result = $znsService->debugSendBookingConfirmation($phone, $templateData, 'cli_test_' . now()->format('YmdHis'));

        $this->line('OK         : ' . (($result['ok'] ?? false) ? 'true' : 'false'));
        $this->line('HTTP status: ' . (int) ($result['status'] ?? 0));
        $this->line('Error      : ' . (($result['error'] ?? null) ?: 'none'));
        $this->line('Transport  : ' . (($result['transport_error'] ?? null) ?: 'none'));
        $this->line('Response   : ' . json_encode($result['response_data'] ?? null, JSON_UNESCAPED_UNICODE));

        return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{name:string,datetime:string,department:string,content:string}
     */
    private function buildTemplateData(mixed $bookingId): array
    {
        if ($bookingId !== null && $bookingId !== '') {
            $booking = MeetingBooking::find((int) $bookingId);
            if ($booking) {
                return [
                    'name' => $this->limit((string) $booking->organizer_name, 60),
                    'datetime' => $booking->start_at?->copy()->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y')
                        ?? now()->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y'),
                    'department' => $this->limit((string) $booking->organizer_department, 60),
                    'content' => 'Dang cho phe duyet.',
                ];
            }
        }

        return [
            'name' => 'TEST ZNS',
            'datetime' => now()->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y'),
            'department' => 'KVP',
            'content' => 'Test gui thong bao.',
        ];
    }

    private function limit(string $value, int $maxLength): string
    {
        $value = trim($value);
        if ($value === '') {
            return '-';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength);
        }

        return substr($value, 0, $maxLength);
    }

    private function printDiagnostics(ZaloZbsTokenService $tokenService): void
    {
        $endpoint = trim((string) config('services.zalo_zns.endpoint', ''));
        $templateId = (string) config('services.zalo_zns.template_id', '');
        $apiKey = trim((string) config('services.zalo_zns.api_key', ''));
        $verifySsl = filter_var(config('services.zalo_zns.verify_ssl', true), FILTER_VALIDATE_BOOL);
        $token = trim((string) ($tokenService->getAccessToken() ?? ''));

        $tokenPreview = $token !== '' ? substr($token, 0, 8) . '...' : '(empty)';

        $this->line('Diag endpoint    : ' . ($endpoint !== '' ? $endpoint : '(empty)'));
        $this->line('Diag template_id : ' . ($templateId !== '' ? $templateId : '(empty)'));
        $this->line('Diag api_key_set : ' . ($apiKey !== '' ? 'true' : 'false'));
        $this->line('Diag refresh_on  : ' . ($tokenService->refreshEnabled() ? 'true' : 'false'));
        $this->line('Diag verify_ssl  : ' . ($verifySsl ? 'true' : 'false'));
        $this->line('Diag token       : ' . $tokenPreview . ' len=' . strlen($token));
    }
}
