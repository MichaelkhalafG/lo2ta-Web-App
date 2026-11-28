<?php

use App\Enums\AdminRole as AdminRoleCode;
use App\Models\ActivityLog;
use App\Models\AdminPasswordLink;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\MailOutbox;
use App\Support\Staff\StaffPasswords;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Part 7.1 (PLAN 2.7, G13, G14): staff are invited by link, a forgotten password gets a one-use link, each person
| changes their own password with the current one, a new password signs that person out everywhere, and no one can
| switch off or demote themselves or the last active admin. Every step is logged; the token never is.
*/

beforeEach(function () {
    foreach (AdminRoleCode::cases() as $code) {
        AdminRole::query()->firstOrCreate(['code' => $code->value], ['name_ar' => $code->value, 'name_en' => $code->value]);
    }
    $this->admin = AdminUser::factory()->admin()->create(['email' => 'boss@example.test']);
});

/** The link token from the newest staff e-mail in the outbox. */
function staffLinkToken(string $email): string
{
    $payload = MailOutbox::query()->where('to_email', $email)->latest('id')->firstOrFail()->payload;
    preg_match('~/dashboard/password/([a-f0-9]{64})~', (string) ($payload['url'] ?? ''), $m);

    return $m[1] ?? '';
}

it('lets only an admin open staff management', function () {
    $reviewer = AdminUser::factory()->create();

    $this->actingAs($reviewer, 'admin')->get('/dashboard/staff')->assertForbidden();
    $this->actingAs($reviewer, 'admin')->post('/dashboard/staff', ['name' => 'X Y', 'email' => 'x@example.test', 'role' => 'admin', 'locale' => 'ar'])->assertForbidden();
    $this->actingAs($reviewer, 'admin')->put("/dashboard/staff/{$reviewer->id}", ['name' => 'X', 'role' => 'admin', 'locale' => 'ar', 'is_active' => true])->assertForbidden();
    expect($reviewer->refresh()->isAdmin())->toBeFalse();

    $this->actingAs($this->admin, 'admin')->get('/dashboard/staff')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Staff/Index')->has('staff', 2)
            ->where('staff', fn ($rows) => collect($rows)->every(fn (array $r): bool => ! array_key_exists('password', $r))));
});

it('invites new staff with a one-use link that sets their own password, then signs in', function () {
    $this->actingAs($this->admin, 'admin')->post('/dashboard/staff', ['name' => 'Mona Ali', 'email' => 'Mona@Example.test', 'role' => 'reviewer', 'locale' => 'en'])
        ->assertSessionHasNoErrors();
    $mona = AdminUser::query()->where('email', 'mona@example.test')->sole();
    $mail = MailOutbox::query()->where('to_email', 'mona@example.test')->sole();
    expect($mail->template)->toBe('admin.staff_invite')->and($mail->locale)->toBe('en');
    $token = staffLinkToken('mona@example.test');
    expect($token)->toHaveLength(64)
        ->and(AdminPasswordLink::query()->sole()->token_hash)->toBe(hash('sha256', $token))
        ->and(json_encode(ActivityLog::query()->get()->toArray()))->not->toContain($token)->not->toContain(hash('sha256', $token));

    auth('admin')->logout();
    $this->get("/dashboard/password/{$token}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/SetPassword')->where('purpose', 'invite')->where('name', 'Mona Ali'));
    $this->post("/dashboard/password/{$token}", ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
    $this->post("/dashboard/password/{$token}", ['password' => 'a-long-new-password', 'password_confirmation' => 'a-long-new-password'])->assertRedirect('/dashboard/login');

    expect(Hash::check('a-long-new-password', $mona->refresh()->password))->toBeTrue()
        ->and(ActivityLog::query()->where('action', 'admin.password_set')->where('subject_id', $mona->id)->exists())->toBeTrue();
    // One use only.
    $this->get("/dashboard/password/{$token}")->assertInertia(fn (Assert $page) => $page->where('token', null));
    $this->post("/dashboard/password/{$token}", ['password' => 'another-long-one', 'password_confirmation' => 'another-long-one'])->assertSessionHasErrors('password');
    $this->post('/dashboard/login', ['email' => 'mona@example.test', 'password' => 'a-long-new-password'])->assertRedirect('/dashboard');
});

it('refuses an expired, unknown or switched-off link with one neutral message', function () {
    $staff = AdminUser::factory()->create(['email' => 'old@example.test']);
    app(StaffPasswords::class)->sendLink($staff, AdminPasswordLink::RESET, $this->admin);
    $token = staffLinkToken('old@example.test');

    $this->travel(61)->minutes();
    $this->get("/dashboard/password/{$token}")->assertInertia(fn (Assert $page) => $page->where('token', null)->where('name', null));
    $this->travelBack();

    $this->get('/dashboard/password/'.str_repeat('a', 64))->assertInertia(fn (Assert $page) => $page->where('token', null));
    $this->get('/dashboard/password/not-a-token')->assertNotFound();

    app(StaffPasswords::class)->sendLink($staff, AdminPasswordLink::RESET, $this->admin);
    $fresh = staffLinkToken('old@example.test');
    $staff->update(['is_active' => false]);
    $this->post("/dashboard/password/{$fresh}", ['password' => 'a-long-new-password', 'password_confirmation' => 'a-long-new-password'])->assertSessionHasErrors('password');
});

it('ends earlier links when a new one is sent, and pauses an address that tries many bad links', function () {
    $staff = AdminUser::factory()->create(['email' => 'two@example.test']);
    $this->actingAs($this->admin, 'admin')->post("/dashboard/staff/{$staff->id}/password-link")->assertSessionHasNoErrors();
    $first = staffLinkToken('two@example.test');
    $this->actingAs($this->admin, 'admin')->post("/dashboard/staff/{$staff->id}/password-link")->assertSessionHasNoErrors();
    $second = staffLinkToken('two@example.test');

    expect($first)->not->toBe($second)
        ->and(AdminPasswordLink::query()->whereNull('used_at')->count())->toBe(1);
    auth('admin')->logout();
    $this->get("/dashboard/password/{$first}")->assertInertia(fn (Assert $page) => $page->where('token', null));

    foreach (range(1, 10) as $i) {
        $this->get('/dashboard/password/'.str_repeat(dechex($i % 16), 64));
    }
    // Even the good link is refused while the address is paused.
    $this->get("/dashboard/password/{$second}")->assertInertia(fn (Assert $page) => $page->where('token', null));
});

it('signs a person out everywhere when their password changes from a link', function () {
    $staff = AdminUser::factory()->create(['email' => 'away@example.test']);
    $this->post('/dashboard/login', ['email' => 'away@example.test', 'password' => 'test-password-123'])->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertOk();

    app(StaffPasswords::class)->useLink(tap(AdminPasswordLink::query()->create([
        'admin_user_id' => $staff->id, 'purpose' => 'reset', 'token_hash' => hash('sha256', 'x'), 'expires_at' => now()->addHour(),
    ]))->load('admin'), 'a-brand-new-password');

    // The same browser session no longer matches the new password. (A real request loads the account afresh;
    // the test app keeps the signed-in model between requests, so forget it first.)
    app('auth')->forgetGuards();
    $this->get('/dashboard')->assertRedirect('/dashboard/login');
});

it('changes your own password with the current one, keeps you signed in, and pauses after wrong tries', function () {
    $reviewer = AdminUser::factory()->create(['email' => 'self@example.test']);
    $this->post('/dashboard/login', ['email' => 'self@example.test', 'password' => 'test-password-123']);

    $this->put('/dashboard/account/password', ['current_password' => 'wrong-one-entirely', 'password' => 'a-long-new-password', 'password_confirmation' => 'a-long-new-password'])
        ->assertSessionHasErrors('current_password');
    $this->put('/dashboard/account/password', ['current_password' => 'test-password-123', 'password' => 'a-long-new-password', 'password_confirmation' => 'mismatch-mismatch'])
        ->assertSessionHasErrors('password');
    $this->put('/dashboard/account/password', ['current_password' => 'test-password-123', 'password' => 'a-long-new-password', 'password_confirmation' => 'a-long-new-password'])
        ->assertSessionHasNoErrors();

    expect(Hash::check('a-long-new-password', $reviewer->refresh()->password))->toBeTrue()
        ->and(ActivityLog::query()->where('action', 'admin.password_changed')->where('subject_id', $reviewer->id)->exists())->toBeTrue();
    $this->get('/dashboard')->assertOk();

    foreach (range(1, 5) as $i) {
        $this->put('/dashboard/account/password', ['current_password' => 'wrong-'.$i.'-wrong-wrong', 'password' => 'yet-another-password', 'password_confirmation' => 'yet-another-password']);
    }
    $this->put('/dashboard/account/password', ['current_password' => 'a-long-new-password', 'password' => 'yet-another-password', 'password_confirmation' => 'yet-another-password'])
        ->assertSessionHasErrors('current_password');
    expect(Hash::check('a-long-new-password', $reviewer->refresh()->password))->toBeTrue();
});

it('never lets you switch off or demote yourself, or the last active admin', function () {
    $this->actingAs($this->admin, 'admin')->put("/dashboard/staff/{$this->admin->id}", ['name' => 'Boss', 'role' => 'reviewer', 'locale' => 'ar', 'is_active' => true])
        ->assertSessionHasErrors('role');
    $this->actingAs($this->admin, 'admin')->put("/dashboard/staff/{$this->admin->id}", ['name' => 'Boss', 'role' => 'admin', 'locale' => 'ar', 'is_active' => false])
        ->assertSessionHasErrors('is_active');
    $this->actingAs($this->admin, 'admin')->put("/dashboard/staff/{$this->admin->id}", ['name' => 'Big Boss', 'role' => 'admin', 'locale' => 'en', 'is_active' => true])
        ->assertSessionHasNoErrors();
    expect($this->admin->refresh()->name)->toBe('Big Boss');

    // A second admin may demote the first, but not once only one active admin is left.
    $second = AdminUser::factory()->admin()->create();
    $this->actingAs($second, 'admin')->put("/dashboard/staff/{$this->admin->id}", ['name' => 'Big Boss', 'role' => 'reviewer', 'locale' => 'en', 'is_active' => true])
        ->assertSessionHasNoErrors();
    $other = AdminUser::factory()->create();
    $this->actingAs($second, 'admin')->put("/dashboard/staff/{$other->id}", ['name' => 'Other', 'role' => 'reviewer', 'locale' => 'ar', 'is_active' => false])
        ->assertSessionHasNoErrors();
    expect($other->refresh()->is_active)->toBeFalse()
        ->and(ActivityLog::query()->where('action', 'admin_users.updated')->where('subject_id', $other->id)->exists())->toBeTrue();

    // A switched-off account gets no password link.
    $this->actingAs($second, 'admin')->post("/dashboard/staff/{$other->id}/password-link")->assertSessionHasErrors('staff');
});

it('shows every staff member their own account page, with times in Cairo time', function () {
    $reviewer = AdminUser::factory()->create();
    $reviewer->forceFill(['last_login_at' => '2026-11-27 23:59:00'])->saveQuietly();
    $this->actingAs($reviewer, 'admin')->get('/dashboard/account')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Account/Index')->where('account.email', $reviewer->email)->where('minLength', 12)
            ->where('account.last_login_at', '2026-11-28 01:59'));
    $this->actingAs($this->admin, 'admin')->get('/dashboard/staff')
        ->assertInertia(fn (Assert $page) => $page->where('staff', fn ($rows) => collect($rows)->firstWhere('id', $reviewer->id)['last_login_at'] === '2026-11-28 01:59'));
});
