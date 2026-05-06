<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('image/logopvgas.png') }}?v=3">
    <link rel="shortcut icon" type="image/png" href="{{ asset('image/logopvgas.png') }}?v=3">
    <title >Hệ thống quản lý Phòng họp, lịch công tác PVGAS LOGISITICS</title>
    <link rel="stylesheet" href="{{ asset('css/meeting.css') }}">
</head>
<body>
    <header class="topbar app-topbar" id="scheduleHeader">
        <div class="brand">
            <!-- <h4 class="brand-logo">PETROVIETNAM<br>PV GAS</h4> -->
             <img src="{{ asset('image/logopvgas.png') }}" alt="PVGAS LOGISTICS" class="brand-logo-image">
            <h3 class="brand-heading">PVGAS LOGISITICS</h3>
        </div>

        <div class="nav-actions">
            <a class="tab " href="{{ route('schedule.index') }}" style="margin-right: 0px;">PHÒNG HỌP</a>
            @if ($currentUser && $currentUser->isAdmin())
                <a class="tab tab-with-badge" href="{{ route('admin.bookings.index') }}">
                    PHÊ DUYỆT
                    @if (($pendingApprovalCount ?? 0) > 0)
                        <span class="badge badge-inline">{{ $pendingApprovalCount }}</span>
                    @endif
                </a>
                <a class="tab" href="{{ route('admin.users.index') }}">NHÂN SỰ</a>
                <a class="tab" href="{{ route('admin.rooms.index') }}">DANH SÁCH PHÒNG</a>
                <a class="tab " href="{{ route('admin.schedule.work-schedule') }}" style="border-radius: 6px; margin-right: 0px;">LỊCH CÔNG TÁC BGĐ</a>
            @else
                <h5>|</h5>
                <a class="tab" href="{{ route('schedule.my-bookings') }}" style="border-radius: 6px; margin-left: 0px;">LỊCH CỦA TÔI</a>
                <h4>|</h4>
                <a class="tab active " href="{{ route('schedule.work-schedule') }}" style="border-radius: 6px; margin-right: 0px;">LỊCH CÔNG TÁC BGĐ</a>
            @endif
            <!-- <button class="icon-btn" type="button" aria-label="Trợ giúp">?</button> -->
            <!-- <button class="icon-btn" type="button" aria-label="Thông báo">
                🔔
                @if (($pendingApprovalCount ?? 0) > 0)
                    <span class="badge">{{ $pendingApprovalCount }}</span>
                @endif
            </button> -->
           <!-- <div class="user-pill">
                <span class="avatar"></span>
                {{ $currentUser?->name ?? 'Người dùng' }}
            </div>-->
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="tab" type="submit" style="background-color: blue; color: white; border-radius: 6px; margin-right: 0px;">ĐĂNG XUẤT</button>
            </form>
        </div>
    </header>

    <main class="layout" id="scheduleLayout">
        <div class="page-notices">
            @if (! $databaseReady)
                <div class="notice warning">Database chưa sẵn sàng, đang hiển thị dữ liệu mẫu. Hãy cấu hình DB và chạy migrate/seed để lưu dữ liệu thật.</div>
            @endif

            @if (session('success'))
                <div class="notice success" id="noticeSuccess">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="notice danger" id="noticeDanger">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif
        </div>

        <section class="schedule-viewport" id="scheduleViewport" style="padding: 24px;">
            @php
                $navRoute   = ($currentUser && $currentUser->isAdmin()) ? 'admin.schedule.work-schedule' : 'schedule.work-schedule';
                $prevMonday = \Carbon\Carbon::parse($selectedDateIso)->subWeek();
                $nextMonday = \Carbon\Carbon::parse($selectedDateIso)->addWeek();
                $dispMon    = \Carbon\Carbon::parse($selectedDateIso)->startOfWeek(\Carbon\Carbon::MONDAY);
                $dispFri    = $dispMon->copy()->addDays(4);
                $viMonths   = ['','Tháng 1','Tháng 2','Tháng 3','Tháng 4','Tháng 5','Tháng 6','Tháng 7','Tháng 8','Tháng 9','Tháng 10','Tháng 11','Tháng 12'];
                $rangeLabel = $dispMon->format('d').' '.$viMonths[$dispMon->month].' - '.$dispFri->format('d').' '.$viMonths[$dispFri->month].' '.$dispFri->year;
            @endphp
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
                <div style="display:flex;align-items:center;gap:6px;">
                    <a href="{{ route($navRoute, ['date' => now()->toDateString()]) }}" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:#0066cc;color:white;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;white-space:nowrap;">📅 Hôm nay</a>
                    <a href="{{ route($navRoute, ['date' => $prevMonday->toDateString()]) }}" style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;background:white;border:1px solid #ddd;border-radius:6px;color:#333;text-decoration:none;font-size:18px;font-weight:600;">‹</a>
                    <div style="position:relative; background:#0066cc;color:white;border-radius:6px;font-size:13px;font-weight:600;">
                        <button type="button" id="weekPickerBtn" style="display:inline-flex;align-items:center;gap:10px;padding:7px 14px;background:white;border:1px solid #ddd;border-radius:6px;font-size:13px;font-weight:600;color:#333;cursor:pointer;min-width:220px;justify-content:space-between;">
                            <span>{{ $rangeLabel }}</span>
                            <span style="color:#888;font-size:11px;">▾</span>
                        </button>
                        <div id="calendarPopup" style="display:none;position:absolute;top:42px;left:0;background:white;border:1px solid #ddd;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.15);z-index:600;padding:16px;min-width:260px;"></div>
                    </div>
                    <a href="{{ route($navRoute, ['date' => $nextMonday->toDateString()]) }}" style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;background:white;border:1px solid #ddd;border-radius:6px;color:#333;text-decoration:none;font-size:18px;font-weight:600;">›</a>
                </div>
                @if ($currentUser && $currentUser->isAdmin())
                <button class="btn-primary" id="registerBtn" type="button" style="padding:8px 16px;background:#27ae60;color:white;border:none;border-radius:4px;cursor:pointer;font-size:14px;font-weight:600;">+ Thêm lịch công tác</button>
                @endif
            </div>

            
            <div style="text-align: center; margin-bottom: 16px; font-weight: bold; color: #555; font-size: 20px;">
                <p>DỰ KIẾN LỊCH CÔNG TÁC TUẦN CỦA BAN GIÁM ĐỐC</p>
                @php
                    $monday = \Carbon\Carbon::parse($selectedDateIso ?? now()->toDateString())->startOfWeek(\Carbon\Carbon::MONDAY);
                    $friday = $monday->copy()->addDays(4);
                @endphp
                <p style="color: #555; font-size: 12px;">(Từ ngày {{ $monday->format('d/m/Y') }} đến {{ $friday->format('d/m/Y') }}, lịch công tác có thể thay đổi)</p>
            </div>
            <div class="schedule-table-wrap" style="overflow-x: auto; background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); height: 600px; font-size: 20px;">
                <table class="schedule-table" style="width: 100%;height: 300px; border-collapse: collapse; font-family: 'Roboto', sans-serif; font-size: 14px;">
                    <thead>
                        <!-- Header with dates -->
                        <tr style="background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%); color: white; height: 60px;">
                            <th rowspan="2" style="padding: 12px; text-align: center; font-weight: 600; border: 1px solid #0052a3; width: 55px; vertical-align: middle;">STT</th>
                            <th rowspan="2" style="padding: 12px; text-align: center; font-weight: 600; border: 1px solid #0052a3; min-width: 160px; vertical-align: middle;">Họ & Tên</th>
                            
                            @php
                                $dates = [];
                                $monday = \Carbon\Carbon::parse($selectedDateIso ?? now()->toDateString())->startOfWeek(\Carbon\Carbon::MONDAY);
                                for ($i = 0; $i < 5; $i++) {
                                    $dates[] = $monday->copy()->addDays($i);
                                }
                                $dayNames = ['Chủ nhật','Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
                            @endphp
                            @foreach ($dates as $date)
                                <th colspan="2" style="padding: 12px; text-align: center; font-weight: 600; border: 1px solid #0052a3; min-width: 220px;">
                                    <div style="font-size: 13px; font-weight: 700;">{{ $dayNames[$date->dayOfWeek] }}</div>
                                    <div style="font-size: 12px; font-weight: 500; margin-top: 4px;">{{ $date->format('d/m/Y') }}</div>
                                </th>
                            @endforeach
                        </tr>
                        <!-- <tr>
                            <th rowspan="1" style="padding: 12px; text-align: center; font-weight: 600; border: 1px solid #0052a3; min-width: 160px; vertical-align: middle;">Ngày</th>
                        </tr> -->
                        <!-- Sub-header with Sáng/Chiều -->
                         <!-- <tr>
                            <th rowspan="1" style="padding: 12px; text-align: center; font-weight: 600; border: 1px solid #0052a3; min-width: 160px; vertical-align: middle;">Họ &amp; Tên</th>
                         </tr> -->
                        <tr style="background: #e3f2fd; color: #0066cc;">
                                <!-- <th rowspan="1" style="padding: 12px;background:#0066cc ;text-align: center; font-weight: 600; border: 1px solid #e3f2fd; min-width: 160px; vertical-align: middle;">Họ &amp; Tên</th> -->
                            @foreach ($dates as $date)
                                <th style="padding: 8px; text-align: center; font-weight: 600; border: 1px solid #b3e5fc; font-size: 12px; background: #bbdefb;">Sáng</th>
                                <th style="padding: 8px; text-align: center; font-weight: 600; border: 1px solid #b3e5fc; font-size: 12px; background: #c5e1a5;">Chiều</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $staffList = $staffList ?? [];
                        @endphp
                        @forelse ($staffList as $staff)
                            <tr style="border-bottom: 1px solid #e0e0e0;">
                                <td style="padding: 8px; text-align: center; font-weight: 700; background: #f5f5f5; border: 1px solid #e0e0e0; width: 55px; color: #0066cc; font-size: 15px;">
                                    {{ $loop->iteration }}
                                </td>
                                <td style="padding: 10px 12px; background: #f5f5f5; border: 1px solid #e0e0e0; min-width: 160px;">
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <div style="width:38px;height:38px;border-radius:50%;background:#0066cc;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="22" height="22"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                                        </div>
                                        @php
                                            $fullName = $staff['name'] ?? 'N/A';
                                            $parts = explode('(', $fullName, 2);
                                            $displayName  = trim($parts[0]);
                                            $displayTitle = isset($parts[1]) ? '(' . trim($parts[1]) : '';
                                        @endphp
                                        <div>
                                            <div style="font-weight:600;font-size:13px;">{{ $displayName }}</div>
                                            @if($displayTitle)
                                                <div style="font-size:12px;color:#555;margin-top:2px;">{{ $displayTitle }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                @php
                                    $staffSchedules = $workSchedules[$staff['id']] ?? [];
                                @endphp
                                @foreach ($dates as $date)
                                    @php
                                        $dateStr = $date->toDateString();
                                        $morningSchedule = collect($staffSchedules)->first(fn($s) => $s['date'] === $dateStr && $s['period'] === 'morning');
                                        $afternoonSchedule = collect($staffSchedules)->first(fn($s) => $s['date'] === $dateStr && $s['period'] === 'afternoon');
                                    @endphp
                                    <td style="padding: 8px; border: 1px solid #e0e0e0; text-align: center; vertical-align: middle; background: #f9f9f9; cursor: {{ $morningSchedule ? 'pointer' : 'default' }};"
                                        @if($morningSchedule)
                                            class="schedule-cell"
                                            data-schedule-id="{{ $morningSchedule['id'] }}"
                                            data-staff-id="{{ $morningSchedule['user_id'] }}"
                                            data-staff-name="{{ $staff['name'] }}"
                                            data-date="{{ $dateStr }}"
                                            data-period="morning"
                                            data-original-period="{{ $morningSchedule['original_period'] }}"
                                            data-start-date="{{ $morningSchedule['start_date'] }}"
                                            data-end-date="{{ $morningSchedule['end_date'] }}"
                                            data-activity="{{ $morningSchedule['activity'] ?? '' }}"
                                        @endif>
                                        @if ($morningSchedule)
                                            <div style="padding: 4px 6px; background: #c8e6c9; border-radius: 3px; font-size: 12px; color: #2e7d32; text-align: left; line-height: 1.4;">
                                                {{ $morningSchedule['activity'] ?? '' }}
                                                <div style="margin-top: 4px; font-size: 11px; color: #1b5e20; font-weight: 600;">08:00 - 12:00</div>
                                            </div>
                                        @else
                                            <div style="font-size: 12px; color: #ccc;">—</div>
                                        @endif
                                    </td>
                                    <td style="padding: 8px; border: 1px solid #e0e0e0; text-align: center; vertical-align: middle; background: #f9f9f9; cursor: {{ $afternoonSchedule ? 'pointer' : 'default' }};"
                                        @if($afternoonSchedule)
                                            class="schedule-cell"
                                            data-schedule-id="{{ $afternoonSchedule['id'] }}"
                                            data-staff-id="{{ $afternoonSchedule['user_id'] }}"
                                            data-staff-name="{{ $staff['name'] }}"
                                            data-date="{{ $dateStr }}"
                                            data-period="afternoon"
                                            data-original-period="{{ $afternoonSchedule['original_period'] }}"
                                            data-start-date="{{ $afternoonSchedule['start_date'] }}"
                                            data-end-date="{{ $afternoonSchedule['end_date'] }}"
                                            data-activity="{{ $afternoonSchedule['activity'] ?? '' }}"
                                        @endif>
                                        @if ($afternoonSchedule)
                                            <div style="padding: 4px 6px; background: #ffe0b2; border-radius: 3px; font-size: 12px; color: #e65100; text-align: left; line-height: 1.4;">
                                                {{ $afternoonSchedule['activity'] ?? '' }}
                                                <div style="margin-top: 4px; font-size: 11px; color: #bf360c; font-weight: 600;">13:00 - 17:00</div>
                                            </div>
                                        @else
                                            <div style="font-size: 12px; color: #ccc;">—</div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 2 + count($dates) * 2 }}" style="padding: 32px; text-align: center; color: #999;">
                                    Không có dữ liệu lịch công tác
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
   

    @if ($currentUser && $currentUser->isAdmin())
    <div class="modal" id="registerModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div class="dialog" role="dialog" aria-modal="true" aria-label="Thêm lịch công tác" style="background: white; border-radius: 8px; box-shadow: 0 2px 16px rgba(0,0,0,0.15); max-width: 560px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <div class="dialog-head" style="padding: 18px 24px; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #0066cc, #0052a3); border-radius: 8px 8px 0 0;">
                <span style="font-size: 16px; font-weight: 600; color: white;">➕ Thêm lịch công tác</span>
                <button class="dialog-close" id="closeRegister" type="button" style="background: none; border: none; font-size: 20px; cursor: pointer; color: white; line-height: 1;">✕</button>
            </div>

            <div class="dialog-body" style="padding: 24px;">
                <form method="POST" action="{{ route('admin.schedule.work-schedule.store') }}" id="scheduleForm">
                    @csrf

                    @if ($errors->any())
                    <div style="background: #fee2e2; border: 1px solid #fca5a5; border-radius: 4px; padding: 10px 14px; margin-bottom: 16px; font-size: 13px; color: #b91c1c;">
                        {{ $errors->first() }}
                    </div>
                    @endif

                    <div style="margin-bottom: 18px;">
                        <label style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 14px;">Nhân viên <span style="color: #d9534f;">*</span></label>
                        <select name="staff_id" required style="width: 100%; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;">
                            <option value="">Chọn nhân viên</option>
                            @foreach (($staffList ?? []) as $staff)
                                <option value="{{ $staff['id'] }}">{{ $staff['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="margin-bottom: 18px;">
                        <label style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 14px;">Từ ngày <span style="color: #d9534f;">*</span></label>
                        <input type="date" name="start_date" required style="width: 100%; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;" value="{{ $selectedDateIso ?? now()->toDateString() }}">
                    </div>

                    <div style="margin-bottom: 18px;">
                        <label style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 14px;">Đến ngày <span style="color: #d9534f;">*</span></label>
                        <input type="date" name="end_date" required style="width: 100%; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;" value="{{ $selectedDateIso ?? now()->toDateString() }}">
                    </div>

                    <div style="margin-bottom: 18px;">
                        <label style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 14px;">Khoảng thời gian <span style="color: #d9534f;">*</span></label>
                        <div style="display: flex; gap: 24px; align-items: center; padding: 10px 0;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px;">
                                <input type="radio" name="period" value="morning" checked style="width: 16px; height: 16px;">
                                Sáng
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px;">
                                <input type="radio" name="period" value="afternoon" style="width: 16px; height: 16px;">
                                Chiều
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px;">
                                <input type="radio" name="period" value="both" style="width: 16px; height: 16px;">
                                Cả ngày
                            </label>
                        </div>
                    </div>

                    <div style="margin-bottom: 24px;">
                        <label style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 14px;">Hoạt động / Ghi chú <span style="color: #d9534f;">*</span></label>
                        <input name="activity" placeholder="VD: Di công tác TP. HCM" required style="width: 100%; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;">
                    </div>

                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" id="closeRegisterBtn" style="padding: 10px 24px; background: #e0e0e0; color: #333; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600;">Hủy</button>
                        <button type="submit" style="padding: 10px 24px; background: #27ae60; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600;">Lưu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <div class="modal" id="bookingDetailModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div role="dialog" aria-modal="true" style="background: white; border-radius: 8px; box-shadow: 0 2px 16px rgba(0,0,0,0.15); max-width: 420px; width: 90%; overflow: hidden;">
            <div style="padding: 18px 24px; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #0066cc, #0052a3);">
                <span style="font-size: 16px; font-weight: 600; color: white;">Chi tiết lịch công tác</span>
                <button id="closeBookingDetail" type="button" style="background: none; border: none; font-size: 20px; cursor: pointer; color: white; line-height: 1;">✕</button>
            </div>
            <div style="padding: 24px;">
                <div style="display: grid; grid-template-columns: 110px 1fr; gap: 12px 16px; font-size: 14px;">
                    <div style="font-weight: 600; color: #333;">Nhân viên</div><div id="detailStaff" style="color: #555;"></div>
                    <div style="font-weight: 600; color: #333;">Ngày</div><div id="detailDate" style="color: #555;"></div>
                    <div style="font-weight: 600; color: #333;">Khoảng</div><div id="detailPeriod" style="color: #555;"></div>
                    <div style="font-weight: 600; color: #333;">Hoạt động</div><div id="detailActivity" style="color: #555;"></div>
                </div>
                @if ($currentUser && $currentUser->isAdmin())
                <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #e0e0e0; display: flex; gap: 10px;">
                    <button type="button" id="editScheduleBtn" style="flex: 1; background: #0066cc; color: #fff; border: none; padding: 10px 16px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">✏️ Chỉnh sửa</button>
                    <form id="deleteForm" method="POST" style="flex: 1;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" id="deleteScheduleBtn" onclick="return confirm('Bạn có chắc muốn xóa lịch này không?')" style="width: 100%; background: #dc2626; color: #fff; border: none; padding: 10px 16px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">🗑 Xóa</button>
                    </form>
                </div>
                @endif
            </div>
        </div>
    </div>

    @if ($currentUser && $currentUser->isAdmin())
    <div class="modal" id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1100; align-items: center; justify-content: center;">
        <div class="dialog" role="dialog" aria-modal="true" aria-label="Chỉnh sửa lịch công tác" style="background: white; border-radius: 8px; box-shadow: 0 2px 16px rgba(0,0,0,0.15); max-width: 560px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <div class="dialog-head" style="padding: 18px 24px; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #0066cc, #0052a3); border-radius: 8px 8px 0 0;">
                <span style="font-size: 16px; font-weight: 600; color: white;">✏️ Chỉnh sửa lịch công tác</span>
                <button id="closeEditModal" type="button" style="background: none; border: none; font-size: 20px; cursor: pointer; color: white; line-height: 1;">✕</button>
            </div>
            <div class="dialog-body" style="padding: 24px;">
                @if ($errors->any())
                <div style="background: #fee2e2; border: 1px solid #fca5a5; border-radius: 4px; padding: 10px 14px; margin-bottom: 16px; font-size: 13px; color: #b91c1c;">
                    {{ $errors->first() }}
                </div>
                @endif
                <form method="POST" id="editScheduleForm" action="">
                    @csrf
                    @method('PUT')

                    <div style="margin-bottom: 18px;">
                        <label style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 14px;">Nhân viên <span style="color: #d9534f;">*</span></label>
                        <select name="staff_id" id="editStaffId" required style="width: 100%; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;">
                            <option value="">Chọn nhân viên</option>
                            @foreach (($staffList ?? []) as $staff)
                                <option value="{{ $staff['id'] }}">{{ $staff['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="margin-bottom: 18px;">
                        <label style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 14px;">Từ ngày <span style="color: #d9534f;">*</span></label>
                        <input type="date" name="start_date" id="editStartDate" required style="width: 100%; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;">
                    </div>

                    <div style="margin-bottom: 18px;">
                        <label style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 14px;">Đến ngày <span style="color: #d9534f;">*</span></label>
                        <input type="date" name="end_date" id="editEndDate" required style="width: 100%; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;">
                    </div>

                    <div style="margin-bottom: 18px;">
                        <label style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 14px;">Khoảng thời gian <span style="color: #d9534f;">*</span></label>
                        <div style="display: flex; gap: 24px; align-items: center; padding: 10px 0;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px;">
                                <input type="radio" name="period" value="morning" id="editPeriodMorning" style="width: 16px; height: 16px;"> Sáng
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px;">
                                <input type="radio" name="period" value="afternoon" id="editPeriodAfternoon" style="width: 16px; height: 16px;"> Chiều
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px;">
                                <input type="radio" name="period" value="both" id="editPeriodBoth" style="width: 16px; height: 16px;"> Cả ngày
                            </label>
                        </div>
                    </div>

                    <div style="margin-bottom: 24px;">
                        <label style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 14px;">Hoạt động / Ghi chú <span style="color: #d9534f;">*</span></label>
                        <input name="activity" id="editActivity" placeholder="VD: Di công tác TP. HCM" required style="width: 100%; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;">
                    </div>

                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" id="closeEditModalBtn" style="padding: 10px 24px; background: #e0e0e0; color: #333; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600;">Hủy</button>
                        <button type="submit" style="padding: 10px 24px; background: #0066cc; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600;">Lưu thay đổi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <script>
        // ── Modal helpers ──
        const registerModal      = document.getElementById('registerModal');
        const bookingDetailModal = document.getElementById('bookingDetailModal');
        const editModal          = document.getElementById('editModal');

        function openModal(modal) { if (modal) modal.style.display = 'flex'; }
        function closeModal(modal) { if (modal) modal.style.display = 'none'; }

        document.getElementById('registerBtn')?.addEventListener('click', () => openModal(registerModal));
        document.getElementById('closeRegister')?.addEventListener('click', () => closeModal(registerModal));
        document.getElementById('closeRegisterBtn')?.addEventListener('click', () => closeModal(registerModal));
        document.getElementById('closeBookingDetail')?.addEventListener('click', () => closeModal(bookingDetailModal));
        document.getElementById('closeEditModal')?.addEventListener('click', () => closeModal(editModal));
        document.getElementById('closeEditModalBtn')?.addEventListener('click', () => closeModal(editModal));

        [registerModal, bookingDetailModal, editModal].forEach(m => {
            m?.addEventListener('click', e => { if (e.target === m) closeModal(m); });
        });

        // Store current detail data for edit button
        let _currentSchedule = {};

        // ── Schedule cell click → show detail + delete form ──
        document.querySelectorAll('.schedule-cell[data-schedule-id]').forEach(cell => {
            cell.addEventListener('click', function () {
                const scheduleId     = this.dataset.scheduleId;
                const staffId        = this.dataset.staffId;
                const staffName      = this.dataset.staffName;
                const date           = this.dataset.date;
                const period         = this.dataset.period;
                const originalPeriod = this.dataset.originalPeriod;
                const startDate      = this.dataset.startDate;
                const endDate        = this.dataset.endDate;
                const activity       = this.dataset.activity;

                document.getElementById('detailStaff').textContent    = staffName || '-';
                document.getElementById('detailDate').textContent      = date || '-';
                document.getElementById('detailPeriod').textContent    = period === 'morning' ? 'Sáng' : (period === 'afternoon' ? 'Chiều' : 'Cả ngày');
                document.getElementById('detailActivity').textContent  = activity || '-';

                const deleteForm = document.getElementById('deleteForm');
                if (deleteForm && scheduleId) {
                    deleteForm.action = '/admin/work-schedule/' + scheduleId;
                }

                _currentSchedule = { scheduleId, staffId, staffName, originalPeriod, startDate, endDate, activity };

                openModal(bookingDetailModal);
            });
        });

        // ── Edit button → open edit modal pre-filled ──
        document.getElementById('editScheduleBtn')?.addEventListener('click', () => {
            const { scheduleId, staffId, originalPeriod, startDate, endDate, activity } = _currentSchedule;

            const form = document.getElementById('editScheduleForm');
            if (form) form.action = '/admin/work-schedule/' + scheduleId;

            const staffSelect = document.getElementById('editStaffId');
            if (staffSelect) staffSelect.value = staffId || '';

            const startInput = document.getElementById('editStartDate');
            if (startInput) startInput.value = startDate || '';

            const endInput = document.getElementById('editEndDate');
            if (endInput) endInput.value = endDate || '';

            const periodMap = { morning: 'editPeriodMorning', afternoon: 'editPeriodAfternoon', both: 'editPeriodBoth' };
            const radioId = periodMap[originalPeriod];
            if (radioId) {
                const radio = document.getElementById(radioId);
                if (radio) radio.checked = true;
            }

            const activityInput = document.getElementById('editActivity');
            if (activityInput) activityInput.value = activity || '';

            closeModal(bookingDetailModal);
            openModal(editModal);
        });
        // ── Calendar week picker ──
        (function() {
            const btn   = document.getElementById('weekPickerBtn');
            const popup = document.getElementById('calendarPopup');
            if (!btn || !popup) return;

            const navBase     = '{{ ($currentUser && $currentUser->isAdmin()) ? url('/admin/work-schedule') : url('/work-schedule') }}';
            const selectedIso = '{{ $selectedDateIso }}';
            let viewYear, viewMonth;
            { const s = new Date(selectedIso); viewYear = s.getFullYear(); viewMonth = s.getMonth(); }

            const monthNames = ['Tháng 1','Tháng 2','Tháng 3','Tháng 4','Tháng 5','Tháng 6','Tháng 7','Tháng 8','Tháng 9','Tháng 10','Tháng 11','Tháng 12'];

            function toISO(d) {
                return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
            }
            function getMonday(d) {
                const c = new Date(d); const dow = c.getDay();
                c.setDate(c.getDate() + (dow === 0 ? -6 : 1 - dow)); return c;
            }

            const selectedMonday = toISO(getMonday(new Date(selectedIso)));
            const todayISO = toISO(new Date());

            function renderCalendar() {
                const firstDay = new Date(viewYear, viewMonth, 1);
                const lastDay  = new Date(viewYear, viewMonth + 1, 0);
                let startDow = firstDay.getDay(); startDow = startDow === 0 ? 6 : startDow - 1;

                let h = '<div style="user-select:none;">';
                h += '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">';
                h += '<button id="calPrev" type="button" style="background:none;border:1px solid #ddd;border-radius:4px;width:28px;height:28px;cursor:pointer;font-size:14px;">&#8249;</button>';
                h += '<span style="font-weight:700;color:#0066cc;font-size:14px;">' + monthNames[viewMonth] + ' ' + viewYear + '</span>';
                h += '<button id="calNext" type="button" style="background:none;border:1px solid #ddd;border-radius:4px;width:28px;height:28px;cursor:pointer;font-size:14px;">&#8250;</button>';
                h += '</div>';

                ['T2','T3','T4','T5','T6','T7','CN'].forEach(d => {
                    h += '<span style="display:inline-block;width:14.28%;text-align:center;font-size:11px;font-weight:700;color:#888;padding:3px 0;">' + d + '</span>';
                });
                h += '<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:3px;margin-top:4px;">';
                for (let i = 0; i < startDow; i++) h += '<div></div>';

                for (let d = 1; d <= lastDay.getDate(); d++) {
                    const thisDate = new Date(viewYear, viewMonth, d);
                    const isoDate  = toISO(thisDate);
                    const isoMon   = toISO(getMonday(thisDate));
                    const isSelWeek = isoMon === selectedMonday;
                    const isToday   = isoDate === todayISO;
                    let bg = isSelWeek ? '#dbeafe' : 'transparent';
                    let color = '#333'; let fw = '400'; let radius = '4px';
                    if (isToday) { bg = '#0066cc'; color = 'white'; fw = '700'; radius = '50%'; }
                    else if (isSelWeek) { color = '#0066cc'; fw = '600'; }
                    h += '<div data-date="' + isoDate + '" style="text-align:center;padding:5px 0;border-radius:' + radius + ';cursor:pointer;font-size:12px;background:' + bg + ';color:' + color + ';font-weight:' + fw + ';">' + d + '</div>';
                }
                h += '</div>';
                h += '<div style="margin-top:12px;padding-top:10px;border-top:1px solid #eee;text-align:center;"><a href="' + navBase + '?date=' + todayISO + '" style="color:#0066cc;font-size:13px;font-weight:600;text-decoration:none;">Hôm nay</a></div>';
                h += '</div>';
                popup.innerHTML = h;

                popup.querySelectorAll('[data-date]').forEach(el => {
                    el.addEventListener('click', function() { location.href = navBase + '?date=' + this.dataset.date; });
                });
                document.getElementById('calPrev')?.addEventListener('click', function(e) {
                    e.stopPropagation(); viewMonth--;
                    if (viewMonth < 0) { viewMonth = 11; viewYear--; } renderCalendar();
                });
                document.getElementById('calNext')?.addEventListener('click', function(e) {
                    e.stopPropagation(); viewMonth++;
                    if (viewMonth > 11) { viewMonth = 0; viewYear++; } renderCalendar();
                });
            }

            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (popup.style.display === 'none') { renderCalendar(); popup.style.display = 'block'; }
                else popup.style.display = 'none';
            });
            document.addEventListener('click', () => popup.style.display = 'none');
            popup.addEventListener('click', e => e.stopPropagation());
        })();

        // ── Auto-dismiss notices after 5 seconds ──
        ['noticeSuccess', 'noticeDanger'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                setTimeout(() => {
                    el.style.transition = 'opacity 0.5s';
                    el.style.opacity = '0';
                    setTimeout(() => el.remove(), 500);
                }, 5000);
            }
        });

        // ── Auto-open modal on validation error ──
        @if ($errors->any())
            @if (request()->isMethod('post'))
                openModal(registerModal);
            @elseif (request()->isMethod('put') || request()->isMethod('patch'))
                openModal(editModal);
            @endif
        @endif
    </script>
</body>

</html>
