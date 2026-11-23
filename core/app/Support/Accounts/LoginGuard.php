<?php

namespace App\Support\Accounts;

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
 */
final class LoginGuard
{
    public function __construct(
        private readonly Settings $settings,
        private readonly Outbox $outbox,
        private readonly ActivityLogger $log,
    ) {}

    /** 429 while the e-mail is locked. */
    public function ensureOpen(string $email): void
    {
        $until = Cache::get(self::lockKey($email));
        $now = Carbon::now()->getTimestamp();
        if (is_int($until) && $until > $now) {
            $text = __('accounts.locked', ['minutes' => (int) ceil(($until - $now) / 60)]);

            throw ValidationException::withMessages(['email' => is_string($text) ? $text : 'accounts.locked'])->status(429);
        }
    }

    /** Count one failure; lock the e-mail and tell its owner when the ceiling is reached. */
    public function failed(string $email, ?User $user): void
    {
        $counter = self::counterKey($email);
        RateLimiter::hit($counter, $this->settings->int('login_window_minutes') * 60);
        if (RateLimiter::attempts($counter) < $this->settings->int('rl_login_account')) {
            return;
        }

        $minutes = $this->settings->int('login_lock_minutes');
        $until = Carbon::now()->addMinutes($minutes);
        Cache::put(self::lockKey($email), $until->getTimestamp(), $until);
        RateLimiter::clear($counter);
        if (! $user instanceof User) {
            return;
        }

        $this->log->record('user.login_locked', $user, null, ['minutes' => $minutes]);
        $this->outbox->send($user->email, $user->name, $user->preferredLocale(), 'account.login_locked', [
            'kind' => 'login_locked',
            'name' => $user->name,
            'url' => Links::frontend('/forgot-password', $user->preferredLocale()),
        ]);
    }

    /** A successful sign-in clears the count; a password reset also lifts a lock. */
    public function clear(string $email, bool $lift = false): void
    {
        RateLimiter::clear(self::counterKey($email));
        if ($lift) {
            Cache::forget(self::lockKey($email));
        }
    }

    private static function counterKey(string $email): string
    {
        return 'login-account:'.hash('sha256', $email);
    }

    private static function lockKey(string $email): string
    {
        return 'login-lock:'.hash('sha256', $email);
    }
}
