<?php

namespace App\Support\Accounts;

use App\Models\AdminUser;
use App\Models\User;
use App\Support\Activity\ActivityLogger;
use App\Support\Mail\Outbox;
use App\Support\Settings\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * An account-wide ceiling on failed sign-ins (audit H2, Michael 2026-11-23 decision 1A). The per-address limit stops
 * one visitor; this one counts every failure for an e-mail from any address: after rl_login_account failures within
 * login_window_minutes the e-mail is locked for login_lock_minutes, even for the right password, and the owner gets
 * an e-mail. Unknown e-mails are counted and locked the same way (only the e-mail is skipped), so the lock tells a
 * visitor nothing about which accounts exist. A password reset lifts the lock at once.
 *
 * Staff sign-in (second security pass N1, Michael 2026-11-24 decision A) uses the same three settings with its own
 * counters, so a public account and a staff account with one e-mail never lock each other. Staff have no password
 * reset: their lock ends when login_lock_minutes have passed.
 */
final class LoginGuard
{
    public const PUBLIC = 'login';

    public const STAFF = 'staff-login';

    public function __construct(
        private readonly Settings $settings,
        private readonly Outbox $outbox,
        private readonly ActivityLogger $log,
    ) {}

    /** 429 while the e-mail is locked. */
    public function ensureOpen(string $email, string $scope = self::PUBLIC): void
    {
        $until = Cache::get(self::lockKey($email, $scope));
        $now = Carbon::now()->getTimestamp();
        if (is_int($until) && $until > $now) {
            $key = $scope === self::STAFF ? 'dashboard.login.locked' : 'accounts.locked';
            $text = __($key, ['minutes' => (int) ceil(($until - $now) / 60)]);

            throw ValidationException::withMessages(['email' => is_string($text) ? $text : $key])->status(429);
        }
    }

    /** Count one failure; lock the e-mail and tell its owner when the ceiling is reached. */
    public function failed(string $email, ?User $user): void
    {
        $minutes = $this->count($email, self::PUBLIC);
        if ($minutes === null || ! $user instanceof User) {
            return;
        }

        $this->log->record('user.login_locked', $user, null, ['minutes' => $minutes]);
        $this->outbox->send($user->email, $user->name, $user->preferredLocale(), 'account.login_locked', [
            'kind' => 'login_locked',
            'name' => $user->name,
            'url' => Links::frontend('/forgot-password', $user->preferredLocale()),
        ]);
    }

    /** A failed staff sign-in: the same ceiling; an active staff member is told of the lock by e-mail. */
    public function staffFailed(string $email, ?AdminUser $admin): void
    {
        $minutes = $this->count($email, self::STAFF);
        if ($minutes === null || ! $admin instanceof AdminUser || ! $admin->is_active) {
            return;
        }

        $this->log->record('admin.login_locked', $admin, null, ['minutes' => $minutes]);
        $this->outbox->send($admin->email, $admin->name, $admin->locale === 'en' ? 'en' : 'ar', 'admin.login_locked', [
            'kind' => 'staff_login_locked',
            'name' => $admin->name,
            'url' => route('dashboard.login'),
        ]);
    }

    /** A successful sign-in clears the count; a password reset also lifts a lock. */
    public function clear(string $email, bool $lift = false, string $scope = self::PUBLIC): void
    {
        RateLimiter::clear(self::counterKey($email, $scope));
        if ($lift) {
            Cache::forget(self::lockKey($email, $scope));
        }
    }

    /** Count one failure: the lock length in minutes when this failure reached the ceiling, otherwise null. */
    private function count(string $email, string $scope): ?int
    {
        $counter = self::counterKey($email, $scope);
        RateLimiter::hit($counter, $this->settings->int('login_window_minutes') * 60);
        if (RateLimiter::attempts($counter) < $this->settings->int('rl_login_account')) {
            return null;
        }

        $minutes = $this->settings->int('login_lock_minutes');
        $until = Carbon::now()->addMinutes($minutes);
        Cache::put(self::lockKey($email, $scope), $until->getTimestamp(), $until);
        RateLimiter::clear($counter);

        return $minutes;
    }

    private static function counterKey(string $email, string $scope): string
    {
        return $scope.'-account:'.hash('sha256', $email);
    }

    private static function lockKey(string $email, string $scope): string
    {
        return $scope.'-lock:'.hash('sha256', $email);
    }
}
