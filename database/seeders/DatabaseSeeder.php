<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            MeetingRoomSeeder::class,
            MeetingBookingSeeder::class,
        ]);

        User::updateOrCreate(
            ['email' => 'admin@pvgas.com.vn'],
            [
                'name' => 'Quản trị hệ thống',
                'username' => 'admin',
                'department' => 'CNTT',
                'position' => 'System Admin',
                'role' => 'admin',
                'is_active' => true,
                'password' => bcrypt('admin123'),
            ]
        );

        User::updateOrCreate(
            ['email' => 'user@pvgas.com.vn'],
            [
                'name' => 'Trần Văn Triều',
                'username' => 'trieutv',
                'department' => 'KVT',
                'position' => 'Nhân viên',
                'role' => 'user',
                'is_active' => true,
                'password' => bcrypt('user123'),
            ]
        );
    }
}
