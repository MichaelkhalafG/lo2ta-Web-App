<?php

use App\Mail\AccountActionMail;
use App\Models\ActivityLog;
use App\Models\AdminUser;
use App\Models\MailOutbox;
use App\Models\User;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;

/*
 * Second security pass N1 (Michael 2026-11-24 decision A): staff sign-in had only five tries a minute per e-mail and
 * address, so a guesser spread over many addresses was never stopped. It now has the public sign-in's account-wide
 * ceiling (LoginGuard, the same three settings) with its own counters.
 */

beforeEach(fn () => Mail::fake());

function staffTry(int $n, string $email = 'staff@example.test', string $password = 'wrong-password'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => "198.51.100.{$n}"])
        ->post('/dashboard/login', ['email' => $email, 'password' => $password]);
}

function staffError(): string
{
    // The session keeps the error bag as an array (default bag, messages by field).
    $errors = app('session.store')->get('errors');
    if ($errors instanceof ViewErrorBag) {
        return (string) $errors->first('email');
    }

    return is_array($errors) ? (string) ($errors['default']['messages']['email'][0] ?? '') : '';
}

it('the N1 proof: twelve addresses no longer guess a staff password without end', function () {
    app(Settings::class)->set('rl_login_account', 10);
    AdminUser::factory()->create(['email' => 'staff@example.test']);

    $locked = collect(range(1, 12))->map(function (int $n): bool {
        staffTry($n)->assertSessionHasErrors('email');

        return str_contains(staffError(), '15');
    })->all();

    expect(array_slice($locked, 0, 10))->each->toBeFalse()
        ->and(array_slice($locked, 10))->each->toBeTrue();
    $this->assertGuest('admin');
});

it('locks a staff account after the ceiling, even for the right password, in the reader\'s language', function () {
    app(Settings::class)->set('rl_login_account', 3);
    AdminUser::factory()->create(['email' => 'staff@example.test']);

    foreach (range(1, 3) as $n) {
        staffTry($n);
    }
    staffTry(99, password: 'test-password-123')->assertSessionHasErrors('email');
    expect(staffError())->toContain('أُوقف الدخول')->toContain('15');
    $this->assertGuest('admin');
});

it('e-mails an active staff member once per lock, with the sign-in page and no password reset, and records it', function () {
    app(Settings::class)->set('rl_login_account', 3);
    $admin = AdminUser::factory()->create(['email' => 'staff@example.test', 'locale' => 'en']);

    foreach (range(1, 5) as $n) {
        staffTry($n);
    }

    $mail = MailOutbox::query()->where('template', 'admin.login_locked')->sole();
    expect($mail->to_email)->toBe('staff@example.test')
        ->and($mail->locale)->toBe('en')
        ->and($mail->payload['kind'])->toBe('staff_login_locked')
        ->and($mail->payload['url'])->toEndWith('/dashboard/login')
        ->and(ActivityLog::query()->where('action', 'admin.login_locked')->where('subject_id', $admin->id)->count())->toBe(1)
        ->and(MailOutbox::query()->where('template', 'account.login_locked')->exists())->toBeFalse();
});

it('locks an unknown e-mail and a switched-off account the same way, without sending anything', function () {
    app(Settings::class)->set('rl_login_account', 3);
    AdminUser::factory()->inactive()->create(['email' => 'off@example.test']);

    foreach (['nobody@example.test', 'off@example.test'] as $email) {
        foreach (range(1, 3) as $n) {
            staffTry($n, $email);
            expect(staffError())->not->toContain('أُوقف');
        }
        staffTry(4, $email);
        expect(staffError())->toContain('أُوقف الدخول');
    }
    expect(MailOutbox::query()->count())->toBe(0);
});

it('keeps staff and public counters apart for the same e-mail', function () {
    app(Settings::class)->set('rl_login_account', 3);
    AdminUser::factory()->create(['email' => 'both@example.test']);
    User::factory()->create(['email' => 'both@example.test', 'password' => 'long-enough-1']);

    foreach (range(1, 3) as $n) {
        staffTry($n, 'both@example.test');
    }
    // The staff lock does not touch the public account...
    $this->postJson('/api/v1/auth/login', ['email' => 'both@example.test', 'password' => 'long-enough-1'], [...apiHeaders(), 'X-Client-Ip' => '203.0.113.1'])
        ->assertOk();

    // ...and public failures do not count towards a staff lock.
    $this->travel(16)->minutes();
    foreach (range(1, 3) as $n) {
        $this->postJson('/api/v1/auth/login', ['email' => 'both@example.test', 'password' => 'wrong'], [...apiHeaders(), 'X-Client-Ip' => "203.0.113.{$n}"]);
    }
    staffTry(50, 'both@example.test', 'test-password-123')->assertRedirect('/dashboard');
    $this->assertAuthenticated('admin');
});

it('unlocks after login_lock_minutes, and a successful sign-in clears the count', function () {
    app(Settings::class)->set('rl_login_account', 3);
    app(Settings::class)->set('login_lock_minutes', 10);
    AdminUser::factory()->create(['email' => 'staff@example.test']);

    foreach (range(1, 3) as $n) {
        staffTry($n);
    }
    $this->travel(9)->minutes();
    staffTry(50, password: 'test-password-123');
    $this->assertGuest('admin');
    $this->travel(2)->minutes();
    staffTry(51, password: 'test-password-123')->assertRedirect('/dashboard');
    $this->assertAuthenticated('admin');

    // Two failures, a success, two more failures: no lock (the success cleared the count).
    auth('admin')->logout();
    staffTry(60);
    staffTry(61);
    staffTry(62, password: 'test-password-123')->assertRedirect('/dashboard');
    auth('admin')->logout();
    staffTry(63);
    staffTry(64);
    expect(staffError())->not->toContain('أُوقف');
});

it('renders the staff lock e-mail in both languages', function () {
    foreach (['ar' => 'لوحة التحكم مؤقتًا', 'en' => 'dashboard account is paused'] as $locale => $words) {
        app()->setLocale($locale);
        $html = AccountActionMail::fromPayload(['kind' => 'staff_login_locked', 'name' => 'Sara', 'url' => 'https://admin.loktaeg.com/dashboard/login'], $locale)->render();
        expect($html)->toContain($words)->toContain('https://admin.loktaeg.com/dashboard/login')->not->toContain('forgot-password');
    }
});
