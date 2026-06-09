<?php

namespace App\Http\Controllers;

use App\Models\MeetingBooking;
use App\Models\MeetingRoom;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\ZaloZnsService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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
        $myApprovedCount = 0;

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

            $bookingsByRoom = $this->loadBookingsByRoom($rooms->pluck('id')->all(), $selectedDate, $user?->id, $user?->isAdmin() ?? false);

            if ($user?->isAdmin()) {
                $pendingApprovalCount = MeetingBooking::query()
                    ->where('status', 'pending')
                    ->count();
            } elseif ($user?->id) {
                $myApprovedCount = MeetingBooking::query()
                    ->where('requested_by', $user->id)
                    ->whereIn('status', ['approved', 'pending'])
                    ->count();
            }
        } catch (QueryException $e) {
            $databaseReady = false;
            $areas = collect(['ALL']);
            $rooms = collect($this->fallbackRooms());
            $bookingsByRoom = $this->fallbackBookingsByRoom();
        }

        return view('welcome', [
            'selectedDate' => $selectedDate,
            'selectedDateText' => $selectedDate->format('d/m/Y'),
            'selectedDateIso' => $selectedDate->format('Y-m-d'),
            'selectedArea' => $selectedArea,
            'areas' => $areas,
            'rooms' => $rooms,
            'bookingsByRoom' => $bookingsByRoom,
            'hours' => range(8, 17),
            'nowLineMinutes' => $this->resolveNowLineMinutes($selectedDate),
            'databaseReady' => $databaseReady,
            'currentUser' => $user,
            'pendingApprovalCount' => $pendingApprovalCount,
            'myApprovedCount' => $myApprovedCount,
        ]);
    }

    public function myBookings(Request $request): View
    {
        $user = $request->user();
        $databaseReady = true;
        $pendingApprovalCount = 0;
        $myApprovedCount = 0;

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
            } elseif ($user?->id) {
                $myApprovedCount = MeetingBooking::query()
                    ->where('requested_by', $user->id)
                    ->whereIn('status', ['approved', 'pending'])
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
            'myApprovedCount' => $myApprovedCount,
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
        $myApprovedCount = 0;
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
            } elseif ($user?->id) {
                $myApprovedCount = MeetingBooking::query()
                    ->where('requested_by', $user->id)
                    ->whereIn('status', ['approved', 'pending'])
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
            'myApprovedCount' => $myApprovedCount,
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
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
            'period'     => ['required', 'in:morning,afternoon,both'],
            'activity'   => ['required', 'string', 'max:500'],
        ], [
            'end_date.after_or_equal' => 'Ngày "Đến ngày" phải bằng hoặc sau "Từ ngày".',
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
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
            'period'     => ['required', 'in:morning,afternoon,both'],
            'activity'   => ['required', 'string', 'max:500'],
            'edit_target_date' => ['nullable', 'date'],
            'edit_target_period' => ['nullable', 'in:morning,afternoon,both'],
        ], [
            'end_date.after_or_equal' => 'Ngày "Đến ngày" phải bằng hoặc sau "Từ ngày".',
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

        $targetDate = $validated['edit_target_date'] ?? null;
        $targetPeriod = $validated['edit_target_period'] ?? null;

        if ($targetDate && $targetPeriod) {
            $segments = $this->buildRemainingSegmentsAfterTargetEdit($schedule, $targetDate, $targetPeriod);

            if ($segments === null) {
                return back()->withInput()->withErrors([
                    'start_date' => 'Không tìm thấy phần lịch cần chỉnh sửa cho ngày/buổi đã chọn.',
                ]);
            }

            if ($this->hasSegmentConflict(
                (int) $validated['staff_id'],
                $validated['start_date'],
                $validated['end_date'],
                $validated['period'],
                $segments,
                (int) $schedule->user_id
            )) {
                return back()->withInput()->withErrors([
                    'staff_id' => 'Lịch chỉnh sửa bị trùng với phần lịch giữ nguyên. Vui lòng điều chỉnh lại ngày hoặc buổi.',
                ]);
            }

            DB::transaction(function () use ($schedule, $validated, $segments, $user): void {
                $createdBy = (int) ($schedule->created_by ?: ($user?->id ?? Auth::id()));

                $schedule->delete();

                foreach ($segments as $segment) {
                    WorkSchedule::create([
                        'user_id' => $segment['user_id'],
                        'start_date' => $segment['start_date'],
                        'end_date' => $segment['end_date'],
                        'period' => $segment['period'],
                        'activity' => $segment['activity'],
                        'created_by' => $segment['created_by'],
                    ]);
                }

                WorkSchedule::create([
                    'user_id'    => $validated['staff_id'],
                    'start_date' => $validated['start_date'],
                    'end_date'   => $validated['end_date'],
                    'period'     => $validated['period'],
                    'activity'   => $validated['activity'],
                    'created_by' => $createdBy,
                ]);
            });
        } else {
            $schedule->update([
                'user_id'    => $validated['staff_id'],
                'start_date' => $validated['start_date'],
                'end_date'   => $validated['end_date'],
                'period'     => $validated['period'],
                'activity'   => $validated['activity'],
            ]);
        }

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
            'organizer_name' => ['required', 'string', 'max:255'],
            'organizer_phone' => ['required', 'string', 'max:20', 'regex:/^(0|84|\+84)[0-9]{8,11}$/'],
            'organizer_department' => ['required', 'string', 'max:120'],
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
        $coverageEndAt = $this->normalizeEndForSlotCoverage($endAt);

        if ($coverageEndAt->lessThanOrEqualTo($startAt)) {
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
            $candidateBookings = MeetingBooking::query()
                ->where('meeting_room_id', $validated['meeting_room_id'])
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->where('start_at', '<', $coverageEndAt)
                ->where('end_at', '>', $startAt->copy()->subHour())
                ->get(['start_at', 'end_at']);

            $hasConflict = $candidateBookings->contains(function (MeetingBooking $existingBooking) use ($startAt, $coverageEndAt): bool {
                $existingCoverageEndAt = $this->normalizeEndForSlotCoverage($existingBooking->end_at);

                return $existingBooking->start_at->lt($coverageEndAt)
                    && $existingCoverageEndAt->gt($startAt);
            });

            if ($hasConflict) {
                return back()->withInput()->withErrors([
                    'meeting_room_id' => 'Khung giờ đã có lịch, vui lòng chọn thời gian khác.',
                ]);
            }

            $isAdminBooking = Auth::user()?->isAdmin() ?? false;

            $booking = MeetingBooking::create([
                'meeting_room_id' => $validated['meeting_room_id'],
                'requested_by' => Auth::id(),
                'approved_by' => $isAdminBooking ? Auth::id() : null,
                'organizer_name' => $validated['organizer_name'],
                'organizer_phone' => trim($validated['organizer_phone']),
                'organizer_department' => trim($validated['organizer_department']),
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

            $booking->refresh();
            $this->sendZnsBookingNotificationsToAdmins($booking);
        } catch (QueryException $e) {
            $errorMessage = (string) $e->getMessage();
            $normalizedError = strtolower($errorMessage);
            $friendlyMessage = 'Không thể lưu lịch họp do lỗi dữ liệu. Vui lòng thử lại.';

            if (str_contains($normalizedError, 'organizer_department') || str_contains($normalizedError, 'unknown column')) {
                $friendlyMessage = 'Database chưa cập nhật cấu trúc mới. Vui lòng chạy migrate rồi thử lại.';
            } elseif (str_contains($normalizedError, 'sqlstate[hy000] [2002]') || str_contains($normalizedError, 'connection refused')) {
                $friendlyMessage = 'Không thể kết nối database. Vui lòng kiểm tra cấu hình DB rồi thử lại.';
            }

            Log::error('Failed to create meeting booking.', [
                'sql_state' => $e->getCode(),
                'error' => $errorMessage,
            ]);

            return back()->withInput()->withErrors([
                'meeting_room_id' => $friendlyMessage,
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

    /**
     * Keep untouched slots from the original schedule when editing a specific day/period.
     *
    * @return array<int, array{user_id:int,start_date:string,end_date:string,period:string,activity:string,created_by:int}>|null
     */
    private function buildRemainingSegmentsAfterTargetEdit(WorkSchedule $schedule, string $targetDate, string $targetPeriod): ?array
    {
        $start = Carbon::parse($schedule->start_date)->startOfDay();
        $end = Carbon::parse($schedule->end_date)->startOfDay();
        $target = Carbon::parse($targetDate)->startOfDay();

        if ($target->lt($start) || $target->gt($end)) {
            return null;
        }

        $originalPeriods = $schedule->period === 'both'
            ? ['morning', 'afternoon']
            : [$schedule->period];

        $removePeriods = $targetPeriod === 'both'
            ? ['morning', 'afternoon']
            : [$targetPeriod];

        $effectiveRemovePeriods = array_values(array_intersect($originalPeriods, $removePeriods));

        if (empty($effectiveRemovePeriods)) {
            return null;
        }

        $slotsByPeriod = [
            'morning' => [],
            'afternoon' => [],
        ];

        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $dateText = $cursor->toDateString();
            foreach ($originalPeriods as $period) {
                $isTargetSlot = $dateText === $target->toDateString() && in_array($period, $effectiveRemovePeriods, true);
                if (! $isTargetSlot) {
                    $slotsByPeriod[$period][] = $dateText;
                }
            }
            $cursor->addDay();
        }

        $segments = [];
        $createdBy = (int) ($schedule->created_by ?: 1);

        foreach ($slotsByPeriod as $period => $dates) {
            if (empty($dates)) {
                continue;
            }

            $segmentStart = Carbon::parse($dates[0]);
            $segmentEnd = Carbon::parse($dates[0]);

            for ($i = 1; $i < count($dates); $i++) {
                $current = Carbon::parse($dates[$i]);
                if ($current->eq($segmentEnd->copy()->addDay())) {
                    $segmentEnd = $current;
                    continue;
                }

                $segments[] = [
                    'user_id' => (int) $schedule->user_id,
                    'start_date' => $segmentStart->toDateString(),
                    'end_date' => $segmentEnd->toDateString(),
                    'period' => $period,
                    'activity' => (string) $schedule->activity,
                    'created_by' => $createdBy,
                ];

                $segmentStart = $current;
                $segmentEnd = $current;
            }

            $segments[] = [
                'user_id' => (int) $schedule->user_id,
                'start_date' => $segmentStart->toDateString(),
                'end_date' => $segmentEnd->toDateString(),
                'period' => $period,
                'activity' => (string) $schedule->activity,
                'created_by' => $createdBy,
            ];
        }

        return $segments;
    }

    /**
     * @param array<int, array{user_id:int,start_date:string,end_date:string,period:string,activity:string,created_by:int}> $segments
     */
    private function hasSegmentConflict(
        int $staffId,
        string $startDate,
        string $endDate,
        string $period,
        array $segments,
        int $originalStaffId
    ): bool {
        if ($staffId !== $originalStaffId) {
            return false;
        }

        $newPeriods = $period === 'both' ? ['morning', 'afternoon'] : [$period];

        foreach ($segments as $segment) {
            if ((int) $segment['user_id'] !== $staffId) {
                continue;
            }

            $segmentPeriods = $segment['period'] === 'both' ? ['morning', 'afternoon'] : [$segment['period']];
            $overlapPeriods = array_intersect($newPeriods, $segmentPeriods);
            if (empty($overlapPeriods)) {
                continue;
            }

            $isDateOverlapped = $segment['start_date'] <= $endDate && $segment['end_date'] >= $startDate;
            if ($isDateOverlapped) {
                return true;
            }
        }

        return false;
    }

    private function resolveUnitCode(string $department): ?string
    {
        $normalized = strtoupper(trim($department));

        return match ($normalized) {
            'KVP', 'KCTV' => $normalized,
            default => null,
        };
    }

    private function sendZnsBookingNotificationsToAdmins(MeetingBooking $booking): void
    {
        $znsService = app(ZaloZnsService::class);
        $templateData = $this->buildBookingZnsTemplateData($booking);

        $approvers = User::query()
            ->where('role', 'admin')
            ->where('is_active', true)
            ->whereNotNull('phone')
            ->get(['id', 'name', 'phone']);

        $recipientPhones = $approvers->pluck('phone')
            ->filter(fn ($phone) => is_string($phone) && trim($phone) !== '')
            ->unique()
            ->values();

        if ($recipientPhones->isEmpty()) {
            Log::warning('No admin phone found for Zalo ZNS booking notification.', [
                'booking_id' => $booking->id,
                'status' => $booking->status,
            ]);

            return;
        }

        Log::info('Dispatching Zalo ZNS booking notifications to admins.', [
            'booking_id' => $booking->id,
            'status' => $booking->status,
            'recipient_count' => $recipientPhones->count(),
        ]);

        foreach ($recipientPhones as $phone) {
            $trackingId = sprintf('booking_%d_admin_%s', $booking->id, preg_replace('/\D+/', '', (string) $phone));
            $sent = $znsService->sendBookingConfirmation((string) $phone, $templateData, $trackingId);

            if (! $sent) {
                Log::warning('Unable to send booking notification via Zalo ZNS for admin phone.', [
                    'booking_id' => $booking->id,
                    'phone' => $phone,
                ]);
            } else {
                Log::info('Sent booking notification via Zalo ZNS for admin phone.', [
                    'booking_id' => $booking->id,
                    'phone' => $phone,
                ]);
            }
        }
    }

    private function buildBookingZnsTemplateData(MeetingBooking $booking): array
    {
        $startAt = $booking->start_at?->copy()->timezone('Asia/Ho_Chi_Minh');
        $endAt = $booking->end_at?->copy()->timezone('Asia/Ho_Chi_Minh');
        $meetingDate = $startAt?->format('d/m/Y')
            ?? now()->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y');
        $meetingTime = $startAt
            ? ($endAt
                ? $startAt->format('H:i') . '-' . $endAt->format('H:i')
                : $startAt->format('H:i'))
            : '-';
        $noiDung = $this->limitZnsTemplateValue((string) $booking->title, 120);

        $statusText = match ($booking->status) {
            'approved' => 'Da duoc phe duyet.',
            'pending' => 'Dang cho phe duyet.',
            'rejected' => 'Da bi tu choi.',
            'cancelled' => 'Da bi huy.',
            default => 'Dang xu ly.',
        };

        return [
            'name' => $this->limitZnsTemplateValue((string) $booking->organizer_name, 60),
            'datetime' => $meetingDate,
            'time' => $meetingTime,
            'meeting_date' => $meetingDate,
            'meeting_time' => $meetingTime,
            'noi_dung' => $noiDung,
            'department' => $this->limitZnsTemplateValue((string) $booking->organizer_department, 60),
            'deparment' => $this->limitZnsTemplateValue((string) $booking->organizer_department, 60),
            'content' => $statusText,
        ];
    }

    private function limitZnsTemplateValue(string $value, int $maxLength): string
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
            $bookingEndAt = $this->normalizeEndForSlotCoverage($booking->end_at);
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

    private function normalizeEndForSlotCoverage(Carbon $endAt): Carbon
    {
        $normalized = $endAt->copy();

        // If users choose an exact hour (e.g. 16:00), treat it as occupying the full 16:00-16:59 slot.
        if ((int) $normalized->minute === 0 && (int) $normalized->second === 0) {
            $normalized->addHour();
        }

        return $normalized;
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
