<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Quản lý phòng họp</title>
    <link rel="stylesheet" href="{{ asset('css/meeting.css') }}">
</head>
<body>
    <div class="admin-wrap">
        <div class="row-flex" style="justify-content: space-between; margin-bottom: 10px;">
            <h1 class="admin-title">Quản lý danh sách phòng họp</h1>
            <div class="row-flex">
                <a class="btn" href="{{ route('schedule.index') }}">Lịch phòng họp</a>
                <a class="btn" href="{{ route('admin.bookings.index') }}">Phê duyệt</a>
                <a class="btn" href="{{ route('admin.users.index') }}">Nhân sự</a>
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

        <details style="margin-bottom: 12px;" open>
            <summary><strong>Thêm phòng họp mới</strong></summary>
            <form method="POST" action="{{ route('admin.rooms.store') }}" class="stack" style="margin-top: 10px;">
                @csrf
                <div class="grid-two">
                    <input class="field" name="code" placeholder="Mã khu vực (VD: KVT)" required>
                    <input class="field" name="name" placeholder="Tên phòng họp" required>
                    <input class="field" name="location" placeholder="Vị trí phòng">
                    <input class="field" name="capacity" type="number" min="1" max="500" value="20" required>
                    <label class="row-flex"><input type="checkbox" name="has_camera" value="1"> Có camera</label>
                    <label class="row-flex"><input type="checkbox" name="is_active" value="1" checked> Đang hoạt động</label>
                </div>
                <button class="btn btn-primary" type="submit">Thêm phòng</button>
            </form>
        </details>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Mã</th>
                    <th>Tên phòng</th>
                    <th>Vị trí</th>
                    <th>Sức chứa</th>
                    <th>Camera</th>
                    <th>Trạng thái</th>
                    <th>Quản lý</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rooms as $room)
                    <tr>
                        <td>{{ $room->id }}</td>
                        <td>{{ $room->code }}</td>
                        <td>{{ $room->name }}</td>
                        <td>{{ $room->location }}</td>
                        <td>{{ $room->capacity }}</td>
                        <td>{{ $room->has_camera ? 'Có' : 'Không' }}</td>
                        <td>{{ $room->is_active ? 'Hoạt động' : 'Ẩn' }}</td>
                        <td>
                            <details>
                                <summary>Sửa</summary>
                                <form method="POST" action="{{ route('admin.rooms.update', $room) }}" class="stack" style="margin-top: 8px; min-width: 320px;">
                                    @csrf
                                    @method('PUT')
                                    <input class="field" name="code" value="{{ $room->code }}" required>
                                    <input class="field" name="name" value="{{ $room->name }}" required>
                                    <input class="field" name="location" value="{{ $room->location }}">
                                    <input class="field" name="capacity" type="number" min="1" max="500" value="{{ $room->capacity }}" required>
                                    <label class="row-flex"><input type="checkbox" name="has_camera" value="1" @checked($room->has_camera)> Có camera</label>
                                    <label class="row-flex"><input type="checkbox" name="is_active" value="1" @checked($room->is_active)> Đang hoạt động</label>
                                    <button class="btn" type="submit">Lưu</button>
                                </form>
                            </details>
                            <form method="POST" action="{{ route('admin.rooms.destroy', $room) }}" onsubmit="return confirm('Bạn có chắc muốn xóa phòng này?');" style="margin-top: 8px;">
                                @csrf
                                @method('DELETE')
                                <button class="btn" type="submit">Xóa</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">Chưa có dữ liệu phòng họp</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
