<?php

use App\Mail\AccountActionMail;
use App\Models\ActivityLog;
use App\Models\MailOutbox;
use App\Models\User;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;

/*
 * Audit H2 (proof P12): twelve wrong passwords from twelve addresses were never refused. The account-wide ceiling
 * (LoginGuard, settings rl_login_account, login_window_minutes, login_lock_minutes) now locks the e-mail.
 */

beforeEach(fn () => Mail::fake());

function wrongFrom(int $n, string $email = 'mona@example.test', string $password = 'wrong-password'): TestResponse
{
    return test()->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password], [...apiHeaders(), 'X-Client-Ip' => "198.51.100.{$n}"]);
}

it('locks an account after the ceiling of failures from different addresses, even for the right password', function () {
    User::factory()->create(['email' => 'mona@example.test', 'password' => 'long-enough-1']);

    foreach (range(1, 20) as $n) {
        wrongFrom($n)->assertStatus(422);
    }
    // The 21st try comes from a fresh address with the right password: still refused while locked.
    wrongFrom(99, password: 'long-enough-1')->assertStatus(429)->assertJsonPath('errors.email.0', fn (string $m) => str_contains($m, '15'));
});

it('the audit proof P12: twelve addresses no longer guess without end', function () {
    app(Settings::class)->set('rl_login_account', 10);
    User::factory()->create(['email' => 'mona@example.test', 'password' => 'long-enough-1']);

    $codes = collect(range(1, 12))->map(fn (int $n) => wrongFrom($n)->status())->all();

    expect(array_slice($codes, 0, 10))->each->toBe(422)
        ->and(array_slice($codes, 10))->each->toBe(429);
});

it('e-mails the owner once per lock and records it', function () {
    app(Settings::class)->set('rl_login_account', 3);
    $user = User::factory()->create(['email' => 'mona@example.test', 'locale' => 'ar']);

    foreach (range(1, 5) as $n) {
        wrongFrom($n);
    }

    $mail = MailOutbox::query()->where('template', 'account.login_locked')->sole();
    expect($mail->to_email)->toBe('mona@example.test')
        ->and($mail->payload['url'])->toEndWith('/forgot-password')
        ->and(ActivityLog::query()->where('action', 'user.login_locked')->where('subject_id', $user->id)->count())->toBe(1);
});

it('locks an unknown e-mail the same way, without sending anything', function () {
    app(Settings::class)->set('rl_login_account', 3);

    foreach (range(1, 3) as $n) {
        wrongFrom($n, 'nobody@example.test')->assertStatus(422);
    }
    wrongFrom(4, 'nobody@example.test')->assertStatus(429);
    expect(MailOutbox::query()->count())->toBe(0);
});

it('unlocks after login_lock_minutes, and forgets failures older than the window', function () {
    app(Settings::class)->set('rl_login_account', 3);
    app(Settings::class)->set('login_lock_minutes', 10);
    User::factory()->create(['email' => 'mona@example.test', 'password' => 'long-enough-1']);

    foreach (range(1, 3) as $n) {
        wrongFrom($n);
    }
    $this->travel(9)->minutes();
    wrongFrom(50, password: 'long-enough-1')->assertStatus(429);
    $this->travel(2)->minutes();
    wrongFrom(51, password: 'long-enough-1')->assertOk();

    // Two failures, then the window (60 minutes) passes: the count starts again.
    wrongFrom(60);
    wrongFrom(61);
    $this->travel(61)->minutes();
    wrongFrom(62);
    wrongFrom(63)->assertStatus(422);
});

it('a successful sign-in clears the count', function () {
    app(Settings::class)->set('rl_login_account', 3);
    User::factory()->create(['email' => 'mona@example.test', 'password' => 'long-enough-1']);

    wrongFrom(1);
    wrongFrom(2);
    wrongFrom(3, password: 'long-enough-1')->assertOk();
    wrongFrom(4)->assertStatus(422);
    wrongFrom(5)->assertStatus(422);
    wrongFrom(6, password: 'long-enough-1')->assertOk();
});

it('a password reset lifts the lock at once', function () {
    app(Settings::class)->set('rl_login_account', 3);
    $user = User::factory()->create(['email' => 'mona@example.test', 'password' => 'long-enough-1']);
    foreach (range(1, 3) as $n) {
        wrongFrom($n);
    }
    wrongFrom(9, password: 'long-enough-1')->assertStatus(429);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => Password::broker()->createToken($user), 'email' => 'mona@example.test',
        'password' => 'new-password-1', 'password_confirmation' => 'new-password-1',
    ], apiHeaders())->assertOk();

    wrongFrom(10, password: 'new-password-1')->assertOk();
});

it('renders the lock e-mail in both languages', function () {
    foreach (['ar' => 'قفلنا الدخول', 'en' => 'We paused sign-in'] as $locale => $words) {
        app()->setLocale($locale);
        $html = AccountActionMail::fromPayload(['kind' => 'login_locked', 'name' => 'Mona', 'url' => 'https://loktaeg.com/forgot-password'], $locale)->render();
        expect($html)->toContain($words)->toContain('https://loktaeg.com/forgot-password');
    }
});
