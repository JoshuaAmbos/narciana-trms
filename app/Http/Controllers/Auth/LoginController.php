<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;

class LoginController extends Controller
{
    /** Set at login, removed at logout. Still there without a session = the session timed out. */
    public const SEEN_COOKIE = 'narciana_was_logged_in';

    public function show(Request $request): View
    {
        $timedOut = $request->boolean('timeout') && $request->cookie(self::SEEN_COOKIE);

        if ($timedOut) {
            Cookie::queue(Cookie::forget(self::SEEN_COOKIE));
        }

        return view('auth.login', [
            'timedOut' => $timedOut,
            'minutes' => config('session.lifetime'),
        ]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticate();

        Auth::login($user);
        $request->session()->regenerate(); // new session ID after login (prevents session fixation)

        AuditLog::record('Login', 'tbl_user', $user->user_id);
        Cookie::queue(self::SEEN_COOKIE, '1', 60 * 24 * 30);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        AuditLog::record('Logout', 'tbl_user', Auth::id());

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Cookie::queue(Cookie::forget(self::SEEN_COOKIE));

        return redirect()->route('login')->with('status', 'You have logged out.');
    }
}
