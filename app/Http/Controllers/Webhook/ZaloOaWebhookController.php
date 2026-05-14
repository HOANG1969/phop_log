<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZaloOaWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        if (! $this->isAuthorized($request)) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized webhook token.',
            ], 401);
        }

        $senderId = $this->extractSenderId($request);
        $messageText = $this->extractMessageText($request);

        if ($senderId === null || $messageText === null) {
            return response()->json([
                'ok' => true,
                'message' => 'Ignored: missing sender or message text.',
            ]);
        }

        $phone = $this->extractPhone($messageText);
        if ($phone === null) {
            return response()->json([
                'ok' => true,
                'message' => 'Ignored: no phone number in message.',
            ]);
        }

        $admin = $this->findAdminByPhone($phone);
        if (! $admin) {
            Log::info('Zalo webhook received phone not mapped to any admin.', [
                'phone' => $phone,
                'sender_id' => $senderId,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'No matching admin by phone.',
                'phone' => $phone,
            ]);
        }

        $admin->zalo_user_id = $senderId;
        $admin->save();

        Log::info('Mapped admin zalo_user_id from webhook.', [
            'user_id' => $admin->id,
            'phone' => $admin->phone,
            'zalo_user_id' => $senderId,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Mapped admin zalo_user_id successfully.',
            'user_id' => $admin->id,
            'phone' => $admin->phone,
            'zalo_user_id' => $senderId,
        ]);
    }

    private function isAuthorized(Request $request): bool
    {
        $expectedToken = trim((string) config('services.zalo_oa.webhook_token', ''));
        if ($expectedToken === '') {
            return true;
        }

        $actualToken = trim((string) $request->query('token', ''));

        return $actualToken !== '' && hash_equals($expectedToken, $actualToken);
    }

    private function extractSenderId(Request $request): ?string
    {
        $payload = $this->payload($request);

        $senderId = data_get($payload, 'sender.id')
            ?? data_get($payload, 'message.from_id')
            ?? data_get($payload, 'data.sender.id')
            ?? data_get($payload, 'data.message.from_id');

        if (! is_string($senderId) && ! is_numeric($senderId)) {
            return null;
        }

        $normalized = trim((string) $senderId);

        return $normalized !== '' ? $normalized : null;
    }

    private function extractMessageText(Request $request): ?string
    {
        $payload = $this->payload($request);

        $text = data_get($payload, 'message.text')
            ?? data_get($payload, 'message.msg')
            ?? data_get($payload, 'data.message.text')
            ?? data_get($payload, 'data.message.msg');

        if (! is_string($text)) {
            return null;
        }

        $normalized = trim($text);

        return $normalized !== '' ? $normalized : null;
    }

    private function payload(Request $request): array
    {
        $payload = $request->all();
        $rawData = data_get($payload, 'data');

        if (is_string($rawData)) {
            $decoded = json_decode($rawData, true);
            if (is_array($decoded)) {
                $payload['data'] = $decoded;
            }
        }

        return is_array($payload) ? $payload : [];
    }

    private function extractPhone(string $text): ?string
    {
        $compact = preg_replace('/\s+/', '', $text) ?? $text;

        if (preg_match('/(\+?84|0)\d{9,10}/', $compact, $matches) !== 1) {
            return null;
        }

        return $this->normalizePhone($matches[0]);
    }

    private function findAdminByPhone(string $phone): ?User
    {
        $target = $this->normalizePhone($phone);

        $admins = User::query()
            ->where('role', 'admin')
            ->whereNotNull('phone')
            ->get();

        foreach ($admins as $admin) {
            if ($this->normalizePhone((string) $admin->phone) === $target) {
                return $admin;
            }
        }

        return null;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0084')) {
            $digits = substr($digits, 4);
            return '0' . $digits;
        }

        if (str_starts_with($digits, '84')) {
            $digits = substr($digits, 2);
            return '0' . $digits;
        }

        if (! str_starts_with($digits, '0')) {
            return '0' . $digits;
        }

        return $digits;
    }
}
