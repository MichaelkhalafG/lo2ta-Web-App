<?php

use App\Enums\InstallmentFrequency;
use App\Enums\MainType;
use App\Enums\SubType;
use App\Enums\UnitState;
use App\Models\Area;
use App\Models\Compound;
use App\Models\Unit;
use Database\Seeders\ReferenceSeeder;

/*
| The opportunities list (PLAN 2.5 filters, 2.6): filters, sorts, paging, the card title, and the compound pages.
*/

function listed(array $overrides = []): Unit
{
    return Unit::factory()->published()->create($overrides);
}

function codes(array $query = []): array
{
    return collect(test()->getJson('/api/v1/units?'.http_build_query($query), apiHeaders())->assertOk()->json('data'))->pluck('code')->all();
}

it('lists published units only', function () {
    $live = listed();
    Unit::factory()->create(['state' => UnitState::Draft]);
    $reserved = listed();
    $reserved->forceFill(['state' => UnitState::Reserved])->save();

    expect(codes())->toBe([$live->code]);
});

it('compares "pay the owner up to" with the lowest amount the owner accepts', function () {
    $asks = listed(['paid_amount' => 1_320_000]);
    $flexible = listed(['paid_amount' => 1_320_000, 'accepts_less' => true, 'lowest_accepted_amount' => 1_200_000]);
    $dear = listed(['paid_amount' => 2_000_000]);

    expect(codes(['owner_max' => 1_250_000]))->toBe([$flexible->code])
        ->and(codes(['owner_max' => 1_320_000]))->toEqualCanonicalizing([$asks->code, $flexible->code])
        ->and(codes(['owner_max' => 1_000_000]))->toBe([])
        ->and($dear->code)->not->toBeIn(codes(['owner_max' => 1_999_999]));
});

it('filters by type, installment, verified and the lokta tag', function () {
    $land = listed(['main_type' => MainType::Land, 'sub_type' => SubType::ResidentialLand]);
    $cheap = listed(['installment_amount' => 50_000]);
    $paidUp = listed(['total_price' => 1_500_000, 'paid_amount' => 1_500_000, 'installment_amount' => null, 'installment_frequency' => null, 'next_due_date' => null]);
    $verified = listed(['installment_amount' => 500_000]);
    $verified->forceFill(['verified_at' => now(), 'deal_tag' => true])->save();

    expect(codes(['type' => 'land']))->toBe([$land->code])
        ->and(codes(['installment_max' => 60_000]))->toEqualCanonicalizing([$cheap->code, $paidUp->code])
        ->and(codes(['verified' => 1]))->toBe([$verified->code])
        ->and(codes(['deal' => 1]))->toBe([$verified->code]);
});

it('takes an area with its districts, and a governorate with everything under it', function () {
    $this->seed(ReferenceSeeder::class);
    $newCairo = Area::query()->where('slug', 'new-cairo')->sole();
    $fifth = Area::query()->where('slug', 'fifth-settlement')->sole();
    $inArea = listed(['area_id' => $newCairo->id]);
    $inDistrict = listed(['area_id' => $fifth->id]);
    $elsewhere = listed(['area_id' => Area::query()->where('slug', 'madinaty')->sole()->id]);

    expect(codes(['area' => 'new-cairo']))->toEqualCanonicalizing([$inArea->code, $inDistrict->code])
        ->and(codes(['area' => 'fifth-settlement']))->toBe([$inDistrict->code])
        ->and(codes(['area' => 'cairo']))->toEqualCanonicalizing([$inArea->code, $inDistrict->code, $elsewhere->code]);
});

it('sorts by default (accepts less first), by the owner amount, the installment and the newest', function () {
    $a = listed(['paid_amount' => 900_000, 'installment_amount' => 90_000, 'published_at' => now()->subDays(3)]);
    $b = listed(['paid_amount' => 1_400_000, 'accepts_less' => true, 'lowest_accepted_amount' => 800_000, 'installment_amount' => 70_000, 'published_at' => now()->subDays(2)]);
    $c = listed(['paid_amount' => 1_000_000, 'installment_amount' => 120_000, 'published_at' => now()->subDay()]);
    foreach ([$a, $b, $c] as $unit) {
        $unit->forceFill(['published_at' => $unit->getAttributes()['published_at']])->save();
    }

    expect(codes())->toBe([$b->code, $c->code, $a->code])
        ->and(codes(['sort' => 'owner_low']))->toBe([$b->code, $a->code, $c->code])
        ->and(codes(['sort' => 'installment_low']))->toBe([$b->code, $a->code, $c->code])
        ->and(codes(['sort' => 'newest']))->toBe([$c->code, $b->code, $a->code]);
});

it('pages by 24 with the totals, and refuses unknown filter values', function () {
    Unit::factory()->published()->count(26)->create();

    $first = $this->getJson('/api/v1/units', apiHeaders())->json();
    $second = $this->getJson('/api/v1/units?page=2', apiHeaders())->json();

    expect($first['meta'])->toBe(['page' => 1, 'last_page' => 2, 'per_page' => 24, 'total' => 26])
        ->and($first['data'])->toHaveCount(24)->and($second['data'])->toHaveCount(2);
    foreach (['sort' => 'price; drop table units', 'type' => 'castle', 'area' => 'nowhere', 'owner_max' => 'x', 'page' => 0] as $key => $value) {
        $this->getJson('/api/v1/units?'.$key.'='.urlencode((string) $value), apiHeaders())->assertStatus(422);
    }
});

it('builds the card title and the installments line from public fields', function () {
    $this->seed(ReferenceSeeder::class);
    $unit = listed([
        'area_id' => Area::query()->where('slug', 'fifth-settlement')->sole()->id, 'sub_type' => SubType::Apartment, 'bedrooms' => 3,
        'installment_frequency' => InstallmentFrequency::Quarterly, 'years_left' => 7, 'installment_amount' => 126_000,
    ]);

    $ar = $this->getJson('/api/v1/units?locale=ar', apiHeaders())->json('data.0');
    $en = $this->getJson('/api/v1/units?locale=en', apiHeaders())->json('data.0');

    expect($ar['title'])->toBe('شقة 3 غرف في التجمع الخامس')
        ->and($en['title'])->toBe('Apartment with 3 bedrooms in Fifth Settlement')
        ->and($en['place'])->toBe(['Fifth Settlement', 'New Cairo'])
        ->and($en['installments'])->toMatchArray(['count' => 28, 'amount' => 126_000])
        ->and($unit->code)->toBe($en['code']);
});

it('answers the unit page with its schedule and the buyer fee', function () {
    $unit = listed(['total_price' => 4_848_000, 'installment_frequency' => InstallmentFrequency::Quarterly, 'years_left' => 2,
        'installment_amount' => 126_000, 'next_due_date' => '2027-01-15']);

    $data = $this->getJson('/api/v1/units/'.$unit->code, apiHeaders())->json('data');

    expect($data['schedule'])->toHaveCount(8)
        ->and($data['schedule'][0])->toBe(['n' => 1, 'date' => '2027-01-15', 'amount' => 126_000])
        ->and($data['schedule'][7]['date'])->toBe('2028-10-15')
        ->and($data['money']['buyer_fee'])->toMatchArray(['bp' => 100, 'amount' => 48_480, 'vat' => 0]);
});

it('lists compounds with their published units and opens a compound page', function () {
    $compound = Compound::factory()->create(['intro_ar' => 'نبذة', 'intro_is_placeholder' => true]);
    listed(['compound_id' => $compound->id, 'developer_id' => $compound->developer_id, 'area_id' => $compound->area_id]);
    $empty = Compound::factory()->create();

    $index = collect($this->getJson('/api/v1/compound-pages', apiHeaders())->assertOk()->json('data'))->keyBy('slug');
    $page = $this->getJson('/api/v1/compound-pages/'.$compound->slug.'?locale=ar', apiHeaders())->assertOk()->json('data');

    expect($index[$compound->slug]['units'])->toBe(1)->and($index[$empty->slug]['units'])->toBe(0)
        ->and($page['units'])->toHaveCount(1)->and($page['intro'])->toBe('نبذة')->and($page['intro_is_placeholder'])->toBeTrue();
    $this->getJson('/api/v1/compound-pages/no-such-compound', apiHeaders())->assertNotFound();
});
