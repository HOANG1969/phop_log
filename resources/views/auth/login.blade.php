<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('image/logopvgas.png') }}?v=3">
    <link rel="shortcut icon" type="image/png" href="{{ asset('image/logopvgas.png') }}?v=3">
    <title> Đăng nhập hệ thống phòng họp</title>
    <link rel="stylesheet" href="{{ asset('css/meeting.css') }}">
</head>
<body>
    <main class="login-page" style="background-image: url('{{ asset('image/background.jpg') }}'); background-size: cover; background-position: center;  display: flex; align-items: center; justify-content: center;">
        <section class="login-shell" role="region" aria-label="Đăng nhập hệ thống">
            <div class="login-brand">
                <img src="{{ asset('image/logopvgas.png') }}" alt="PVGAS Logo" class="login-logo">
                <h2 style="color: #f50606;">PVGAS LOGISTICS</h2>
                <p style="color: #0f0ce7;font-size: 14px;">Hệ thống quản lý thông tin phòng họp</p>
                    <p style="color: #0f0ce7;font-size: 14px;">Lịch công tác Ban Giám đốc</p>
            </div>

            <div class="login-form-wrap" style="color: #0f0ce7; text-align: center;" >
                <h1>ĐĂNG NHẬP</h1>

                @if ($errors->any())
                    <div class="notice danger">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form class="stack" method="POST" action="{{ route('login.attempt') }}">
                    @csrf
                    <label class="input-with-icon" aria-label="Username">
                        <span class="input-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M12 12a4.25 4.25 0 1 0-4.25-4.25A4.25 4.25 0 0 0 12 12zm0 2c-4 0-7.25 2.53-7.25 5.65A1.35 1.35 0 0 0 6.1 21h11.8a1.35 1.35 0 0 0 1.35-1.35C19.25 16.53 16 14 12 14z"/>
                            </svg>
                        </span>
                        <input class="field login-username has-icon" name="login" value="{{ old('login') }}" placeholder="Username" required>
                    </label>
                    <label class="input-with-icon" aria-label="Mật khẩu">
                        <span class="input-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M17 9h-1V7a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2zm-7-2a2 2 0 1 1 4 0v2h-4zm2 10a1.75 1.75 0 1 1 1.75-1.75A1.75 1.75 0 0 1 12 17z"/>
                            </svg>
                        </span>
                        <input class="field login-password has-icon" type="password" name="password" placeholder="Mật khẩu" required>
                    </label>
                    <label class="row-flex login-remember">
                        <input type="checkbox" name="remember" value="1">
                        <span>Ghi nhớ đăng nhập</span>
                    </label>
                    <button class="btn btn-primary login-submit" type="submit" style="margin-left: 80px;">ĐĂNG NHẬP</button>
                </form>

                
            </div>
        </section>
    </main>
</body>
</html>
