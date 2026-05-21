<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AutoLogoutOnInactivity
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $timeoutMinutes = (int) env('AUTO_LOGOUT_IDLE_MINUTES', 30);
        $timeoutSeconds = max(1, $timeoutMinutes) * 60;
        $lastActivityAt = (int) $request->session()->get('last_activity_at', 0);
        $currentTime = time();

        if ($lastActivityAt > 0 && ($currentTime - $lastActivityAt) > $timeoutSeconds) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Phiên đăng nhập đã hết hạn do không thao tác. Vui lòng đăng nhập lại.',
                ], 401);
            }

            return redirect()->route('login')
                ->withErrors(['login' => '']);
        }

        $request->session()->put('last_activity_at', $currentTime);

        return $next($request);
    }
}
