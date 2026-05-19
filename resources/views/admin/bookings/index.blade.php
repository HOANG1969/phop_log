<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('image/logopvgas.png') }}?v=3">
    <link rel="shortcut icon" type="image/png" href="{{ asset('image/logopvgas.png') }}?v=3">
    <title>Admin - Phê duyệt đăng ký lịch</title>
    <link rel="stylesheet" href="{{ asset('css/meeting.css') }}">
    <style>
        .admin-wrap.bookings-page {
            max-width: none;
            width: calc(100vw - 24px);
            margin: 12px auto;
        }

        .bookings-table-wrap {
            overflow-x: auto;
        }

        .bookings-table {
            width: 100%;
            min-width: 1180px;
            table-layout: auto;
        }

        .bookings-table col.col-id { width: 60px; }
        .bookings-table col.col-room { width: 150px; }
        .bookings-table col.col-title { width: auto; }
        .bookings-table col.col-time { width: 210px; }
        .bookings-table col.col-organizer { width: 160px; }
        .bookings-table col.col-status { width: 120px; }
        .bookings-table col.col-action { width: 130px; }
        .bookings-table col.col-note { width: 250px; }

        .bookings-table td {
            vertical-align: top;
            word-break: break-word;
            white-space: normal;
        }

        .bookings-table td.nowrap,
        .bookings-table th.nowrap {
            white-space: nowrap;
        }

        .bookings-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: flex-start;
        }

        .bookings-actions form {
            margin: 0;
        }

        .bookings-small {
            display: inline-block;
            max-width: 320px;
            line-height: 1.4;
            white-space: normal;
        }

        .bookings-pagination {
            margin-top: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .bookings-page-btn {
            display: inline-block;
            border: 1px solid #d4dce6;
            background: #fff;
            border-radius: 4px;
            padding: 6px 10px;
            color: #2f4661;
            text-decoration: none;
            font-size: 14px;
        }

        .bookings-page-btn.is-disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        .bookings-summary {
            margin-top: 6px;
            color: #334155;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="admin-wrap bookings-page">
        <div class="row-flex" style="justify-content: space-between; margin-bottom: 10px;">
            <h1 class="admin-title">Phê duyệt đăng ký lịch phòng họp</h1>
            <div class="row-flex">
                <!--<a class="btn" href="{{ route('schedule.index') }}">Lịch phòng họp</a>
                <a class="btn" href="{{ route('admin.users.index') }}">Nhân sự</a>
                <a class="btn" href="{{ route('admin.rooms.index') }}">Phòng họp</a>-->
                <!-- <a class="btn" type="button" style="border-radius: 6px; margin-right: 0px; font-size: 15px;" onclick="window.history.back();">Quay lại</a> -->
                <a class="btn" type="button" style="border-radius: 6px; margin-right: 0px; font-size: 15px;text-decoration: none; background-color:green; color:white" href="{{ route('schedule.index') }}">Quay lại</a>
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

        <form method="GET" action="{{ route('admin.bookings.index') }}" class="row-flex" style="margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
            <select class="field" name="status" style="max-width: 220px;">
                <option value="pending" @selected($status === 'pending')>Chờ duyệt</option>
                <option value="approved" @selected($status === 'approved')>Đã duyệt</option>
                <option value="rejected" @selected($status === 'rejected')>Từ chối</option>
                <option value="cancelled" @selected($status === 'cancelled')>Đã hủy</option>
                <option value="all" @selected($status === 'all')>Tất cả</option>
            </select>
            <button class="btn" type="submit">Tìm kiếm</button>
        </form>

        <div class="bookings-table-wrap">
        <table class="admin-table bookings-table">
            <colgroup>
                <col class="col-id">
                <col class="col-room">
                <col class="col-title">
                <col class="col-time">
                <col class="col-organizer">
                <col class="col-status">
                <col class="col-action">
                <col class="col-note">
            </colgroup>
            <thead>
                <tr>
                    <th class="nowrap">ID</th>
                    <th class="nowrap">Phòng</th>
                    <th>Nội dung</th>
                    <th class="nowrap">Thời gian</th>
                    <th class="nowrap">Người đăng ký</th>
                    <th class="nowrap">Trạng thái</th>
                    <th class="nowrap">Xử lý</th>
                    <th>Thông tin xử lý</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bookings as $booking)
                    <tr>
                        <td class="nowrap">{{ $booking->id }}</td>
                        <td class="nowrap">{{ $booking->room?->name }}</td>
                        <td>{{ $booking->title }}</td>
                        <td class="nowrap">{{ $booking->start_at?->format('d/m/Y H:i') }} - {{ $booking->end_at?->format('H:i') }}</td>
                        <td class="nowrap">{{ $booking->organizer_name ?? $booking->requester?->name ?? 'N/A' }}</td>
                        <td class="nowrap">{{ strtoupper($booking->status) }}</td>
                        <td>
                            @if ($booking->status === 'pending')
                                <div class="bookings-actions">
                                    <form method="POST" action="{{ route('admin.bookings.approve', $booking) }}">
                                        @csrf
                                        <button class="btn btn-primary" type="submit">Duyệt</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.bookings.reject', $booking) }}" onsubmit="return submitRejectReason(this);">
                                        @csrf
                                        <input type="hidden" name="rejection_reason" value="">
                                        <button class="btn" type="submit">Từ chối</button>
                                    </form>
                                </div>
                            @elseif ($booking->status === 'approved')
                                <div class="bookings-actions">
                                    <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}" onsubmit="return confirm('Bạn có chắc muốn hủy lịch họp đã duyệt này không?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn" type="submit" style="background:#dc2626; color:#fff; border:none;">Hủy lịch</button>
                                    </form>
                                </div>
                            @else
                                <span class="bookings-page-btn is-disabled">Đã xử lý</span>
                            @endif
                        </td>
                        <td>
                            @if ($booking->status === 'pending')
                                <span>-</span>
                            @else
                                <small class="bookings-small">
                                    {{ $booking->approver?->name ? 'Bởi: ' . $booking->approver->name : '' }}
                                    {{ $booking->approved_at ? ' - ' . $booking->approved_at->format('d/m/Y H:i') : '' }}
                                    {{ $booking->rejection_reason ? ' - ' . $booking->rejection_reason : '' }}
                                </small>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">Chưa có đăng ký lịch</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>

        @if ($bookings->hasPages())
            <div class="bookings-pagination">
                @if ($bookings->onFirstPage())
                    <span class="bookings-page-btn is-disabled">Trước</span>
                @else
                    <a class="bookings-page-btn" href="{{ $bookings->previousPageUrl() }}">Trước</a>
                @endif

                <span class="bookings-page-btn is-disabled">Trang {{ $bookings->currentPage() }}/{{ $bookings->lastPage() }}</span>

                @if ($bookings->hasMorePages())
                    <a class="bookings-page-btn" href="{{ $bookings->nextPageUrl() }}">Tiếp</a>
                @else
                    <span class="bookings-page-btn is-disabled">Tiếp</span>
                @endif
            </div>
            <div class="bookings-summary">
                Hiển thị {{ $bookings->firstItem() ?? 0 }}-{{ $bookings->lastItem() ?? 0 }} / tổng {{ $bookings->total() }} bản ghi
            </div>
        @endif
    </div>

    <script>
        function submitRejectReason(form) {
            const reason = window.prompt('Nhập lý do từ chối:');
            if (reason === null) {
                return false;
            }

            const normalizedReason = reason.trim();
            if (normalizedReason === '') {
                alert('Vui lòng nhập lý do từ chối.');
                return false;
            }

            const input = form.querySelector('input[name="rejection_reason"]');
            if (!input) {
                return false;
            }

            input.value = normalizedReason;
            return true;
        }
    </script>
</body>
</html>
