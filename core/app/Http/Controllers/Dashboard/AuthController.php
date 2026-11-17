<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Support\Activity\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard sign-in for staff (guard "admin"). Five tries a minute per e-mail and IP; every sign-in, failure and
 * sign-out is in the activity log.
 */
class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request, ActivityLogger $log): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);
        $email = Str::lower($request->string('email')->toString());
        $password = $request->string('password')->toString();
        $throttleKey = 'dashboard-login:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => __('dashboard.login.throttled', ['seconds' => $seconds]),
            ]);
        }

        $ok = Auth::guard('admin')->attempt(
            ['email' => $email, 'password' => $password, 'is_active' => true],
        );

        if (! $ok) {
            RateLimiter::hit($throttleKey, 60);
            $log->record('admin.login_failed', null, null, ['email' => $email]);
            throw ValidationException::withMessages([
                'email' => __('dashboard.login.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $admin = Auth::guard('admin')->user();
        if ($admin instanceof AdminUser) {
            // Quiet save: the login itself is logged once below, not as an "admin_users.updated" line.
            $admin->forceFill(['last_login_at' => now()])->saveQuietly();
            $log->record('admin.login', $admin);
        }

        return redirect()->intended(route('dashboard.home'));
    }

    public function destroy(Request $request, ActivityLogger $log): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();
        if ($admin instanceof AdminUser) {
            $log->record('admin.logout', $admin);
        }

        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('dashboard.login');
    }
}
