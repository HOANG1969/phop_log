<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('image/logopvgas.png') }}?v=3">
    <link rel="shortcut icon" type="image/png" href="{{ asset('image/logopvgas.png') }}?v=3">
    <title>Lịch của tôi - PVGAS LOGISTICS</title>
    <link rel="stylesheet" href="{{ asset('css/meeting.css') }}">
</head>
<body>
    <header class="topbar app-topbar">
        <div class="brand">
            <img src="{{ asset('image/logopvgas.png') }}" alt="PVGAS LOGISTICS" class="brand-logo-image">
            <h4 class="brand-heading" style="font-size:16px;"> PVGAS LOGISITICS</h4>
        </div>

        <div class="nav-actions">
            <a class="tab" href="{{ route('schedule.index') }}" style="margin-right: 0px;">PHÒNG HỌP</a>
            @if ($currentUser && $currentUser->isAdmin())
                <a class="tab tab-with-badge" href="{{ route('admin.bookings.index') }}">
                    PHÊ DUYỆT
                    @if (($pendingApprovalCount ?? 0) > 0)
                        <span class="badge badge-inline">{{ $pendingApprovalCount }}</span>
                    @endif
                </a>
                <a class="tab" href="{{ route('admin.users.index') }}">NHÂN SỰ</a>
                <a class="tab" href="{{ route('admin.rooms.index') }}">DANH SÁCH PHÒNG</a>
            @else
                <h5>|</h5>
                <a class="tab tab-with-badge active" href="{{ route('schedule.my-bookings') }}" style="border-radius: 6px; margin-left: 0px;">
                    LỊCH CỦA TÔI
                    @if (($myApprovedCount ?? 0) > 0)
                        <span class="badge badge-inline">{{ $myApprovedCount }}</span>
                    @endif
                </a>
                <h4>|</h4>
                <!-- <button class="tab" type="button" style="border-radius: 6px; margin-right: 0px;">THỐNG KÊ</button> -->
                 <a class="tab" href="{{ route('schedule.work-schedule') }}" style="border-radius: 6px; margin-right: 0px;">LỊCH CÔNG TÁC BGĐ</a>
                 <!-- <a class="tab active" href="{{ route('schedule.my-bookings') }}" style="border-radius: 6px; margin-left: 0px;">LỊCH CỦA TÔI</a> -->
            @endif
            <!-- <div class="user-pill">
                <span class="avatar"></span>
                {{ $currentUser?->name ?? 'Người dùng' }}
            </div> -->
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <!-- <button class="tab" type="submit">ĐĂNG XUẤT</button> -->
                 <button class="tab" type="submit" style="background-color: green; color: white; border-radius: 6px; margin-right: 0px;">ĐĂNG XUẤT</button>
            </form>
        </div>
    </header>

    <main class="layout">
        @if (! $databaseReady)
            <div class="notice warning">Database chưa sẵn sàng, chưa thể tải lịch sử đăng ký. Vui lòng cấu hình DB và thử lại.</div>
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

        <section class="admin-wrap">
            <div class="row-flex" style="justify-content: space-between; margin-bottom: 12px;">
                <h2 class="admin-title" style="margin: 0;">Lịch sử đăng ký của tôi</h2>
                <a class="tab" href="{{ route('schedule.index') }}" style="background-color: green; color:white; font-size:13px;"> Quay lại</a>
            </div>

            @if (! $databaseReady)
                <p>Không thể tải dữ liệu lịch sử do chưa kết nối được database.</p>
            @elseif ($bookings->isEmpty())
                <p>Bạn chưa có lịch đăng ký nào.</p>
            @else
                <div style="overflow-x: auto;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>Nội dung</th>
                                <th>Phòng họp</th>
                                <th>Bắt đầu</th>
                                <th>Kết thúc</th>
                                <th>Trạng thái</th>
                                <th>Ngày đăng ký</th>
                                <th>Người đăng ký</th>
                                <th>Phòng ban</th>
                                <!-- <th>Người duyệt</th> -->
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bookings as $index => $booking)
                                <tr>
                                    <td>{{ $bookings->firstItem() + $index }}</td>
                                    <td>{{ $booking->title }}</td>
                                    <td>{{ $booking->room?->name ?? '-' }}</td>
                                    <td>{{ $booking->start_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td>{{ $booking->end_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td>{{ $statusLabels[$booking->status] ?? ucfirst((string) $booking->status) }}</td>
                                    <td>{{ $booking->created_at?->copy()->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td>{{ $booking->organizer_name ?? '-' }}</td>
                                    <td>{{ $booking->organizer_department ?? '-' }}</td>
                                    <!-- <td>{{ $booking->approver?->name ?? '-' }}</td> -->
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 12px;">
                    {{ $bookings->links() }}
                </div>
            @endif
        </section>
    </main>

    <script>
        (function () {
            const notices = document.querySelectorAll('.notice.success, .notice.danger');
            notices.forEach(function (notice) {
                window.setTimeout(function () {
                    notice.classList.add('is-hiding');
                    window.setTimeout(function () {
                        notice.remove();
                    }, 350);
                }, 5000);
            });
        })();
    </script>
</body>
</html>
