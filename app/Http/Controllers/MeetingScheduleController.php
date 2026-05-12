<?php

namespace App\Http\Controllers;

use App\Models\MeetingBooking;
use App\Models\MeetingRoom;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\ZaloOaService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class MeetingScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $selectedDate = $this->resolveDate($request->query('date')) ?? Carbon::today();
        $defaultArea = $this->resolveUnitCode((string) ($user?->department ?? '')) ?? 'ALL';
        $areaFromQuery = strtoupper(trim((string) $request->query('area', '')));
        $selectedArea = $areaFromQuery !== '' ? $areaFromQuery : $defaultArea;
        $pendingApprovalCount = 0;

        $databaseReady = true;

        try {
            $areas = MeetingRoom::query()->select('code')->distinct()->pluck('code')->filter()->values();
            $areas = $areas->sortBy(function (string $code): int {
                return match (strtoupper($code)) {
                    'KVP' => 0,
                    'KCTV' => 1,
                    default => 2,
                };
            })->values();
            $areas = collect(['ALL'])->merge($areas);
            if ($areas->isEmpty()) {
                $areas = collect(['ALL']);
            }

            if (! $areas->contains($selectedArea)) {
                $selectedArea = $areas->contains($defaultArea) ? $defaultArea : 'ALL';
            }

            $roomsQuery = MeetingRoom::query()
                ->where('is_active', true)
                ->orderByRaw("case when upper(code) = 'KVP' then 0 when upper(code) = 'KCTV' then 1 else 2 end")
                ->orderBy('code')
                ->orderBy('name');

            if ($selectedArea !== 'ALL') {
                $roomsQuery->where('code', $selectedArea);
            }

            $rooms = $roomsQuery->get();

            if ($rooms->isEmpty() && $selectedArea !== 'ALL') {
                $rooms = MeetingRoom::query()
                    ->where('is_active', true)
                    ->orderByRaw("case when upper(code) = 'KVP' then 0 when upper(code) = 'KCTV' then 1 else 2 end")
                    ->orderBy('code')
                    ->orderBy('name')
                    ->get();
                $selectedArea = 'ALL';
            }

            $organizerUsers = User::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'phone', 'zalo_user_id']);

            $bookingsByRoom = $this->loadBookingsByRoom($rooms->pluck('id')->all(), $selectedDate, $user?->id, $user?->isAdmin() ?? false);

            if ($user?->isAdmin()) {
                $pendingApprovalCount = MeetingBooking::query()
                    ->where('status', 'pending')
                    ->count();
            }
        } catch (QueryException $e) {
            $databaseReady = false;
            $areas = collect(['ALL']);
            $rooms = collect($this->fallbackRooms());
            $organizerUsers = collect();
            $bookingsByRoom = $this->fallbackBookingsByRoom();
        }

        return view('welcome', [
            'selectedDate' => $selectedDate,
            'selectedDateText' => $selectedDate->format('d/m/Y'),
            'selectedDateIso' => $selectedDate->format('Y-m-d'),
            'selectedArea' => $selectedArea,
            'areas' => $areas,
            'rooms' => $rooms,
            'organizerUsers' => $organizerUsers,
            'bookingsByRoom' => $bookingsByRoom,
            'hours' => range(8, 17),
            'nowLineMinutes' => $this->resolveNowLineMinutes($selectedDate),
            'databaseReady' => $databaseReady,
            'currentUser' => $user,
            'pendingApprovalCount' => $pendingApprovalCount,
        ]);
    }

    public function myBookings(Request $request): View
    {
        $user = $request->user();
        $databaseReady = true;
        $pendingApprovalCount = 0;

        try {
            $bookings = MeetingBooking::query()
                ->with(['room', 'approver'])
                ->where('requested_by', $user?->id)
                ->orderByDesc('start_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString();

            if ($user?->isAdmin()) {
                $pendingApprovalCount = MeetingBooking::query()
                    ->where('status', 'pending')
                    ->count();
            }
        } catch (QueryException $e) {
            $databaseReady = false;
            $bookings = collect();
        }

        return view('my-bookings', [
            'bookings' => $bookings,
            'databaseReady' => $databaseReady,
            'currentUser' => $user,
            'pendingApprovalCount' => $pendingApprovalCount,
            'statusLabels' => [
                'pending' => 'Chờ duyệt',
                'approved' => 'Đã duyệt',
                'rejected' => 'Từ chối',
                'cancelled' => 'Đã hủy',
            ],
        ]);
    }

    public function workSchedule(Request $request): View
    {
        $user = $request->user();
        $databaseReady = true;
        $pendingApprovalCount = 0;
        $selectedDateIso = $this->resolveDate($request->query('date'))?->format('Y-m-d') ?? Carbon::today()->format('Y-m-d');
        $monday = Carbon::parse($selectedDateIso)->startOfWeek(Carbon::MONDAY);
        $staffOptions = $this->workScheduleStaffOptions();

        // Build date range for current week view (5 days Mon-Fri)
        $dates = [];
        for ($i = 0; $i < 5; $i++) {
            $dates[] = $monday->copy()->addDays($i);
        }
        $rangeStart = $dates[0]->toDateString();
        $rangeEnd = $dates[4]->toDateString();
        $selectedDateIso = $monday->toDateString();

        $staffList = [];
        $workSchedules = [];

        try {
            $staffList = collect($staffOptions)
                ->map(fn (string $name, int|string $id) => [
                    'id' => (int) $id,
                    'name' => $name,
                ])
                ->values()
                ->toArray();

            // Load work schedules that overlap with the date range
            $rawSchedules = WorkSchedule::query()
                ->with('user')
                ->where('start_date', '<=', $rangeEnd)
                ->where('end_date', '>=', $rangeStart)
                ->get();

            // Expand each schedule to per-day entries for the table
            foreach ($rawSchedules as $ws) {
                $periods = $ws->period === 'both'
                    ? ['morning', 'afternoon']
                    : [$ws->period];

                $current = Carbon::parse($ws->start_date);
                $end = Carbon::parse($ws->end_date);

                while ($current->lte($end)) {
                    $dateStr = $current->toDateString();
                    foreach ($periods as $period) {
                        $workSchedules[$ws->user_id][] = [
                            'id'              => $ws->id,
                            'date'            => $dateStr,
                            'period'          => $period,
                            'original_period' => $ws->period,
                            'activity'        => $ws->activity,
                            'start_date'      => $ws->start_date,
                            'end_date'        => $ws->end_date,
                            'user_id'         => $ws->user_id,
                        ];
                    }
                    $current->addDay();
                }
            }

            if ($user?->isAdmin()) {
                $pendingApprovalCount = MeetingBooking::query()
                    ->where('status', 'pending')
                    ->count();
            }
        } catch (QueryException $e) {
            $databaseReady = false;
        }

        return view('work-schedule', [
            'staffList' => $staffList,
            'workSchedules' => $workSchedules,
            'selectedDateIso' => $selectedDateIso,
            'databaseReady' => $databaseReady,
            'currentUser' => $user,
            'pendingApprovalCount' => $pendingApprovalCount,
        ]);
    }

    public function storeWorkSchedule(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $staffIds = array_map('intval', array_keys($this->workScheduleStaffOptions()));

        if (! $user?->isAdmin()) {
            abort(403, 'Bạn không có quyền thực hiện hành động này.');
        }

        $validated = $request->validate([
            'staff_id'   => ['required', 'integer', 'exists:users,id', Rule::in($staffIds)],
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'gte:start_date'],
            'period'     => ['required', 'in:morning,afternoon,both'],
            'activity'   => ['required', 'string', 'max:500'],
        ]);

        $conflictingPeriods = $validated['period'] === 'both'
            ? ['morning', 'afternoon', 'both']
            : ['both', $validated['period']];

        $conflict = WorkSchedule::query()
            ->where('user_id', $validated['staff_id'])
            ->where('start_date', '<=', $validated['end_date'])
            ->where('end_date', '>=', $validated['start_date'])
            ->whereIn('period', $conflictingPeriods)
            ->exists();

        if ($conflict) {
            return back()->withInput()->withErrors([
                'staff_id' => 'Nhân viên này đã có lịch trùng trong khoảng thời gian và buổi đã chọn.',
            ]);
        }

        WorkSchedule::create([
            'user_id'    => $validated['staff_id'],
            'start_date' => $validated['start_date'],
            'end_date'   => $validated['end_date'],
            'period'     => $validated['period'],
            'activity'   => $validated['activity'],
            'created_by' => $user->id,
        ]);

        return redirect()->route('admin.schedule.work-schedule', [
            'date' => $validated['start_date'],
        ])->with('success', 'Thêm lịch công tác thành công.');
    }

    public function updateWorkSchedule(Request $request, WorkSchedule $schedule): RedirectResponse
    {
        $user = Auth::user();
        $staffIds = array_map('intval', array_keys($this->workScheduleStaffOptions()));

        if (! $user?->isAdmin()) {
            abort(403, 'Bạn không có quyền thực hiện hành động này.');
        }

        $validated = $request->validate([
            'staff_id'   => ['required', 'integer', 'exists:users,id', Rule::in($staffIds)],
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'gte:start_date'],
            'period'     => ['required', 'in:morning,afternoon,both'],
            'activity'   => ['required', 'string', 'max:500'],
        ]);

        $conflictingPeriods = $validated['period'] === 'both'
            ? ['morning', 'afternoon', 'both']
            : ['both', $validated['period']];

        $conflict = WorkSchedule::query()
            ->where('user_id', $validated['staff_id'])
            ->where('id', '!=', $schedule->id)
            ->where('start_date', '<=', $validated['end_date'])
            ->where('end_date', '>=', $validated['start_date'])
            ->whereIn('period', $conflictingPeriods)
            ->exists();

        if ($conflict) {
            return back()->withInput()->withErrors([
                'staff_id' => 'Nhân viên này đã có lịch trùng trong khoảng thời gian và buổi đã chọn.',
            ]);
        }

        $schedule->update([
            'user_id'    => $validated['staff_id'],
            'start_date' => $validated['start_date'],
            'end_date'   => $validated['end_date'],
            'period'     => $validated['period'],
            'activity'   => $validated['activity'],
        ]);

        return redirect()->route('admin.schedule.work-schedule', [
            'date' => $validated['start_date'],
        ])->with('success', 'Cập nhật lịch công tác thành công.');
    }

    public function destroyWorkSchedule(WorkSchedule $schedule): RedirectResponse
    {
        $user = Auth::user();

        if (! $user?->isAdmin()) {
            abort(403, 'Bạn không có quyền thực hiện hành động này.');
        }

        $schedule->delete();

        return back()->with('success', 'Xóa lịch công tác thành công.');
    }

    public function cancel(MeetingBooking $booking): RedirectResponse
    {
        $user = Auth::user();

        if ((int) $booking->requested_by !== (int) $user->id) {
            abort(403, 'Bạn không có quyền hủy lịch này.');
        }

        if ($booking->status !== 'pending') {
            return back()->withErrors(['booking' => 'Chỉ có thể hủy lịch chưa được phê duyệt. Hãy liên hệ admin để hủy lịch đã duyệt.']);
        }

        $booking->update(['status' => 'cancelled']);

        return redirect()->route('schedule.index', [
            'date' => $booking->start_at->format('Y-m-d'),
        ])->with('success', 'Đã hủy lịch họp thành công.');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'meeting_room_id' => ['required', 'integer', 'exists:meeting_rooms,id'],
            'organizer_user_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'string'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_date' => ['required', 'string'],
            'end_time' => ['required', 'date_format:H:i'],
            'internal_attendees' => ['nullable', 'string'],
            'external_attendees' => ['nullable', 'string'],
            'meeting_link' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'snacks_requested' => ['nullable', 'boolean'],
            'is_online' => ['nullable', 'boolean'],
        ]);

        $startDate = $this->resolveDate($validated['start_date']);
        $endDate = $this->resolveDate($validated['end_date']);

        if (! $startDate || ! $endDate) {
            return back()->withInput()->withErrors([
                'start_date' => 'Ngày họp không hợp lệ. Vui lòng chọn lại bằng bộ chọn ngày.',
            ]);
        }

        $startAt = Carbon::createFromFormat('Y-m-d H:i', $startDate->format('Y-m-d') . ' ' . $validated['start_time']);
        $endAt = Carbon::createFromFormat('Y-m-d H:i', $endDate->format('Y-m-d') . ' ' . $validated['end_time']);
        $effectiveEndAt = $endAt;

        if ($effectiveEndAt->lessThanOrEqualTo($startAt)) {
            return back()->withInput()->withErrors([
                'end_time' => 'Thời gian kết thúc phải lớn hơn thời gian bắt đầu.',
            ]);
        }

        if ($this->startsInPast($startAt)) {
            return back()->withInput()->withErrors([
                'start_time' => 'Thời gian bắt đầu không được trước thời điểm hiện tại.',
            ]);
        }

        try {
            $hasConflict = MeetingBooking::query()
                ->where('meeting_room_id', $validated['meeting_room_id'])
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->where('start_at', '<', $effectiveEndAt)
                ->where('end_at', '>', $startAt)
                ->exists();

            if ($hasConflict) {
                return back()->withInput()->withErrors([
                    'meeting_room_id' => 'Khung giờ đã có lịch, vui lòng chọn thời gian khác.',
                ]);
            }

            $organizerUser = User::query()
                ->where('id', $validated['organizer_user_id'])
                ->where('is_active', true)
                ->first();

            if (! $organizerUser) {
                return back()->withInput()->withErrors([
                    'organizer_user_id' => 'Người đăng ký không hợp lệ hoặc đã bị khóa.',
                ]);
            }

            if (! is_string($organizerUser->zalo_user_id) || trim($organizerUser->zalo_user_id) === '') {
                return back()->withInput()->withErrors([
                    'organizer_user_id' => 'Người đăng ký chưa có Zalo User ID để gửi thông báo qua OA.',
                ]);
            }

            $isAdminBooking = Auth::user()?->isAdmin() ?? false;

            $booking = MeetingBooking::create([
                'meeting_room_id' => $validated['meeting_room_id'],
                'requested_by' => $organizerUser->id,
                'approved_by' => $isAdminBooking ? Auth::id() : null,
                'organizer_name' => $organizerUser->name,
                'title' => $validated['title'],
                'start_at' => $startAt,
                'end_at' => $effectiveEndAt,
                'internal_attendees' => $validated['internal_attendees'] ?? null,
                'external_attendees' => $validated['external_attendees'] ?? null,
                'meeting_link' => $validated['meeting_link'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'snacks_requested' => (bool) ($validated['snacks_requested'] ?? false),
                'is_online' => (bool) ($validated['is_online'] ?? false),
                'status' => $isAdminBooking ? 'approved' : 'pending',
                'approved_at' => $isAdminBooking ? Carbon::now() : null,
            ]);

            $this->sendZaloBookingNotifications($booking, $organizerUser);
        } catch (QueryException $e) {
            return back()->withInput()->withErrors([
                'meeting_room_id' => 'Không thể lưu vì chưa kết nối được database. Vui lòng cấu hình DB rồi thử lại.',
            ]);
        }

        return redirect()->route('schedule.index', [
            'date' => $startAt->format('Y-m-d'),
            'area' => strtoupper((string) $request->input('area', 'ALL')),
        ])->with('success', 'Đăng ký phòng họp thành công.');
    }

    private function resolveDate(?string $dateText): ?Carbon
    {
        if (! $dateText) {
            return Carbon::today();
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $dateText)->startOfDay();
        } catch (\Throwable $e) {
            try {
                return Carbon::createFromFormat('Y-m-d', $dateText)->startOfDay();
            } catch (\Throwable $e) {
                return null;
            }
        }
    }

    private function resolveUnitCode(string $department): ?string
    {
        $normalized = strtoupper(trim($department));

        return match ($normalized) {
            'KVP', 'KCTV' => $normalized,
            default => null,
        };
    }

    private function sendZaloBookingNotifications(MeetingBooking $booking, User $organizerUser): void
    {
        $zaloOaService = app(ZaloOaService::class);
        $roomName = $booking->room?->name ?? '-';
        $timeLabel = $booking->start_at?->format('d/m/Y H:i') . ' - ' . $booking->end_at?->format('H:i');

        $notificationData = [
            'app_name' => 'PHOP LOG',
            'title' => $booking->title,
            'room_name' => $roomName,
            'time_label' => $timeLabel,
            'organizer_name' => $booking->organizer_name,
            'status' => $booking->status,
        ];

        $approvers = User::query()
            ->where('role', 'admin')
            ->where('is_active', true)
            ->whereNotNull('zalo_user_id')
            ->get(['name', 'zalo_user_id']);

        $recipientUserIds = collect([$organizerUser->zalo_user_id])
            ->merge($approvers->pluck('zalo_user_id'))
            ->filter(fn ($userId) => is_string($userId) && trim($userId) !== '')
            ->unique()
            ->values();

        foreach ($recipientUserIds as $userId) {
            $sent = $zaloOaService->sendBookingNotification((string) $userId, $notificationData);
            if (! $sent) {
                Log::warning('Unable to send booking notification via Zalo OA.', [
                    'booking_id' => $booking->id,
                    'zalo_user_id' => $userId,
                ]);
            }
        }
    }

    private function resolveNowLineMinutes(Carbon $selectedDate): ?int
    {
        if (! $selectedDate->isToday()) {
            return null;
        }

        $dayStart = $selectedDate->copy()->setTime(8, 0);
        $minutes = (int) $dayStart->diffInMinutes(Carbon::now(), false);

        return max(0, min(600, $minutes));
    }

    private function loadBookingsByRoom(array $roomIds, Carbon $selectedDate, ?int $currentUserId, bool $isAdmin): array
    {
        $dayStart = $selectedDate->copy()->setTime(8, 0);
        $dayEnd = $selectedDate->copy()->setTime(18, 0);

        $bookingsQuery = MeetingBooking::query()
            ->whereIn('meeting_room_id', $roomIds)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->where('start_at', '<', $dayEnd)
            ->where('end_at', '>', $dayStart)
            ->orderBy('start_at');

        if (! $isAdmin) {
            $bookingsQuery->whereIn('status', ['pending', 'approved']);
        }

        $bookings = $bookingsQuery->get();

        $result = [];

        foreach ($bookings as $booking) {
            $displayStart = $booking->start_at->greaterThan($dayStart) ? $booking->start_at : $dayStart;
            $bookingEndAt = $booking->end_at->copy();
            $displayEnd = $bookingEndAt->lessThan($dayEnd) ? $bookingEndAt : $dayEnd;

            $durationMinutes = max(15, $displayStart->diffInMinutes($displayEnd));
            $leftMinutes = $dayStart->diffInMinutes($displayStart);

            $result[$booking->meeting_room_id][] = [
                'id' => $booking->id,
                'title' => $booking->title,
                'left_minutes' => $leftMinutes,
                'duration_minutes' => $durationMinutes,
                'status' => $booking->status,
                'is_online' => (bool) $booking->is_online,
                'time_label' => $booking->start_at->format('H:i') . ' - ' . $booking->end_at->format('H:i'),
                'room_name' => $booking->room?->name,
                'organizer_name' => $booking->organizer_name,
                'internal_attendees' => $booking->internal_attendees,
                'external_attendees' => $booking->external_attendees,
                'meeting_link' => $booking->meeting_link,
                'notes' => $booking->notes,
                'can_cancel' => ! $isAdmin && $currentUserId && (int) $booking->requested_by === $currentUserId && $booking->status === 'pending',
            ];
        }

        return $result;
    }

    private function startsInPast(Carbon $startAt): bool
    {
        return $startAt->lessThan(Carbon::now());
    }

    private function workScheduleStaffOptions(): array
    {
        return [
            1 => 'Ông Nguyễn Nhật Quốc Toản (Giám đốc)',
            2 => 'Ông Phan Tấn Hậu (Phó Giám đốc)',
        ];
    }

    private function fallbackRooms(): array
    {
        return [
            ['id' => 1, 'code' => 'KVT', 'name' => 'Phòng họp 205', 'has_camera' => true, 'capacity' => 45],
            ['id' => 2, 'code' => 'KVT', 'name' => 'Phòng họp 402', 'has_camera' => false, 'capacity' => 22],
            ['id' => 3, 'code' => 'KVT', 'name' => 'P. Họp Tòa nhà TV 101', 'has_camera' => true, 'capacity' => 62],
            ['id' => 4, 'code' => 'KVT', 'name' => 'Hội trường 266', 'has_camera' => false, 'capacity' => 90],
            ['id' => 5, 'code' => 'KVT', 'name' => 'Phòng đào tạo 266', 'has_camera' => false, 'capacity' => 15],
        ];
    }

    private function fallbackBookingsByRoom(): array
    {
        return [
            1 => [
                ['title' => 'Kế hoạch triển khai, xây dựng VHDN tại C...', 'left_minutes' => 120, 'duration_minutes' => 110],
            ],
            3 => [
                ['title' => 'Công suất vận chuyển tuyến ống...', 'left_minutes' => 60, 'duration_minutes' => 90],
                ['title' => 'Công tác phối hợp...', 'left_minutes' => 155, 'duration_minutes' => 50],
            ],
        ];
    }
}
