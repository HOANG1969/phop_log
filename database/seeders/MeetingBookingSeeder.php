<?php

namespace Database\Seeders;

use App\Models\MeetingBooking;
use App\Models\MeetingRoom;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MeetingBookingSeeder extends Seeder
{
    public function run(): void
    {
        $date = Carbon::today();
        $requester = User::query()->where('email', 'user@pvgas.com.vn')->first();

        $bookingRows = [
            [
                'room' => 'Phòng họp 205',
                'title' => 'Kế hoạch triển khai, xây dựng VHDN tại C...',
                'start' => '10:00',
                'end' => '11:50',
            ],
            [
                'room' => 'P. Họp Tòa nhà TV 101',
                'title' => 'Công suất vận chuyển tuyến ống...',
                'start' => '09:00',
                'end' => '10:30',
            ],
            [
                'room' => 'P. Họp Tòa nhà TV 101',
                'title' => 'Công tác phối hợp...',
                'start' => '10:35',
                'end' => '11:20',
            ],
        ];

        foreach ($bookingRows as $row) {
            $room = MeetingRoom::where('name', $row['room'])->first();

            if (! $room) {
                continue;
            }

            $startAt = Carbon::parse($date->format('Y-m-d') . ' ' . $row['start']);
            $endAt = Carbon::parse($date->format('Y-m-d') . ' ' . $row['end']);

            MeetingBooking::updateOrCreate(
                [
                    'meeting_room_id' => $room->id,
                    'title' => $row['title'],
                    'start_at' => $startAt,
                ],
                [
                    'end_at' => $endAt,
                    'status' => 'approved',
                    'organizer_name' => 'System Seeder',
                    'requested_by' => $requester?->id,
                ]
            );
        }
    }
}
