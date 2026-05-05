<?php

namespace Database\Seeders;

use App\Models\MeetingRoom;
use Illuminate\Database\Seeder;

class MeetingRoomSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            ['code' => 'KVT', 'name' => 'Phòng họp 205', 'location' => 'Tầng 2', 'capacity' => 45, 'has_camera' => true],
            ['code' => 'KVT', 'name' => 'Phòng họp 402', 'location' => 'Tầng 4', 'capacity' => 22, 'has_camera' => false],
            ['code' => 'KVT', 'name' => 'P. Họp Tòa nhà TV 101', 'location' => 'Tầng 1', 'capacity' => 62, 'has_camera' => true],
            ['code' => 'KVT', 'name' => 'Hội trường 266', 'location' => 'Tầng 2', 'capacity' => 90, 'has_camera' => false],
            ['code' => 'KVT', 'name' => 'Phòng đào tạo 266', 'location' => 'Tầng 2', 'capacity' => 15, 'has_camera' => false],
        ];

        foreach ($rooms as $room) {
            MeetingRoom::updateOrCreate(
                ['code' => $room['code'], 'name' => $room['name']],
                $room
            );
        }
    }
}
