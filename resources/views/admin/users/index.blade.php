<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        @if ($errors->any())
            <div class="notice danger">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="GET" action="{{ route('admin.users.index') }}" class="row-flex" style="margin-bottom: 12px;">
            <input class="field" name="q" value="{{ $keyword }}" placeholder="Tìm tên, username, email, phòng ban...">
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
                    <input class="field" name="password" type="password" placeholder="Mật khẩu" required>
                    <input class="field" name="department" placeholder="Phòng ban">
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
                    <th>Phòng ban</th>
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
                        <td>{{ $user->department }}</td>
                        <td>{{ $user->role }}</td>
                        <td>{{ $user->is_active ? 'Hoạt động' : 'Khóa' }}</td>
                        <td>
                            <details>
                                <summary>Sửa</summary>
                                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="stack" style="margin-top: 8px; min-width: 320px;">
                                    @csrf
                                    @method('PUT')
                                    <input class="field" name="name" value="{{ $user->name }}" required>
                                    <input class="field" name="username" value="{{ $user->username }}" required>
                                    <input class="field" name="email" value="{{ $user->email }}" required>
                                    <input class="field" name="department" value="{{ $user->department }}">
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
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">Chưa có dữ liệu người dùng</td></tr>
                @endforelse
            </tbody>
        </table>

        <div style="margin-top: 12px;">{{ $users->links() }}</div>
    </div>
</body>
</html>
