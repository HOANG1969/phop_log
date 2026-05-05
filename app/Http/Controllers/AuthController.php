<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('schedule.index');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = $credentials['login'];
        $password = $credentials['password'];

        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        try {
            if (Auth::attempt([$field => $login, 'password' => $password, 'is_active' => true], $request->boolean('remember'))) {
                $request->session()->regenerate();

                return redirect()->intended(route('schedule.index'));
            }
        } catch (QueryException $e) {
            return back()->withInput()->withErrors([
                'login' => 'Không thể đăng nhập vì database chưa kết nối được (thiếu driver hoặc sai cấu hình DB). Vui lòng cấu hình MySQL rồi thử lại.',
            ]);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors([
                'login' => 'Hệ thống đăng nhập đang gặp sự cố kết nối dữ liệu. Vui lòng kiểm tra cấu hình database.',
            ]);
        }

        return back()->withInput()->withErrors([
            'login' => 'Thông tin đăng nhập không đúng hoặc tài khoản đã bị khóa.',
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
