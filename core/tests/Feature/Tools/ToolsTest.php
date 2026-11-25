<?php

use App\Models\ActivityLog;
use App\Models\Developer;
use App\Models\DeveloperPolicy;
use App\Models\MarketValue;
use App\Models\ReportIndicator;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Support\Calculator\ExitCalculator;
use App\Support\Calculator\ExitReportPdf;
use App\Support\Calculator\MarketValues;
use App\Support\Settings\Settings;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

/*
| Phase 6 (PLAN 4, plan approved 2026-11-24): the researched values loaded as provisional (Michael 2026-11-25), the
| public market values without sources (J10), the calculator's two tabs (stores nothing, J12; limited per address),
| developer policies only once the client approves them (J3), and the client's indicators.
*/

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
});

function sellerCalc(array $body = [], string $ip = '203.0.113.70'): TestResponse
{
    return test()->postJson('/api/v1/calculator/seller?locale=en', [
        'total_price' => 6_000_000, 'paid_amount' => 1_500_000, 'years_paid' => 3, ...$body,
    ], [...apiHeaders(), 'X-Client-Ip' => $ip]);
}

function approvedPolicy(array $overrides = [], bool $approved = true): DeveloperPolicy
{
    $developer = Developer::factory()->create(['slug' => 'nile-homes', 'name_en' => 'Nile Homes', 'name_ar' => 'نايل هومز']);

    return DeveloperPolicy::query()->create([
        'developer_id' => $developer->id,
        'penalty_min_bp' => 500, 'penalty_max_bp' => 800, 'transfer_fee_bp' => 200, 'refund_rule' => 'mirror',
        'source_title' => 'Nile Homes resale terms', 'source_url' => 'https://example.test/terms',
        'checked_on' => '2026-11-20',
        'client_approved_at' => $approved ? now() : null,
        ...$overrides,
    ]);
}

it('loads the values the client confirmed on 2026-11-25, dated, and keeps the discount rate provisional', function () {
    $all = app(Settings::class)->all();
    $value = fn (string $k) => $all[$k]['value'];

    expect($value('penalty_basis'))->toBe('total')
        ->and($value('penalty_min_bp'))->toBe(1500)
        ->and($value('penalty_max_bp'))->toBe(1500)
        ->and($value('refund_rule'))->toBe('lump')
        ->and($value('late_fee_bp'))->toBe(100)
        ->and($value('transfer_fee_mode'))->toBe('fixed')
        ->and($value('transfer_fee_fixed'))->toBe(50_000)
        ->and($value('maintenance_deposit_moves'))->toBeTrue()
        ->and($value('discount_rate_bp'))->toBe(2000)
        ->and($value('show_present_value'))->toBeFalse()
        ->and($value('rl_calculator_ip'))->toBe(60)
        ->and($all)->not->toHaveKey('transfer_fee_range_min_bp');

    $rows = MarketValue::query()->withCount('sources')->get()->keyBy('key');
    foreach (MarketValues::definitions() as $key => $def) {
        $confirmed = $key !== 'discount_rate';
        expect($rows[$key]->confirmed_at?->format('Y-m-d'))->toBe($confirmed ? '2026-11-25' : null);
        foreach ($def['settings'] as $setting) {
            expect($all[$setting]['provisional'])->toBe(! $confirmed, "{$setting}");
        }
    }
    expect($rows->keys()->sort()->values()->all())->toBe(['cancellation', 'discount_rate', 'late_fee', 'maintenance_deposit', 'refund', 'transfer_fee'])
        ->and($rows['cancellation']->checked_on->format('Y-m-d'))->toBe('2026-11-24')
        ->and($rows['transfer_fee']->sources_count)->toBe(4)
        ->and($rows['cancellation']->sources()->where('ref', 'S5')->value('url'))->toContain('/7610511');
});

it('never changes a stored market value when the seeder runs again', function () {
    MarketValue::query()->where('key', 'refund')->update(['note' => 'edited in the dashboard']);
    $this->seed(ReferenceSeeder::class);

    expect(MarketValue::query()->where('key', 'refund')->value('note'))->toBe('edited in the dashboard')
        ->and(MarketValue::query()->count())->toBe(6);
});

it('sends the public site each value and the month checked, never the sources, notes or links (J10)', function () {
    $response = $this->getJson('/api/v1/market-values?locale=ar', apiHeaders())->assertOk();
    $body = (string) $response->getContent();
    $keys = array_column($response->json('data.values'), 'key');

    // The late fee is on (1%); the present-value line is off, so the discount rate is not listed.
    expect($keys)->toBe(['cancellation', 'refund', 'late_fee', 'transfer_fee', 'maintenance_deposit'])
        ->and($response->json('data.values.0'))->toMatchArray(['checked' => '2026-11', 'provisional' => false, 'under_review' => false])
        ->and($response->json('data.values.0.values'))->toBe(['penalty_basis' => 'total', 'penalty_min_bp' => 1500, 'penalty_max_bp' => 1500])
        ->and($response->json('data.values.3.values'))->toBe(['transfer_fee_mode' => 'fixed', 'transfer_fee_bp' => 0, 'transfer_fee_fixed' => 50_000])
        ->and($response->json('data.values.4.values'))->toBe(['maintenance_deposit_moves' => true])
        ->and($response->json('data.developers'))->toBe([]);
    foreach (['masrawy', 'youm7', 'http', 'S1', 'Saada', 'origin', 'note', 'figure', 'publisher', '800', 'confirmed'] as $leak) {
        expect(stripos($body, $leak))->toBeFalse("the public answer contains {$leak}");
    }
});

it('marks a value due for a re-check after 6 months and under review on the site after 12 (J8)', function () {
    $row = MarketValue::query()->where('key', 'cancellation')->firstOrFail();

    expect(MarketValues::status($row, Carbon::parse('2027-05-23')->toImmutable()))->toBe('current')
        ->and(MarketValues::status($row, Carbon::parse('2027-05-24')->toImmutable()))->toBe('recheck')
        ->and(MarketValues::status($row, Carbon::parse('2027-11-24')->toImmutable()))->toBe('stale');

    $this->travelTo(Carbon::parse('2027-11-25'));
    expect($this->getJson('/api/v1/market-values', apiHeaders())->json('data.values.0.under_review'))->toBeTrue();
});

it('computes the seller tab with the confirmed values, nothing provisional', function () {
    $r = sellerCalc()->assertOk()->json('data');

    // 15% of 6,000,000 kept, 600,000 back in one payment; the fixed EGP 50,000 transfer fee.
    expect($r['cancel'])->toMatchArray(['penalty_min' => 900_000, 'penalty_max' => 900_000, 'refund_min' => 600_000, 'refund_max' => 600_000, 'refund_rule' => 'lump', 'refund_years' => 0])
        ->and($r['transfer'])->toMatchArray(['transfer_fee' => 50_000, 'transfer_fee_bp' => null, 'net' => 1_450_000, 'lokta_fee' => 0])
        ->and($r['provisional'])->toBe([])
        ->and($r['policy'])->toBeNull()
        ->and($r)->not->toHaveKey('inputs');
});

it('pays overdue installments and their 1% penalty from the proceeds, and takes the penalty off the refund', function () {
    $r = sellerCalc(['overdue_amount' => 100_000])->assertOk()->json('data');
    expect($r['transfer'])->toMatchArray(['arrears_to_developer' => 100_000, 'late_fees' => 1_000, 'net' => 1_349_000])
        ->and($r['cancel'])->toMatchArray(['late_fees' => 1_000, 'refund_min' => 599_000]);

    app(Settings::class)->set('late_fee_bp', 0);
    expect(sellerCalc(['overdue_amount' => 100_000])->json('data.transfer.net'))->toBe(1_350_000);
});

it('refuses figures that cannot be true, in the page language', function () {
    sellerCalc(['paid_amount' => 7_000_000])->assertUnprocessable()
        ->assertJsonPath('errors.paid_amount.0', 'The amount paid cannot be more than the total contract price.');
    sellerCalc(['overdue_amount' => 5_000_000])->assertUnprocessable()->assertJsonValidationErrors('overdue_amount');
    sellerCalc(['years_paid' => 0, 'total_price' => 'lots'])->assertUnprocessable()->assertJsonValidationErrors(['years_paid', 'total_price']);
});

it('stores nothing: no row and no log line for a calculation (J12)', function () {
    $before = [ActivityLog::query()->count(), Setting::query()->count(), MarketValue::query()->count()];
    sellerCalc()->assertOk();
    $this->postJson('/api/v1/calculator/buyer', ['contract_price' => 3_000_000, 'price_today' => 4_000_000], apiHeaders())->assertOk();

    expect([ActivityLog::query()->count(), Setting::query()->count(), MarketValue::query()->count()])->toBe($before);
});

it('limits calculations per address and hour, with the time to wait', function () {
    app(Settings::class)->set('rl_calculator_ip', 3);
    foreach (range(1, 3) as $i) {
        sellerCalc(ip: '203.0.113.80')->assertOk();
    }
    sellerCalc(ip: '203.0.113.80')->assertStatus(429)->assertHeader('Retry-After')->assertJsonStructure(['message', 'retry_after']);
    $this->postJson('/api/v1/calculator/buyer', ['contract_price' => 1, 'price_today' => 2], [...apiHeaders(), 'X-Client-Ip' => '203.0.113.80'])->assertStatus(429);
    sellerCalc(ip: '203.0.113.81')->assertOk();
});

it('needs the server key like every other route', function () {
    $this->postJson('/api/v1/calculator/seller', ['total_price' => 1, 'paid_amount' => 1, 'years_paid' => 1])->assertUnauthorized();
    $this->getJson('/api/v1/market-values')->assertUnauthorized();
});

it('computes the buyer tab: the 1% fee and no transfer fee in the buyer figures', function () {
    $r = $this->postJson('/api/v1/calculator/buyer', ['contract_price' => 3_000_000, 'price_today' => 4_000_000], apiHeaders())->assertOk()->json('data');

    expect($r)->toMatchArray(['fee_bp' => 100, 'fee' => 30_000, 'fee_vat' => 0, 'gain' => 970_000, 'gain_bp' => 2425])
        ->and($r)->not->toHaveKey('transfer_fee');
});

it('uses a developer policy only once the client approves it, and names only that developer (J3)', function () {
    $policy = approvedPolicy(approved: false);

    expect($this->getJson('/api/v1/market-values?locale=en', apiHeaders())->json('data.developers'))->toBe([]);
    sellerCalc(['developer' => 'nile-homes'])->assertUnprocessable()->assertJsonValidationErrors('developer');

    $policy->update(['client_approved_at' => now()]);
    expect($this->getJson('/api/v1/market-values?locale=en', apiHeaders())->json('data.developers'))
        ->toBe([['slug' => 'nile-homes', 'name' => 'Nile Homes', 'checked' => '2026-11']]);

    $r = sellerCalc(['developer' => 'nile-homes'])->assertOk()->json('data');
    // 5-8% of 6,000,000 and 2% transfer fee from the developer's own terms; those keys are no longer provisional.
    expect($r['cancel'])->toMatchArray(['penalty_min' => 300_000, 'penalty_max' => 480_000])
        ->and($r['transfer']['transfer_fee'])->toBe(120_000)
        ->and($r['policy'])->toMatchArray(['developer' => 'nile-homes', 'checked_on' => '2026-11-20'])
        ->and($r['transfer']['transfer_fee_bp'])->toBe(200)
        ->and($r['cancel']['refund_years'])->toBe(3)
        ->and($r['provisional'])->toBe([]);

    $policy->developer?->update(['is_active' => false]);
    sellerCalc(['developer' => 'nile-homes'])->assertUnprocessable();
});

it('applies an approved policy to the exit report of that developer\'s unit', function () {
    $policy = approvedPolicy();
    $seller = User::factory()->create();
    $unit = completeDraft($seller, ['developer_id' => $policy->developer_id, 'total_price' => 6_000_000, 'paid_amount' => 1_500_000, 'transfer_fee_bp' => null]);

    $r = app(ExitCalculator::class)->forUnit($unit);
    expect($r)->not->toBeNull()
        ->and($r['cancel']['penalty_min'])->toBe(300_000)
        ->and($r['settings']['penalty_max_bp'])->toBe(800)
        ->and($r['policy']['developer'])->toBe('nile-homes');

    $policy->update(['client_approved_at' => null]);
    expect(app(ExitCalculator::class)->forUnit($unit->refresh())['cancel']['penalty_min'])->toBe(900_000);
});

it('lists only published indicators, without their source (J9, J10)', function () {
    ReportIndicator::query()->create(['title_ar' => 'مؤشر', 'title_en' => 'Units transferred', 'value' => '42', 'unit' => 'count', 'as_of' => '2026-10-31', 'source' => 'client sheet, internal', 'is_published' => true]);
    ReportIndicator::query()->create(['title_ar' => 'مخفي', 'title_en' => 'Hidden one', 'value' => '1', 'unit' => 'count', 'as_of' => '2026-10-31', 'source' => 'x', 'is_published' => false]);

    $response = $this->getJson('/api/v1/indicators?locale=en', apiHeaders())->assertOk();
    expect($response->json('data'))->toBe([['title' => 'Units transferred', 'value' => '42.00', 'unit' => 'count', 'note' => null, 'as_of' => '2026-10']])
        ->and((string) $response->getContent())->not->toContain('client sheet');
});

it('prints the month each value was checked in the exit report, and marks a developer\'s own terms', function () {
    $policy = approvedPolicy();
    $seller = User::factory()->create();
    $render = function (Unit $unit): string {
        $r = app(ExitCalculator::class)->forUnit($unit);

        return view('pdf.exit-report', [
            'unit' => $unit, 'r' => $r, 'settings' => $r['settings'], 'kind' => 'declared', 'locale' => 'en', 'draft' => true,
            'checked' => ['cancellation' => '2026-11', 'refund' => '2026-11', 'transfer_fee' => '2026-11', 'late_fee' => '2026-11'],
            'label' => fn (string $key): string => $key, 'reference' => '#0001', 'date' => '2026-11-25',
        ])->render();
    };

    $market = $render(completeDraft($seller, ['total_price' => 6_000_000, 'paid_amount' => 1_500_000, 'transfer_fee_bp' => null]));
    expect(substr_count($market, 'assume.checked 2026-11'))->toBe(4)
        ->and($market)->not->toContain('assume.developer_terms');

    $own = $render(completeDraft($seller, ['developer_id' => $policy->developer_id, 'total_price' => 6_000_000, 'paid_amount' => 1_500_000, 'transfer_fee_bp' => null]));
    // The cancellation, the refund rule and the transfer fee come from the developer's terms; the late fee does not.
    expect(substr_count($own, 'assume.developer_terms'))->toBe(3)
        ->and(substr_count($own, 'assume.checked 2026-11'))->toBe(1);
});

it('takes "rates" off the exit report band once every value the figures use is confirmed; texts keep it on', function () {
    $seller = User::factory()->create();
    $r = app(ExitCalculator::class)->forUnit(completeDraft($seller, ['total_price' => 6_000_000, 'paid_amount' => 1_500_000]));

    expect($r['provisional'])->toBe([])
        ->and(ExitReportPdf::band($r['provisional'] !== [], true))->toBe('draft_band_texts')
        ->and(ExitReportPdf::band(true, true))->toBe('draft_band')
        ->and(ExitReportPdf::band(true, false))->toBe('draft_band')
        ->and(ExitReportPdf::band(false, false))->toBeNull();
});
