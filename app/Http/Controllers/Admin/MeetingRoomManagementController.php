<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MeetingRoom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MeetingRoomManagementController extends Controller
{
    public function index(): View
    {
        $rooms = MeetingRoom::query()
            ->orderByDesc('is_active')
            ->orderBy('code')
            ->orderBy('name')
            ->get();

        return view('admin.rooms.index', compact('rooms'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'has_camera' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        MeetingRoom::create([
            'code' => strtoupper(trim($data['code'])),
            'name' => trim($data['name']),
            'location' => $data['location'] ?? null,
            'capacity' => $data['capacity'],
            'has_camera' => (bool) ($data['has_camera'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return back()->with('success', 'Đã tạo phòng họp mới.');
    }

    public function update(Request $request, MeetingRoom $room): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'has_camera' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $room->update([
            'code' => strtoupper(trim($data['code'])),
            'name' => trim($data['name']),
            'location' => $data['location'] ?? null,
            'capacity' => $data['capacity'],
            'has_camera' => (bool) ($data['has_camera'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return back()->with('success', 'Đã cập nhật phòng họp.');
    }

    public function destroy(MeetingRoom $room): RedirectResponse
    {
        if ($room->bookings()->exists()) {
            return back()->withErrors([
                'room' => 'Phòng họp đã có lịch, không thể xóa. Bạn có thể chuyển sang trạng thái Ẩn.',
            ]);
        }

        $room->delete();

        return back()->with('success', 'Đã xóa phòng họp.');
    }
}
