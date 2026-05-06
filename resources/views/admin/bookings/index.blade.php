<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('image/logopvgas.png') }}?v=3">
    <link rel="shortcut icon" type="image/png" href="{{ asset('image/logopvgas.png') }}?v=3">
    <title>Admin - Phê duyệt đăng ký lịch</title>
    <link rel="stylesheet" href="{{ asset('css/meeting.css') }}">
</head>
<body>
    <div class="admin-wrap">
        <div class="row-flex" style="justify-content: space-between; margin-bottom: 10px;">
            <h1 class="admin-title">Phê duyệt đăng ký lịch phòng họp</h1>
            <div class="row-flex">
                <!--<a class="btn" href="{{ route('schedule.index') }}">Lịch phòng họp</a>
                <a class="btn" href="{{ route('admin.users.index') }}">Nhân sự</a>
                <a class="btn" href="{{ route('admin.rooms.index') }}">Phòng họp</a>-->
                <button class="btn" type="button" style="border-radius: 6px; margin-right: 0px; font-size: 15px;" onclick="window.history.back();">Quay lại</button>
            </div>
        </div>

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

        <form method="GET" action="{{ route('admin.bookings.index') }}" class="row-flex" style="margin-bottom: 12px;">
            <select class="field" name="status" style="max-width: 220px;">
                <option value="pending" @selected($status === 'pending')>Chờ duyệt</option>
                <option value="approved" @selected($status === 'approved')>Đã duyệt</option>
                <option value="rejected" @selected($status === 'rejected')>Từ chối</option>
                <option value="cancelled" @selected($status === 'cancelled')>Đã hủy</option>
                <option value="all" @selected($status === 'all')>Tất cả</option>
            </select>
            <button class="btn" type="submit">Tìm kiếm</button>
        </form>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Phòng</th>
                    <th>Nội dung</th>
                    <th>Thời gian</th>
                    <th>Người đăng ký</th>
                    <th>Trạng thái</th>
                    <th>Xử lý</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bookings as $booking)
                    <tr>
                        <td>{{ $booking->id }}</td>
                        <td>{{ $booking->room?->name }}</td>
                        <td>{{ $booking->title }}</td>
                        <td>{{ $booking->start_at?->format('d/m/Y H:i') }} - {{ $booking->end_at?->format('H:i') }}</td>
                        <td>{{ $booking->organizer_name ?? $booking->requester?->name ?? 'N/A' }}</td>
                        <td>{{ strtoupper($booking->status) }}</td>
                        <td>
                            @if ($booking->status === 'pending')
                                <div class="row-flex">
                                    <form method="POST" action="{{ route('admin.bookings.approve', $booking) }}">
                                        @csrf
                                        <button class="btn btn-primary" type="submit">Duyệt</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.bookings.reject', $booking) }}" class="row-flex">
                                        @csrf
                                        <input class="field" name="rejection_reason" placeholder="Lý do từ chối" required>
                                        <button class="btn" type="submit">Từ chối</button>
                                    </form>
                                </div>
                            @elseif ($booking->status === 'approved')
                                <div class="row-flex">
                                    <small>
                                        {{ $booking->approver?->name ? 'Bởi: ' . $booking->approver->name : '' }}
                                        {{ $booking->approved_at ? ' - ' . $booking->approved_at->format('d/m/Y H:i') : '' }}
                                    </small>
                                    <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}" onsubmit="return confirm('Bạn có chắc muốn hủy lịch họp đã duyệt này không?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn" type="submit" style="background:#dc2626; color:#fff; border:none;">Hủy lịch</button>
                                    </form>
                                </div>
                            @else
                                <small>
                                    {{ $booking->approver?->name ? 'Bởi: ' . $booking->approver->name : '' }}
                                    {{ $booking->approved_at ? ' - ' . $booking->approved_at->format('d/m/Y H:i') : '' }}
                                    {{ $booking->rejection_reason ? ' - ' . $booking->rejection_reason : '' }}
                                </small>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">Chưa có đăng ký lịch</td></tr>
                @endforelse
            </tbody>
        </table>

        <div style="margin-top: 12px;">{{ $bookings->links() }}</div>
    </div>
</body>
</html>
