<?php

use App\Models\ActivityLog;
use App\Models\AdminUser;
use App\Models\Developer;
use App\Models\DeveloperPolicy;
use App\Models\MarketValue;
use App\Models\ReportIndicator;
use App\Support\Settings\Settings;
use Database\Seeders\ReferenceSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Phase 6 step 3: the dashboard editor for the calculator values (with their internal sources and the client's
| confirmation), the display settings, developer policies (J3) and the reports page's indicators. Admin only;
| every write is in the activity log.
*/

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->admin = AdminUser::factory()->admin()->create(['name' => 'Staff One']);
});

function valueBody(array $settings, array $extra = []): array
{
    return ['settings' => $settings, 'checked_on' => '2026-11-24', 'note' => 'internal', 'confirmed' => false, ...$extra];
}

it('lets only the admin open and change the calculator settings and indicators', function () {
    $reviewer = AdminUser::factory()->create();
    foreach (['/dashboard/calculator', '/dashboard/indicators'] as $path) {
        $this->actingAs($reviewer, 'admin')->get($path)->assertForbidden();
    }
    $this->actingAs($reviewer, 'admin')->put('/dashboard/calculator/values/cancellation', valueBody(['penalty_max_bp' => 3000]))->assertForbidden();
    $this->actingAs($reviewer, 'admin')->post('/dashboard/indicators', [])->assertForbidden();
    expect(app(Settings::class)->int('penalty_max_bp'))->toBe(1500);

    $nav = fn (AdminUser $who) => collect($this->actingAs($who, 'admin')->get('/dashboard')->viewData('page')['props']['nav'])
        ->flatMap(fn (array $g) => collect($g['items'])->pluck('href', 'key'));
    expect($nav($this->admin)['calculator'])->toBe('/dashboard/calculator')
        ->and($nav($this->admin)['reports'])->toBe('/dashboard/indicators')
        ->and($nav($reviewer)->has('calculator'))->toBeFalse();
});

it('shows each value with its settings, status, sources and note to the admin', function () {
    $this->actingAs($this->admin, 'admin')->get('/dashboard/calculator')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Calculator/Index')
        ->has('values', 6)
        ->where('values.0.key', 'cancellation')
        ->where('values.0.confirmed', true)
        ->where('values.0.confirmed_note', fn (string $note) => str_contains($note, '2026-11-25'))
        ->where('values.5.key', 'discount_rate')
        ->where('values.5.confirmed', false)
        ->where('values.0.settings.1.key', 'penalty_min_bp')
        ->where('values.0.settings.1.unit', 'pct')
        ->where('values.0.settings.1.value', 1500)
        ->where('values.0.sources.0.ref', 'S1')
        ->has('policies', 0)
        // K3: the 25% deep-loss line is confirmed by Michael.
        ->where('display.confirmed', true));
});

it('saves a value, keeps it provisional until confirmed, and logs every change', function () {
    $this->actingAs($this->admin, 'admin')->from('/dashboard/calculator')
        ->put('/dashboard/calculator/values/cancellation', valueBody(['penalty_basis' => 'total', 'penalty_min_bp' => 1000, 'penalty_max_bp' => 2000]))
        ->assertRedirect('/dashboard/calculator')->assertSessionHas('status');

    $all = app(Settings::class)->all();
    expect($all['penalty_max_bp']['value'])->toBe(2000)
        ->and($all['penalty_max_bp']['provisional'])->toBeTrue()
        ->and(MarketValue::query()->where('key', 'cancellation')->value('confirmed_at'))->toBeNull()
        ->and(ActivityLog::query()->where('action', 'settings.updated')->where('actor_id', $this->admin->id)->exists())->toBeTrue();

    // The client confirms: every setting of the value loses its provisional flag, and the confirmation is dated.
    $this->actingAs($this->admin, 'admin')->put('/dashboard/calculator/values/cancellation', valueBody(['penalty_max_bp' => 2000], ['confirmed' => true]))->assertSessionHasNoErrors();
    $all = app(Settings::class)->all();
    $row = MarketValue::query()->where('key', 'cancellation')->firstOrFail();
    expect($all['penalty_basis']['provisional'])->toBeFalse()
        ->and($all['penalty_min_bp']['provisional'])->toBeFalse()
        ->and($all['penalty_max_bp']['provisional'])->toBeFalse()
        ->and($row->confirmed_by)->toBe($this->admin->id)
        ->and($this->getJson('/api/v1/market-values', apiHeaders())->json('data.values.0.provisional'))->toBeFalse();

    // Switched back: provisional again everywhere.
    $this->actingAs($this->admin, 'admin')->put('/dashboard/calculator/values/cancellation', valueBody([], ['confirmed' => false]));
    expect(app(Settings::class)->all()['penalty_min_bp']['provisional'])->toBeTrue()
        ->and($row->refresh()->confirmed_at)->toBeNull();
});

it('only writes the settings of the value being saved', function () {
    $this->actingAs($this->admin, 'admin')->put('/dashboard/calculator/values/refund', valueBody(['refund_rule' => 'fixed', 'refund_years_fixed' => 3, 'buyer_fee_bp' => 900]))->assertSessionHasNoErrors();

    expect(app(Settings::class)->string('refund_rule'))->toBe('fixed')
        ->and(app(Settings::class)->int('refund_years_fixed'))->toBe(3)
        ->and(app(Settings::class)->int('buyer_fee_bp'))->toBe(100);
});

it('refuses a range upside down and a value outside its limits, on the right field, and saves nothing', function () {
    $this->actingAs($this->admin, 'admin')->put('/dashboard/calculator/values/cancellation', valueBody(['penalty_min_bp' => 1600, 'penalty_max_bp' => 1500]))
        ->assertSessionHasErrors('settings.penalty_max_bp');
    $this->actingAs($this->admin, 'admin')->put('/dashboard/calculator/values/transfer_fee', valueBody(['transfer_fee_bp' => 300, 'transfer_fee_fixed' => -5]))
        ->assertSessionHasErrors('settings.transfer_fee_fixed');
    $this->actingAs($this->admin, 'admin')->put('/dashboard/calculator/values/cancellation', valueBody(['penalty_max_bp' => 1500], ['checked_on' => '2099-01-01']))
        ->assertSessionHasErrors('checked_on');
    // K4: the deposit range is a pair too.
    $this->actingAs($this->admin, 'admin')->put('/dashboard/calculator/values/maintenance_deposit', valueBody(['maintenance_deposit_min_bp' => 1200, 'maintenance_deposit_max_bp' => 1000]))
        ->assertSessionHasErrors('settings.maintenance_deposit_max_bp');

    // All or nothing: the valid transfer_fee_bp in the refused call was not kept.
    expect(app(Settings::class)->int('transfer_fee_bp'))->toBe(0)
        ->and(app(Settings::class)->int('penalty_min_bp'))->toBe(1500)
        ->and(app(Settings::class)->int('maintenance_deposit_min_bp'))->toBe(800);
    $this->actingAs($this->admin, 'admin')->put('/dashboard/calculator/values/no_such_value', valueBody([]))->assertNotFound();
});

it('saves the display settings: the deep loss line and the present-value switch', function () {
    $this->actingAs($this->admin, 'admin')->put('/dashboard/calculator/display', ['deep_loss_ratio_bp' => 6000, 'show_present_value' => true, 'confirmed' => true])->assertSessionHasNoErrors();

    $all = app(Settings::class)->all();
    expect($all['deep_loss_ratio_bp']['value'])->toBe(6000)
        ->and($all['deep_loss_ratio_bp']['provisional'])->toBeFalse()
        ->and($all['show_present_value']['value'])->toBeTrue()
        // With the present-value line on, the public list carries the discount rate.
        ->and(array_column($this->getJson('/api/v1/market-values', apiHeaders())->json('data.values'), 'key'))->toContain('discount_rate');
});

it('adds, edits and deletes a source, logged', function () {
    $url = '/dashboard/calculator/values/refund/sources';
    $body = ['ref' => 'S20', 'publisher' => 'A newspaper', 'url' => 'https://example.test/a', 'published_on' => '2026-11-01', 'read_on' => '2026-11-24', 'figure' => 'refunds over 3 years', 'origin' => 'paper-1', 'counted' => true];
    $this->actingAs($this->admin, 'admin')->post($url, $body)->assertSessionHasNoErrors();
    $source = MarketValue::query()->where('key', 'refund')->firstOrFail()->sources()->where('ref', 'S20')->firstOrFail();

    $this->actingAs($this->admin, 'admin')->put("{$url}/{$source->id}", [...$body, 'counted' => false])->assertSessionHasNoErrors();
    expect($source->refresh()->counted)->toBeFalse();

    // A source of another value cannot be reached through this one.
    $this->actingAs($this->admin, 'admin')->delete("/dashboard/calculator/values/cancellation/sources/{$source->id}")->assertNotFound();
    $this->actingAs($this->admin, 'admin')->post($url, [...$body, 'url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');

    $this->actingAs($this->admin, 'admin')->delete("{$url}/{$source->id}")->assertSessionHasNoErrors();
    expect($source->exists && $source->fresh() === null)->toBeTrue()
        ->and(ActivityLog::query()->where('action', 'market_value_sources.deleted')->exists())->toBeTrue();
});

it('adds a developer policy only from the developer\'s own terms, one per developer, used once approved', function () {
    Developer::factory()->create(['slug' => 'nile-homes']);
    $body = ['developer' => 'nile-homes', 'penalty_min_bp' => 500, 'penalty_max_bp' => 800, 'source_title' => 'Resale terms', 'source_url' => 'https://example.test/terms', 'checked_on' => '2026-11-20', 'approved' => false];

    $this->actingAs($this->admin, 'admin')->post('/dashboard/calculator/policies', [...$body, 'penalty_min_bp' => null, 'penalty_max_bp' => null])->assertSessionHasErrors('penalty_basis');
    $this->actingAs($this->admin, 'admin')->post('/dashboard/calculator/policies', [...$body, 'source_url' => ''])->assertSessionHasErrors('source_url');
    $this->actingAs($this->admin, 'admin')->post('/dashboard/calculator/policies', [...$body, 'penalty_min_bp' => 900])->assertSessionHasErrors('penalty_max_bp');
    $this->actingAs($this->admin, 'admin')->post('/dashboard/calculator/policies', $body)->assertSessionHasNoErrors();
    $this->actingAs($this->admin, 'admin')->post('/dashboard/calculator/policies', $body)->assertSessionHasErrors('developer');

    $policy = DeveloperPolicy::query()->firstOrFail();
    expect($policy->client_approved_at)->toBeNull()
        ->and($policy->penalty_basis)->toBeNull();

    $this->actingAs($this->admin, 'admin')->put("/dashboard/calculator/policies/{$policy->id}", [...$body, 'approved' => true])->assertSessionHasNoErrors();
    // Approved, it stays internal (K6): the public market values never name it.
    expect($policy->refresh()->client_approved_by)->toBe($this->admin->id)
        ->and($this->getJson('/api/v1/market-values', apiHeaders())->json('data'))->not->toHaveKey('developers')
        ->and(ActivityLog::query()->where('action', 'developer_policies.updated')->exists())->toBeTrue();

    $this->actingAs($this->admin, 'admin')->delete("/dashboard/calculator/policies/{$policy->id}")->assertSessionHasNoErrors();
    expect(DeveloperPolicy::query()->count())->toBe(0);
});

it('adds, edits and deletes indicators; only published ones reach the site', function () {
    $body = ['title_ar' => 'متوسط مدة البيع', 'title_en' => 'Average time to sell', 'value' => '45', 'unit' => 'count', 'as_of' => '2026-10-31', 'source' => 'client report', 'sort' => 1, 'is_published' => false];
    $this->actingAs($this->admin, 'admin')->post('/dashboard/indicators', [...$body, 'unit' => 'weeks'])->assertSessionHasErrors('unit');
    $this->actingAs($this->admin, 'admin')->post('/dashboard/indicators', [...$body, 'source' => ''])->assertSessionHasErrors('source');
    $this->actingAs($this->admin, 'admin')->post('/dashboard/indicators', $body)->assertSessionHasNoErrors();
    $indicator = ReportIndicator::query()->firstOrFail();
    expect($this->getJson('/api/v1/indicators', apiHeaders())->json('data'))->toBe([]);

    $this->actingAs($this->admin, 'admin')->put("/dashboard/indicators/{$indicator->id}", [...$body, 'is_published' => true])->assertSessionHasNoErrors();
    expect($this->getJson('/api/v1/indicators?locale=ar', apiHeaders())->json('data.0.title'))->toBe('متوسط مدة البيع');

    $this->actingAs($this->admin, 'admin')->get('/dashboard/indicators')->assertInertia(fn (Assert $page) => $page->component('Indicators/Index')->has('indicators', 1)->has('units', 5));
    $this->actingAs($this->admin, 'admin')->delete("/dashboard/indicators/{$indicator->id}")->assertSessionHasNoErrors();
    expect(ReportIndicator::query()->count())->toBe(0)
        ->and(ActivityLog::query()->where('action', 'report_indicators.deleted')->exists())->toBeTrue();
});

it('labels each field by what it holds: refund years are years, not pounds, and a fixed fee is pounds', function () {
    $this->actingAs($this->admin, 'admin')->get('/dashboard/calculator')->assertInertia(fn (Assert $page) => $page
        ->where('values.1.settings.1.key', 'refund_years_fixed')
        ->where('values.1.settings.1.unit', 'years')
        ->where('values.3.settings.2.key', 'transfer_fee_fixed')
        ->where('values.3.settings.2.unit', 'egp')
        ->where('values.0.confirmed_note', fn (string $note) => ! str_contains($note, '—')));
});
