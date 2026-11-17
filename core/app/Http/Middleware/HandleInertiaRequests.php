<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Shared with every dashboard page: language and direction, the signed-in admin, the dashboard strings for the
     * current language (lang/{locale}/dashboard.php), and one-time flash messages.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = app()->getLocale();
        $admin = Auth::guard('admin')->user();
        $strings = trans('dashboard');

        return [
            ...parent::share($request),
            'locale' => $locale,
            'dir' => config("locales.supported.$locale.dir", 'rtl'),
            'locales' => array_keys((array) config('locales.supported')),
            'siteName' => config("locales.supported.$locale.site_name"),
            'admin' => $admin instanceof AdminUser ? [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->roleCode()->value,
                'roleLabel' => $admin->roleCode()->label(),
            ] : null,
            't' => is_array($strings) ? $strings : [],
            'flash' => [
                'status' => $request->session()->get('status'),
            ],
        ];
    }
}
