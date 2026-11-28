<?php

namespace App\Support\Staff;

use App\Models\AdminPasswordLink;
use App\Models\AdminUser;
use App\Support\Accounts\LoginGuard;
use App\Support\Activity\ActivityLogger;
use App\Support\Mail\Outbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Staff passwords (PLAN 2.7, G13, G14). No one types another person's password: an admin sends a one-use link that
 * works for 60 minutes (an invite for new staff, a reset for a forgotten password), and each staff member changes
 * their own password with the current one. A new password signs that person out everywhere: each session carries a
 * mark derived from the password hash (sessionMark), and EnsureAdminActive signs out a session whose mark no longer
 * matches. Every step is logged; the token itself is never stored or logged, only its sha256.
 */
final class StaffPasswords
{
    public const LINK_MINUTES = 60;

    public const MIN_LENGTH = 12;

    public const SESSION_KEY = 'admin_auth_mark';

    public function __construct(
        private readonly ActivityLogger $log,
        private readonly Outbox $outbox,
        private readonly LoginGuard $guard,
    ) {}

    /** Send a set-password link; earlier unused links for the same person stop working. */
    public function sendLink(AdminUser $target, string $purpose, ?AdminUser $by): void
    {
        $token = bin2hex(random_bytes(32));

        DB::transaction(function () use ($target, $purpose, $by, $token): void {
            AdminPasswordLink::query()->where('admin_user_id', $target->id)->whereNull('used_at')->delete();
            AdminPasswordLink::query()->create([
                'admin_user_id' => $target->id,
                'purpose' => $purpose,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addMinutes(self::LINK_MINUTES),
                'created_by' => $by?->id,
            ]);
        });

        $this->log->record('admin.password_link_sent', $target, null, ['purpose' => $purpose]);
        $this->outbox->send($target->email, $target->name, $target->locale === 'en' ? 'en' : 'ar', 'admin.staff_'.$purpose, [
            'kind' => 'staff_'.$purpose,
            'name' => $target->name,
            'url' => route('dashboard.password.edit', ['token' => $token]),
        ]);
    }

    /** The usable link for a token, or null (wrong, used, expired, or the account is switched off). */
    public function find(string $token): ?AdminPasswordLink
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }
        $link = AdminPasswordLink::query()->with('admin')->where('token_hash', hash('sha256', $token))->first();
        if (! $link instanceof AdminPasswordLink || ! $link->isUsable()) {
            return null;
        }
        $admin = $link->admin;

        return $admin instanceof AdminUser && $admin->is_active ? $link : null;
    }

    /** Set the password from a link: one use, then every session of that person ends and a sign-in lock lifts. */
    public function useLink(AdminPasswordLink $link, string $password): AdminUser
    {
        $admin = $link->admin;
        if (! $admin instanceof AdminUser) {
            throw new \LogicException('A password link without its staff member.');
        }

        DB::transaction(function () use ($link, $admin, $password): void {
            $admin->forceFill(['password' => $password])->saveQuietly();
            $link->forceFill(['used_at' => now()])->save();
            AdminPasswordLink::query()->where('admin_user_id', $admin->id)->whereNull('used_at')->delete();
        });
        $this->guard->clear($admin->email, lift: true, scope: LoginGuard::STAFF);
        $this->log->record('admin.password_set', $admin, null, ['purpose' => $link->purpose]);

        return $admin;
    }

    /**
     * A staff member changes their own password with the current one. Their other sessions end; this one stays
     * signed in. Returns false when the current password is wrong (nothing changes).
     */
    public function changeOwn(AdminUser $admin, string $current, string $new, Request $request): bool
    {
        if (! Hash::check($current, $admin->password)) {
            $this->log->record('admin.password_change_refused', $admin);

            return false;
        }
        $admin->forceFill(['password' => $new])->saveQuietly();
        AdminPasswordLink::query()->where('admin_user_id', $admin->id)->whereNull('used_at')->delete();
        $request->session()->put(self::SESSION_KEY, self::sessionMark($admin));
        $request->session()->regenerate();
        $this->log->record('admin.password_changed', $admin);

        return true;
    }

    /**
     * A mark of the current password hash, kept in each session and prefixed with the staff id; a new password
     * changes it. A session only ever holds the mark of the person who signed in to it (sign-in writes it).
     */
    public static function sessionMark(AdminUser $admin): string
    {
        $key = config('app.key');

        return $admin->id.'|'.hash_hmac('sha256', 'staff-session|'.$admin->id.'|'.$admin->password, is_string($key) ? $key : '');
    }
}
