<?php

use App\Models\ActivityLog;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

it('sends a guest to the login page, in Arabic and right to left', function () {
    $this->get('/dashboard')->assertRedirect('/dashboard/login');

    $this->get('/dashboard/login')
        ->assertOk()
        ->assertSee('lang="ar"', false)
        ->assertSee('dir="rtl"', false)
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login')
            ->where('locale', 'ar')
            ->where('siteName', 'لقطة')
            ->where('t.login.title', 'تسجيل الدخول')
            ->where('admin', null));
});

it('signs an admin in and logs it, without logging the password', function () {
    $admin = AdminUser::factory()->create(['email' => 'staff@example.test']);

    $this->post('/dashboard/login', ['email' => 'STAFF@example.test', 'password' => 'test-password-123'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($admin, 'admin');
    expect($admin->refresh()->last_login_at)->not->toBeNull();

    $line = ActivityLog::query()->where('action', 'admin.login')->sole();
    expect($line->actor_type)->toBe('admin')
        ->and($line->actor_id)->toBe($admin->id)
        ->and(ActivityLog::query()->where('action', 'admin_users.updated')->exists())->toBeFalse()
        ->and(json_encode(ActivityLog::query()->get()->toArray()))->not->toContain('test-password-123');
});

it('refuses a wrong password and an inactive account, and logs the failure', function () {
    AdminUser::factory()->create(['email' => 'staff@example.test']);
    AdminUser::factory()->inactive()->create(['email' => 'off@example.test']);

    $this->post('/dashboard/login', ['email' => 'staff@example.test', 'password' => 'wrong-password'])
        ->assertSessionHasErrors('email');
    $this->post('/dashboard/login', ['email' => 'off@example.test', 'password' => 'test-password-123'])
        ->assertSessionHasErrors('email');

    $this->assertGuest('admin');
    expect(ActivityLog::query()->where('action', 'admin.login_failed')->count())->toBe(2);
});

it('stops a sixth try within a minute', function () {
    AdminUser::factory()->create(['email' => 'staff@example.test']);

    foreach (range(1, 5) as $i) {
        $this->post('/dashboard/login', ['email' => 'staff@example.test', 'password' => 'wrong']);
    }

    $this->post('/dashboard/login', ['email' => 'staff@example.test', 'password' => 'test-password-123'])
        ->assertSessionHasErrors('email');
    $this->assertGuest('admin');
});

it('signs out a session whose account was switched off', function () {
    $admin = AdminUser::factory()->create();
    $this->actingAs($admin, 'admin')->get('/dashboard')->assertOk();

    $admin->update(['is_active' => false]);

    $this->actingAs($admin->refresh(), 'admin')->get('/dashboard')->assertRedirect('/dashboard/login');
});

it('logs sign-out', function () {
    $admin = AdminUser::factory()->create();

    $this->actingAs($admin, 'admin')->post('/dashboard/logout')->assertRedirect('/dashboard/login');

    $this->assertGuest('admin');
    expect(ActivityLog::query()->where('action', 'admin.logout')->where('actor_id', $admin->id)->exists())->toBeTrue();
});

it('saves the language on the admin account, logged, and switches direction', function () {
    $admin = AdminUser::factory()->create(['locale' => 'ar']);

    $this->actingAs($admin, 'admin')->post('/dashboard/locale', ['locale' => 'en'])->assertRedirect();

    expect($admin->refresh()->locale)->toBe('en');
    $line = ActivityLog::query()->where('action', 'admin_users.updated')->sole();
    expect($line->before)->toBe(['locale' => 'ar'])->and($line->after)->toBe(['locale' => 'en']);

    $this->actingAs($admin, 'admin')->get('/dashboard')
        ->assertSee('dir="ltr"', false)
        ->assertInertia(fn (Assert $page) => $page->component('Overview')
            ->where('locale', 'en')
            ->where('siteName', 'Lokta')
            ->where('admin.role', 'reviewer'));
});

it('keeps a guest language choice in the session only', function () {
    $this->post('/dashboard/locale', ['locale' => 'en'])->assertRedirect();

    $this->get('/dashboard/login')->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
    expect(ActivityLog::query()->count())->toBe(0);

    $this->post('/dashboard/locale', ['locale' => 'fr'])->assertSessionHasErrors('locale');
});

it('gives settings, texts, staff and commissions to the admin role only', function () {
    $admin = AdminUser::factory()->admin()->create();
    $reviewer = AdminUser::factory()->create();

    foreach (['manage-settings', 'manage-site-texts', 'manage-admins', 'manage-commissions'] as $ability) {
        expect(Gate::forUser($admin)->allows($ability))->toBeTrue()
            ->and(Gate::forUser($reviewer)->allows($ability))->toBeFalse();
    }
});

it('names the provisional settings on the overview instead of showing their keys (audit D12)', function () {
    $admin = AdminUser::factory()->create();

    $this->actingAs($admin, 'admin')->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->component('Overview')
            ->where('provisionalSettings.0', ['key' => 'deep_loss_ratio_bp', 'label' => 'حد الخسارة الكبيرة'])
            ->where('provisionalSettings', fn ($rows) => collect($rows)->every(fn (array $r): bool => $r['label'] !== $r['key'])));
});
