<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ZaloOaService;
use Illuminate\Console\Command;

class ZaloOaTestCommand extends Command
{
    protected $signature = 'zalo:test {user_id : ID nhân sự cần gửi thử thông báo OA}';

    protected $description = 'Gửi thử tin nhắn Zalo OA cho một nhân sự để kiểm tra kết nối';

    public function handle(ZaloOaService $zaloOaService): int
    {
        $userId = (int) $this->argument('user_id');

        $user = User::find($userId);

        if (! $user) {
            $this->error("Không tìm thấy nhân sự với ID: {$userId}");

            return self::FAILURE;
        }

        $this->line("Nhân sự  : {$user->name} (ID: {$user->id})");
        $this->line('Zalo UID  : ' . ($user->zalo_user_id ?? '(chưa cài đặt)'));

        if (empty($user->zalo_user_id)) {
            $this->error('Nhân sự này chưa có Zalo User ID. Vui lòng cập nhật trong trang quản lý nhân sự.');

            return self::FAILURE;
        }

        $this->line('Đang gửi tin nhắn thử...');

        $bookingData = [
            'app_name'       => config('app.name', 'PHOP LOG'),
            'title'          => '[TEST] Kiểm tra kết nối Zalo OA',
            'room_name'      => 'Phòng họp Demo',
            'time_label'     => now()->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i'),
            'organizer_name' => $user->name,
            'status'         => 'pending',
        ];

        $success = $zaloOaService->sendBookingNotification($user->zalo_user_id, $bookingData);

        if ($success) {
            $this->info('Gửi thành công! Kiểm tra Zalo của nhân sự để xác nhận.');
        } else {
            $this->error('Gửi thất bại. Kiểm tra log Laravel (storage/logs/laravel.log) để biết chi tiết.');
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }
}
