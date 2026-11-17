<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The dashboard speaks Arabic and English (Q5). A signed-in admin uses their saved language; a guest on the login
 * page uses the one chosen in this session; otherwise Arabic.
 */
class SetDashboardLocale
{
    public const SESSION_KEY = 'dashboard_locale';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys((array) config('locales.supported'));
        $admin = Auth::guard('admin')->user();
        $chosen = $admin instanceof AdminUser ? $admin->locale : $request->session()->get(self::SESSION_KEY);

        if (is_string($chosen) && in_array($chosen, $supported, true)) {
            app()->setLocale($chosen);
        }

        return $next($request);
    }
}
