<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('image/logopvgas.png') }}?v=3">
    <link rel="shortcut icon" type="image/png" href="{{ asset('image/logopvgas.png') }}?v=3">
    <title>Admin - Quản lý nhân sự</title>
    <link rel="stylesheet" href="{{ asset('css/meeting.css') }}">
</head>
<body>
    <div class="admin-wrap">
        <div class="row-flex" style="justify-content: space-between; margin-bottom: 10px;">
            <h1 class="admin-title">Quản lý nhân sự người dùng</h1>
            <div class="row-flex">
                <a class="btn" href="{{ route('schedule.index') }}">Lịch phòng họp</a>
                <a class="btn" href="{{ route('admin.bookings.index') }}">Phê duyệt</a>
                <a class="btn" href="{{ route('admin.rooms.index') }}">Phòng họp</a>
            </div>
        </div>

        @if (session('success'))
            <div class="notice success">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="notice danger">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="notice danger">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="GET" action="{{ route('admin.users.index') }}" class="row-flex" style="margin-bottom: 12px;">
            <input class="field" name="q" value="{{ $keyword }}" placeholder="Tìm tên, username, email, số điện thoại, Zalo User ID, đơn vị...">
            <button class="btn" type="submit">Tìm</button>
        </form>

        <details style="margin-bottom: 12px;" open>
            <summary><strong>Tạo người dùng mới</strong></summary>
            <form method="POST" action="{{ route('admin.users.store') }}" class="stack" style="margin-top: 10px;">
                @csrf
                <div class="grid-two">
                    <input class="field" name="name" placeholder="Họ tên" required>
                    <input class="field" name="username" placeholder="Username" required>
                    <input class="field" name="email" type="email" placeholder="Email" required>
                    <input class="field" name="phone" placeholder="Số điện thoại (zalo)">
                    <input class="field" name="zalo_user_id" placeholder="Zalo User ID (OA)">
                    <input class="field" name="password" type="password" placeholder="Mật khẩu" required>
                    <select class="field" name="department" required>
                        <option value="" disabled selected>Chọn đơn vị</option>
                        <option value="KVP">KVP</option>
                        <option value="KCTV">KCTV</option>
                    </select>
                    <input class="field" name="position" placeholder="Chức vụ">
                    <select class="field" name="role" required>
                        <option value="user">user</option>
                        <option value="admin">admin</option>
                    </select>
                    <label class="row-flex"><input type="checkbox" name="is_active" value="1" checked> Kích hoạt</label>
                </div>
                <button class="btn btn-primary" type="submit">Tạo tài khoản</button>
            </form>
        </details>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Họ tên</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Số điện thoại</th>
                    <th>Zalo User ID</th>
                    <th>Đơn vị</th>
                    <th>Vai trò</th>
                    <th>Trạng thái</th>
                    <th>Cập nhật</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>{{ $user->id }}</td>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->username }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->phone ?? '-' }}</td>
                        <td>{{ $user->zalo_user_id ?? '-' }}</td>
                        <td>{{ $user->department }}</td>
                        <td>{{ $user->role }}</td>
                        <td>{{ $user->is_active ? 'Hoạt động' : 'Khóa' }}</td>
                        <td>
                            <div class="row-flex" style="gap:4px; flex-wrap:wrap;">
                                <details>
                                    <summary>Sửa</summary>
                                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="stack" style="margin-top: 8px; min-width: 320px;">
                                    @csrf
                                    @method('PUT')
                                    <input class="field" name="name" value="{{ $user->name }}" required>
                                    <input class="field" name="username" value="{{ $user->username }}" required>
                                    <input class="field" name="email" value="{{ $user->email }}" required>
                                    <input class="field" name="phone" value="{{ $user->phone }}" placeholder="Số điện thoại (zalo)">
                                    <input class="field" name="zalo_user_id" value="{{ $user->zalo_user_id }}" placeholder="Zalo User ID (OA)">
                                    <select class="field" name="department" required>
                                        <option value="" disabled @selected(empty($user->department))>Chọn đơn vị</option>
                                        <option value="KVP" @selected(strtoupper((string) $user->department) === 'KVP')>KVP</option>
                                        <option value="KCTV" @selected(strtoupper((string) $user->department) === 'KCTV')>KCTV</option>
                                    </select>
                                    <input class="field" name="position" value="{{ $user->position }}">
                                    <select class="field" name="role">
                                        <option value="user" @selected($user->role === 'user')>user</option>
                                        <option value="admin" @selected($user->role === 'admin')>admin</option>
                                    </select>
                                    <label class="row-flex"><input type="checkbox" name="is_active" value="1" @checked($user->is_active)> Kích hoạt</label>
                                    <input class="field" name="password" type="password" placeholder="Mật khẩu mới (để trống nếu giữ nguyên)">
                                    <button class="btn" type="submit">Lưu</button>
                                </form>
                            </details>
                            @if ($user->zalo_user_id)
                                <form method="POST" action="{{ route('admin.users.test-zalo', $user) }}" style="display:inline;">
                                    @csrf
                                    <button class="btn" type="submit" style="background:#0068ff;color:#fff;" onclick="return confirm('Gửi tin nhắn thử Zalo OA cho {{ $user->name }}?')">Test OA</button>
                                </form>
                            @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10">Chưa có dữ liệu người dùng</td></tr>
                @endforelse
            </tbody>
        </table>

        <div style="margin-top: 12px;">{{ $users->links() }}</div>
    </div>
</body>
</html>
