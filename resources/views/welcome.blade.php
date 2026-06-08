<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('image/logopvgas.png') }}?v=3">
    <link rel="shortcut icon" type="image/png" href="{{ asset('image/logopvgas.png') }}?v=3">
    <title >Hệ thống quản lý phòng họp PVGAS LOGISITICS</title>
    <link rel="stylesheet" href="{{ asset('css/meeting.css') }}">
</head>
<body>
    <header class="topbar app-topbar" id="scheduleHeader">
        <div class="brand">
            <!-- <h4 class="brand-logo">PETROVIETNAM<br>PV GAS</h4> -->
             <img src="{{ asset('image/logopvgas.png') }}" alt="PVGAS LOGISTICS" class="brand-logo-image">
            <h4 class="brand-heading" style="font-size: 16px;">PVGAS LOGISITICS</h4>
        </div>

        <div class="nav-actions">
            <a class="tab active" href="{{ route('schedule.index') }}" style="margin-right: 0px;">PHÒNG HỌP</a>
            @if ($currentUser && $currentUser->isAdmin())
                <a class="tab tab-with-badge" href="{{ route('admin.bookings.index') }}">
                    PHÊ DUYỆT
                    @if (($pendingApprovalCount ?? 0) > 0)
                        <span class="badge badge-inline">{{ $pendingApprovalCount }}</span>
                    @endif
                </a>
                <a class="tab" href="{{ route('admin.users.index') }}">NHÂN SỰ</a>
                <a class="tab" href="{{ route('admin.rooms.index') }}">DANH SÁCH PHÒNG</a>
                <a class="tab" href="{{ route('schedule.work-schedule') }}" style="border-radius: 6px; margin-right: 0px;">LỊCH CÔNG TÁC BGĐ</a>
            @else
                <h5>|</h5>
                <a class="tab tab-with-badge" href="{{ route('schedule.my-bookings') }}" style="border-radius: 6px; margin-left: 0px;">
                    LỊCH CỦA TÔI
                    @if (($myApprovedCount ?? 0) > 0)
                        <span class="badge badge-inline">{{ $myApprovedCount }}</span>
                    @endif
                </a>
                <h4>|</h4>
                <a class="tab" href="{{ route('schedule.work-schedule') }}" style="border-radius: 6px; margin-left: 0px;">LỊCH CÔNG TÁC BGD</a>
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
                <!-- <button class="tab" type="submit" style="background-color: blue; color: white; border-radius: 6px; margin-right: 0px;">ĐĂNG XUẤT</button> -->
                 <button class="tab" type="submit" style="background-color: green; color: white; border-radius: 6px; margin-right: 0px;">ĐĂNG XUẤT</button>
            </form>
        </div>
    </header>

    <main class="layout" id="scheduleLayout">
        <div class="page-notices">
            @if (! $databaseReady)
                <div class="notice warning">Database chưa sẵn sàng, đang hiển thị dữ liệu mẫu. Hãy cấu hình DB và chạy migrate/seed để lưu dữ liệu thật.</div>
            @endif

            @if (session('success'))
                <div class="notice success">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="notice danger">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif
        </div>

        <section class="schedule-viewport" id="scheduleViewport">
            <form class="toolbar" id="filterForm" method="GET" action="{{ route('schedule.index') }}" >
                <div class="control date-box" style="display: flex; align-items: center; gap: 4px; width: max-content; height: 30px; padding: 0 8px; border: 1px solid #ccc; border-radius: 4px; font-family: Roboto, sans-serif;">
                    <button type="button" id="prevDate">&#9664;</button>
                    <input id="dateInput" name="date" type="date" value="{{ $selectedDateIso }}" autocomplete="off">
                    <button type="button" id="nextDate">&#9654;</button>
                </div>

                <div class="control area-box" style="width: 80px; height: 30px; display: flex; align-items: center; gap: 4px;">
                    <select name="area" id="areaSelect">
                        @foreach ($areas as $area)
                            <option value="{{ $area }}" @selected($area === $selectedArea)>{{ $area === 'ALL' ? 'Tất cả' : $area }}</option>
                        @endforeach
                    </select>
                </div>

                <button class="register-btn" id="openRegister" style="height: 30px; background-color: green; color: white; border-radius: 6px;" type="button" @disabled(! $databaseReady)>+ Đăng ký</button>

                
                <button class="zoom-btn" id="toggleScheduleZoom" type="button" aria-pressed="false">⛶</button>
            </form>

            <section class="board-wrap">
                <div class="board" style="height: 650px; position: relative;">
                    <div class="board-grid">
                    @if (! is_null($nowLineMinutes))
                        <div class="timeline-now" style="--left-minutes: {{ $nowLineMinutes }};"></div>
                    @endif

                    <div class="grid-header" style="background-color: {{ $areaColors[$selectedArea] ?? '#e7e4e4' }}; height: 50px; ">
                        <div class="cell" style="font-size: 14px; color: #000;">Danh sách phòng họp </div>
                        @foreach ($hours as $hour)
                            <div class="cell time" style="color: #5c0feb;font-size: 12px;">
                                {{ $hour }}
                            </div>
                        @endforeach
                    </div>


                
                    @foreach ($rooms as $room)
                       
                        @php
                            $roomId = data_get($room, 'id');
                            $roomBookings = $bookingsByRoom[$roomId] ?? [];
                        @endphp
                
                        <div class="grid-row" data-room-id="{{ $roomId }}" style="position: relative; font-size: 12px;">
                            <div class="cell">
                                <div class="room-head" style="width: 100%;">
                                    <div class="room-head-left">
                                        <span class="dot"></span>
                                        <span class="kvt-chip">{{ data_get($room, 'code', 'LOGISTICS') }}</span>
                                        <span class="room-name">{{ data_get($room, 'name') }}</span>
                                    </div>
                                    <span class="room-right">
                                        @if (data_get($room, 'has_camera'))
                                            <span class="camera">📹</span>
                                        @endif
                                        <span class="capacity">{{ data_get($room, 'capacity', '-') }}</span>
                                    </span>
                                </div>
                            </div>

                            @foreach ($hours as $hour)
                                <div class="slot" data-start-hour="{{ $hour }}" data-end-hour="{{ $hour + 1 }}"></div>
                            @endforeach

                            @foreach ($roomBookings as $booking)
                                <div class="meeting meeting-{{ $booking['status'] ?? 'approved' }}"
                                    data-title="{{ $booking['title'] }}"
                                    data-time="{{ $booking['time_label'] ?? '' }}"
                                    data-room="{{ $booking['room_name'] ?? data_get($room, 'name') }}"
                                    data-status="{{ strtoupper($booking['status'] ?? 'approved') }}"
                                    data-organizer="{{ $booking['organizer_name'] ?? '' }}"
                                    data-internal="{{ $booking['internal_attendees'] ?? '' }}"
                                    data-external="{{ $booking['external_attendees'] ?? '' }}"
                                    data-link="{{ $booking['meeting_link'] ?? '' }}"
                                    data-notes="{{ $booking['notes'] ?? '' }}"
                                    @if (!empty($booking['can_cancel']))
                                    data-cancel-url="{{ route('schedule.bookings.cancel', $booking['id']) }}"
                                    @endif
                                    style="--left-minutes: {{ $booking['left_minutes'] }}; --duration-minutes: {{ $booking['duration_minutes'] }}; font-size: 15px;">
                                    <span class="meeting-title">@if (!empty($booking['is_online'])) 📹 @endif{{ $booking['title'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    
                    @endforeach

                    </div>

                    <div class="board-legend" aria-label="Chú thích trạng thái phòng họp">
                        <span class="legend-item legend-online">
                            <span class="legend-icon" aria-hidden="true">📹</span>
                            <span>Cuộc họp trực tuyến</span>
                        </span>
                        <span class="legend-item legend-maintenance">
                            <span class="legend-box" aria-hidden="true"></span>
                            <span>Bảo trì</span>
                        </span>
                        <span class="legend-item legend-pending">
                            <span class="legend-box" aria-hidden="true"></span>
                            <span>Chờ duyệt</span>
                        </span>
                        <span class="legend-item legend-approved">
                            <span class="legend-box" aria-hidden="true"></span>
                            <span>Đã duyệt</span>
                        </span>
                    </div>
                </div>
            </section>
        </section>
    </main>
   

    <div class="modal {{ $errors->any() ? 'open' : '' }}" id="registerModal">
        <div class="dialog" role="dialog" aria-modal="true" aria-label="Đăng ký phòng họp" style="width: 800px;">
            <div class="dialog-head" style="font-size: 18px; font-weight: 500;background-color: green; color: white;">
                <span>✎ Đăng ký</span>
                <button class="dialog-close" id="closeRegister" type="button">✕</button>
            </div>

            <div class="dialog-body">
                <form method="POST" action="{{ route('schedule.bookings.store') }}">
                    @csrf
                    <input type="hidden" name="area" value="{{ $selectedArea }}">
                    @php
                        $timeOptions = [];
                        for ($hour = 0; $hour < 24; $hour++) {
                            foreach ([0, 30] as $minute) {
                                $timeOptions[] = sprintf('%02d:%02d', $hour, $minute);
                            }
                        }
                    @endphp

                    <div class="f-row">
                        <div class="f-label">Bắt đầu <span class="req">*</span></div>
                        <div class="f-inline">
                            <input class="field"  id="registerStartDate" type="date" name="start_date" value="{{ old('start_date', $selectedDateIso) }}" required>
                            <select class="field" style="width: 125px;" id="registerStartTime" name="start_time" required>
                                @foreach ($timeOptions as $timeOption)
                                    <option value="{{ $timeOption }}" @selected(old('start_time', '12:00') === $timeOption)>{{ $timeOption }}</option>
                                @endforeach
                            </select>
                            <input class="field" id="registerEndDate" type="date" name="end_date" value="{{ old('end_date', $selectedDateIso) }}" required>
                            <select class="field" style="width: 125px;" id="registerEndTime" name="end_time" required>
                                @foreach ($timeOptions as $timeOption)
                                    <option value="{{ $timeOption }}" @selected(old('end_time', '13:00') === $timeOption)>{{ $timeOption }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="f-row">
                        <div class="f-label">Phòng họp <span class="req">*</span></div>
                        <div class="row-flex">
                            <select class="field" id="registerMeetingRoom" name="meeting_room_id" required>
                                <option value="">Chọn phòng họp</option>
                                @foreach ($rooms as $room)
                                    <option value="{{ data_get($room, 'id') }}" @selected((string) old('meeting_room_id') === (string) data_get($room, 'id'))>
                                        ({{ data_get($room, 'code') }}) {{ data_get($room, 'name') }}
                                    </option>
                                @endforeach
                            </select>

                            <label class="row-flex online-wrap">
                                <input class="check" type="checkbox" name="is_online" value="1" @checked(old('is_online'))>
                                <span>Trực tuyến</span>
                            </label>
                        </div>
                    </div>

                    <div class="f-row">
                        <div class="f-label">Người đăng ký <span class="req">*</span></div>
                        <div class="f-inline organizer-inline">
                            <input class="field" name="organizer_name" value="{{ old('organizer_name') }}" placeholder="Họ và tên" required>
                            <input class="field" name="organizer_phone" value="{{ old('organizer_phone') }}" placeholder="Số điện thoại" required>
                            <select class="field" name="organizer_department" required>
                                <option value="">Phòng ban</option>
                                @foreach (['TCHC', 'KTAT', 'TCKT', 'KHĐT', 'KTPT', 'TMLG', 'KCTV'] as $departmentOption)
                                    <option value="{{ $departmentOption }}" @selected(old('organizer_department') === $departmentOption)>{{ $departmentOption }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="hint organizer-hint" style="font-size: 12px; font-style: italic;">Vui lòng nhập đúng số điện thoại.</div>
                    </div>

                    <div class="f-row">
                        <div class="f-label">Nội dung họp <span class="req">*</span></div>
                        <input class="field" name="title" value="{{ old('title') }}" placeholder="Nhập nội dung họp" required>
                    </div>

                    <div class="f-row">
                        <div class="f-label">Trái cây, bánh kẹo</div>
                        <label class="row-flex">
                            <input class="check" type="checkbox" name="snacks_requested" value="1" @checked(old('snacks_requested'))>
                            <span class="hint" style="font-size: 12px;">Trái cây, bánh kẹo sẽ được phục vụ sau khi được duyệt</span>
                        </label>
                    </div>

                    <!-- <div class="f-row">
                        <div class="f-label">Người tham dự (Nội bộ)</div>
                        <input class="field" name="internal_attendees" value="{{ old('internal_attendees') }}" placeholder="Chọn người tham dự nội bộ">
                    </div>

                    <div class="f-row">
                        <div class="f-label">Người tham dự (Bên ngoài)</div>
                        <input class="field" name="external_attendees" value="{{ old('external_attendees') }}" placeholder="Nhập email người tham dự bên ngoài, cách nhau bởi dấu phẩy">
                    </div>

                    <div class="f-row">
                        <div class="f-label">Tài liệu (link)</div>
                        <input class="field" name="meeting_link" value="{{ old('meeting_link') }}" placeholder="Nhập địa chỉ URL tài liệu">
                    </div>

                    <div class="f-row">
                        <div class="f-label">Ghi chú</div>
                        <textarea class="field textarea" name="notes" placeholder="Ghi chú thêm...">{{ old('notes') }}</textarea>
                    </div> -->

                    <div class="submit-wrap" ">
                        <button class="submit" type="submit" @disabled(! $databaseReady)style="background-color: green;">✎ ĐĂNG KÝ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="bookingDetailModal" ">
        <div class="dialog detail-dialog" role="dialog" aria-modal="true" aria-label="Chi tiết lịch họp" style="width: 400px; ">
            <div class="dialog-head" style="background-color: green;">
                <span style="font-size: 20px;">Chi tiết lịch họp</span>
                <button class="dialog-close" id="closeBookingDetail" type="button">✕</button>
            </div>
            <div class="dialog-body">
                <div class="detail-grid">
                    <div class="detail-label">Nội dung</div><div id="detailTitle"></div>
                    <div class="detail-label">Thời gian</div><div id="detailTime"></div>
                    <div class="detail-label">Phòng họp</div><div id="detailRoom"></div>
                    <div class="detail-label">Trạng thái</div><div id="detailStatus"></div>
                    <div class="detail-label">Người đăng ký</div><div id="detailOrganizer"></div>
                    <!-- <div class="detail-label">Tham dự nội bộ</div><div id="detailInternal"></div> -->
                    <!-- <div class="detail-label">Tham dự bên ngoài</div><div id="detailExternal"></div>
                    <div class="detail-label">Tài liệu</div><div id="detailLink"></div> -->
                    <!-- <div class="detail-label">Ghi chú</div><div id="detailNotes"></div> -->
                </div>
                <div id="cancelBookingWrap" style="margin-top: 16px; display: none;">
                    <form id="cancelBookingForm" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="button" id="cancelBookingBtn"
                            onclick="if(confirm('Bạn có chắc muốn hủy lịch họp này không?')) { document.getElementById('cancelBookingForm').submit(); }"
                            class="btn" style="background:#dc2626; color:#fff; border:none; padding:6px 16px; border-radius:4px; cursor:pointer;">
                            Hủy lịch
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>


    <script src="{{ asset('js/meeting.js') }}" defer></script>
</body>

</html>
