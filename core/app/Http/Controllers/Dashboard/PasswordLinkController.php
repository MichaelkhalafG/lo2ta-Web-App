<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Support\Activity\ActivityLogger;
use App\Support\Dashboard\Text;
use App\Support\Staff\StaffPasswords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The set-password page a staff member reaches from an invite or reset link (G13, G14), signed out. The link is
 * one-use and works for 60 minutes; a wrong or old link shows one neutral message and never says whether an account
 * exists. Ten bad links an hour from one address pause the page.
 */
class PasswordLinkController extends Controller
{
    private const MAX_BAD = 10;

    public function edit(Request $request, string $token, StaffPasswords $passwords): Response
    {
        $link = self::tooMany($request) ? null : $passwords->find($token);
        if ($link === null) {
            RateLimiter::hit(self::key($request), 3600);
        }

        return Inertia::render('Auth/SetPassword', [
            'token' => $link === null ? null : $token,
            'purpose' => $link?->purpose,
            'name' => $link?->admin?->name,
            'minLength' => StaffPasswords::MIN_LENGTH,
        ]);
    }

    public function update(Request $request, string $token, StaffPasswords $passwords, ActivityLogger $log): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'min:'.StaffPasswords::MIN_LENGTH, 'max:255', 'confirmed'],
        ]);
        $link = self::tooMany($request) ? null : $passwords->find($token);
        if ($link === null) {
            RateLimiter::hit(self::key($request), 3600);
            $log->record('admin.password_link_refused');
            throw ValidationException::withMessages(['password' => Text::get('password_link.invalid')]);
        }
        $passwords->useLink($link, $request->string('password')->toString());

        return redirect()->route('dashboard.login')->with('status', Text::get('password_link.done'));
    }

    private static function tooMany(Request $request): bool
    {
        return RateLimiter::tooManyAttempts(self::key($request), self::MAX_BAD);
    }

    private static function key(Request $request): string
    {
        return 'staff-password-link:'.$request->ip();
    }
}
