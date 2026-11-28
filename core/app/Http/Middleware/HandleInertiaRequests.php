<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use App\Support\Dashboard\Navigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /** Abilities the dashboard UI asks about (the server enforces them again on every route). */
    private const ABILITIES = ['manage-settings', 'manage-site-texts', 'manage-admins', 'manage-commissions', 'view-activity', 'export-data', 'review-units', 'verify-units', 'work-clients', 'suspend-users'];

    /**
     * Shared with every dashboard page: language and direction, the signed-in admin, the sidebar for their role,
     * their abilities, the dashboard strings for the current language (lang/{locale}/dashboard.php), and one-time
     * flash messages.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = app()->getLocale();
        $admin = Auth::guard('admin')->user();
        $strings = trans('dashboard');
        $signedIn = $admin instanceof AdminUser;

        $abilities = [];
        foreach (self::ABILITIES as $ability) {
            $abilities[$ability] = $signedIn && Gate::forUser($admin)->allows($ability);
        }

        return [
            ...parent::share($request),
            'locale' => $locale,
            'dir' => config("locales.supported.$locale.dir", 'rtl'),
            'locales' => array_keys((array) config('locales.supported')),
            'siteName' => config("locales.supported.$locale.site_name"),
            'admin' => $signedIn ? [
                'name' => $admin->name,
                'email' => $admin->email,
                'initials' => self::initials($admin->name),
                'role' => $admin->roleCode()->value,
                'roleLabel' => $admin->roleCode()->label(),
            ] : null,
            'nav' => $signedIn ? Navigation::for($admin, $request) : [],
            'abilities' => $abilities,
            't' => is_array($strings) ? $strings : [],
            'flash' => [
                'status' => $request->session()->get('status'),
                'warning' => $request->session()->get('warning'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }

    private static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = array_map(static fn (string $p): string => mb_substr($p, 0, 1), array_slice($parts, 0, 2));

        return mb_strtoupper(implode('', $letters));
    }
}
