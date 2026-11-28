<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use App\Support\Staff\StaffPasswords;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * An account switched off while signed in is signed out on its next request, and so is a session from before the
 * password changed (G13: a new password signs that person out everywhere). A session that has no mark yet gets one.
 */
class EnsureAdminActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();
        if (! $admin instanceof AdminUser) {
            return $next($request);
        }

        $mark = StaffPasswords::sessionMark($admin);
        $stored = $request->session()->get(StaffPasswords::SESSION_KEY);
        $stale = is_string($stored) && str_starts_with($stored, $admin->id.'|') && ! hash_equals($mark, $stored);
        if (! $admin->is_active || $stale) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('dashboard.login');
        }
        if (! is_string($stored) || ! str_starts_with($stored, $admin->id.'|')) {
            $request->session()->put(StaffPasswords::SESSION_KEY, $mark);
        }

        return $next($request);
    }
}
