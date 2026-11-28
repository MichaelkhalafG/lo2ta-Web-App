<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Support\Dashboard\Text;
use App\Support\Staff\StaffPasswords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My account" for every staff member (G13): their own details and a password change that needs the current
 * password. Five wrong current passwords in ten minutes pause the form. The change signs out their other sessions.
 */
class AccountController extends Controller
{
    private const MAX_WRONG = 5;

    public function show(Request $request): Response
    {
        $admin = AdminUser::query()->findOrFail(self::me($request)->id);

        return Inertia::render('Account/Index', [
            'account' => [
                'name' => $admin->name,
                'email' => $admin->email,
                'role_label' => $admin->roleCode()->label(),
                'last_login_at' => $admin->last_login_at?->timezone('Africa/Cairo')->format('Y-m-d H:i'),
            ],
            'minLength' => StaffPasswords::MIN_LENGTH,
        ]);
    }

    public function updatePassword(Request $request, StaffPasswords $passwords): RedirectResponse
    {
        $admin = self::me($request);
        $request->validate([
            'current_password' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:'.StaffPasswords::MIN_LENGTH, 'max:255', 'confirmed', 'different:current_password'],
        ]);
        $key = 'staff-password-change:'.$admin->id;
        if (RateLimiter::tooManyAttempts($key, self::MAX_WRONG)) {
            throw ValidationException::withMessages(['current_password' => Text::get('account.throttled', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)])]);
        }
        if (! $passwords->changeOwn($admin, $request->string('current_password')->toString(), $request->string('password')->toString(), $request)) {
            RateLimiter::hit($key, 600);
            throw ValidationException::withMessages(['current_password' => Text::get('account.wrong_current')]);
        }
        RateLimiter::clear($key);

        return back()->with('status', Text::get('account.password_changed'));
    }

    private static function me(Request $request): AdminUser
    {
        $admin = $request->user('admin');
        if (! $admin instanceof AdminUser) {
            abort(403);
        }

        return $admin;
    }
}
