<?php

use App\Enums\UnitState;
use App\Models\Unit;
use Database\Seeders\ReferenceSeeder;

it('keeps /health public and refuses keyed routes without the right key', function () {
    $this->getJson('/api/v1/health')->assertOk()->assertJson(['ok' => true]);

    $this->getJson('/api/v1/areas')->assertStatus(401);
    $this->getJson('/api/v1/areas', ['X-Api-Key' => 'wrong'])->assertStatus(401);
    $this->getJson('/api/v1/areas', apiHeaders())->assertOk();
});

it('fails closed when no server key is configured', function () {
    config(['services.web.server_key' => '']);

    $this->getJson('/api/v1/areas', ['X-Api-Key' => ''])->assertStatus(401);
});

it('answers in the language asked for, Arabic by default', function () {
    $this->seed(ReferenceSeeder::class);

    $ar = $this->getJson('/api/v1/areas', apiHeaders())->assertOk()->assertHeader('Content-Language', 'ar');
    $en = $this->getJson('/api/v1/areas?locale=en', apiHeaders())->assertOk()->assertHeader('Content-Language', 'en');
    $fr = $this->getJson('/api/v1/areas?locale=fr', apiHeaders())->assertOk()->assertHeader('Content-Language', 'ar');

    expect($ar->json('data.0.name'))->toBe('القاهرة')
        ->and($en->json('data.0.name'))->toBe('Cairo')
        ->and($en->json('data.0.areas.0'))->toBe(['slug' => 'new-cairo', 'code' => 'NC', 'name' => 'New Cairo'])
        ->and($fr->json('data.0.name'))->toBe('القاهرة');
});

it('serves features, public settings and site texts', function () {
    $this->seed(ReferenceSeeder::class);

    expect($this->getJson('/api/v1/features?locale=en', apiHeaders())->json('data'))->toHaveCount(35);

    $settings = $this->getJson('/api/v1/settings', apiHeaders())->assertOk()->json('data');
    expect($settings['buyer_fee_bp'])->toBe(100)
        ->and($settings['vat_bp'])->toBe(0)
        ->and($settings['transfer_fee_payer'])->toBe('seller')
        ->and($settings)->not->toHaveKey('id_retention_days');

    $texts = $this->getJson('/api/v1/site-texts?group=seller&locale=en', apiHeaders())->assertOk()->json('data');
    expect($texts['seller.no_over.confirm'])->toBe('I understand, I list without over');

    $this->getJson('/api/v1/site-texts', apiHeaders())->assertStatus(422);
});

it('lists published units only, sellers who accept less first, then newest', function () {
    $older = Unit::factory()->published()->create(['published_at' => now()->subDays(5)]);
    $newer = Unit::factory()->published()->create(['published_at' => now()->subDay()]);
    $less = Unit::factory()->published()->create([
        'published_at' => now()->subDays(9), 'accepts_less' => true, 'lowest_accepted_amount' => 1_400_000,
    ]);
    Unit::factory()->create(['state' => UnitState::Submitted]);

    $codes = collect($this->getJson('/api/v1/units', apiHeaders())->assertOk()->json('data'))->pluck('code')->all();

    expect($codes)->toBe([$less->code, $newer->code, $older->code]);
});

it('opens a published or reserved unit by code and hides the others', function () {
    $published = Unit::factory()->published()->create();
    $draft = Unit::factory()->published()->create();
    $draft->forceFill(['state' => UnitState::Withdrawn])->save();

    $this->getJson('/api/v1/units/'.strtolower((string) $published->code), apiHeaders())->assertOk();
    $this->getJson('/api/v1/units/'.$draft->code, apiHeaders())->assertNotFound();
});

it('labels choices in the language asked for', function () {
    $unit = Unit::factory()->published()->create();

    $en = $this->getJson('/api/v1/units/'.$unit->code.'?locale=en', apiHeaders())->json('data');
    $ar = $this->getJson('/api/v1/units/'.$unit->code, apiHeaders())->json('data');

    expect($en['sub_type'])->toBe(['code' => 'apartment', 'label' => 'Apartment'])
        ->and($ar['sub_type'])->toBe(['code' => 'apartment', 'label' => 'شقة'])
        ->and($en['facade'])->toBe(['north'])
        ->and($en['money']['remaining_amount'])->toBe(4_500_000)
        ->and($en['money']['lowest_accepted_amount'])->toBeNull();
});
