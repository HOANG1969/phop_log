<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><img src="{{ asset('image/logopvgas.png') }}" alt="PVGAS Logo"> Đăng nhập hệ thống phòng họp2</title>
    <link rel="stylesheet" href="{{ asset('css/meeting.css') }}">
</head>
<body>
    <main class="login-page" style="background-image: url('{{ asset('image/background.jpg') }}'); background-size: cover; background-position: center;  display: flex; align-items: center; justify-content: center;">
        <section class="login-shell" role="region" aria-label="Đăng nhập hệ thống" style="width: 750px; height: 400px;">
            <div class="login-brand" style="width: 350px; height:400px;">
                <img src="{{ asset('image/logopvgas.png') }}"  style="width: 200px; height: 200px;" alt="PVGAS Logo" class="login-logo">
                <h1>PVGAS LOGISTICS</h1>
                <p style="color: #0f0ce7;">Hệ thống quản lý thông tin phòng họp</p>
                    <p style="color: #0f0ce7;">Lịch công tác Ban Giám đốc</p>
            </div>

            <div class="login-form-wrap" style="margin-top: 2px; color: #0f0ce7; width: 400px; height: 400px;" >
                <h1 style="margin-left:70px;">ĐĂNG NHẬP</h1>

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
                        <input class="field login-username has-icon" name="login" value="{{ old('login') }}" placeholder="Username" required style="width: 300px; ">
                    </label>
                    <label class="input-with-icon" aria-label="Mật khẩu">
                        <span class="input-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M17 9h-1V7a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2zm-7-2a2 2 0 1 1 4 0v2h-4zm2 10a1.75 1.75 0 1 1 1.75-1.75A1.75 1.75 0 0 1 12 17z"/>
                            </svg>
                        </span>
                        <input class="field login-password has-icon" type="password" name="password" placeholder="Mật khẩu" required style="width: 300px; ">
                    </label>
                    <label class="row-flex login-remember">
                        <input type="checkbox" name="remember" value="1">
                        <span>Ghi nhớ đăng nhập</span>
                    </label>
                    <button class="btn btn-primary login-submit" type="submit" style="font-size: 20px; width: 150px; margin: 40px 80px; margin-left: 90px; margin-top: 60px;border-radius: 10px; size-text:20px;">ĐĂNG NHẬP</button>
                </form>

                
            </div>
        </section>
    </main>
</body>
</html>
