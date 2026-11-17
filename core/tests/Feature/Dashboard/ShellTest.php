<?php

use App\Models\ActivityLog;
use App\Models\AdminUser;
use App\Models\Developer;
use Inertia\Testing\AssertableInertia as Assert;

it('shares a sidebar built for the role: the reviewer never sees admin screens', function () {
    $admin = AdminUser::factory()->admin()->create();
    $reviewer = AdminUser::factory()->create();

    $keys = function (AdminUser $who): array {
        $nav = $this->actingAs($who, 'admin')->get('/dashboard')->viewData('page')['props']['nav'];

        return collect($nav)->flatMap(fn (array $g) => collect($g['items'])->pluck('key'))->all();
    };

    $adminKeys = $keys($admin);
    $reviewerKeys = $keys($reviewer);

    foreach (['commissions', 'site_texts', 'settings', 'staff', 'activity'] as $adminOnly) {
        expect($adminKeys)->toContain($adminOnly)->and($reviewerKeys)->not->toContain($adminOnly);
    }
    expect($reviewerKeys)->toContain('overview')->toContain('review_queue');
});

it('marks the current page active and later pages without a link', function () {
    $admin = AdminUser::factory()->admin()->create();

    $this->actingAs($admin, 'admin')->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('nav.0.items.0.key', 'overview')
        ->where('nav.0.items.0.active', true)
        ->where('nav.0.items.0.href', '/dashboard')
        ->where('nav.1.items.0.href', null)
        ->where('abilities.view-activity', true)
        ->where('admin.initials', mb_strtoupper(mb_substr($admin->name, 0, 1)).mb_strtoupper(mb_substr(explode(' ', $admin->name)[1] ?? '', 0, 1))));
});

it('lets only the admin open the activity log', function () {
    $this->actingAs(AdminUser::factory()->create(), 'admin')->get('/dashboard/activity')
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 403));

    $this->actingAs(AdminUser::factory()->admin()->create(), 'admin')->get('/dashboard/activity')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Activity/Index'));
});

it('lists, filters and searches the activity log, newest first, in Cairo time', function () {
    $admin = AdminUser::factory()->admin()->create(['name' => 'Staff One']);
    $this->actingAs($admin, 'admin');
    Developer::factory()->create(['name_en' => 'Before']);
    $dev = Developer::factory()->create();
    $dev->update(['name_en' => 'After']);

    $page = $this->get('/dashboard/activity')->assertOk()->viewData('page')['props']['table'];
    expect($page['data'][0]['action'])->toBe('developers.updated')
        ->and($page['data'][0]['actor'])->toBe('Staff One')
        ->and($page['data'][0]['changes'][0])->toMatchArray(['field' => 'name_en', 'to' => 'After']);

    $filtered = $this->get('/dashboard/activity?filters[action]=developers.created')->viewData('page')['props']['table'];
    expect(collect($filtered['data'])->pluck('action')->unique()->all())->toBe(['developers.created']);

    $searched = $this->get('/dashboard/activity?q=updated')->viewData('page')['props']['table'];
    expect($searched['meta']['total'])->toBe(ActivityLog::query()->where('action', 'like', '%updated%')->count());
});

it('exports the filtered activity log as CSV for the admin only', function () {
    $admin = AdminUser::factory()->admin()->create();
    $this->actingAs($admin, 'admin');
    Developer::factory()->create();

    $response = $this->get('/dashboard/activity?export=csv');
    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->streamedContent())->toContain('developers.created');
});

it('renders the dashboard error page for an unknown dashboard address', function () {
    $this->actingAs(AdminUser::factory()->create(), 'admin')
        ->get('/dashboard/no-such-page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 404));
});
