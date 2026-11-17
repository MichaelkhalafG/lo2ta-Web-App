<?php

use App\Enums\DocumentType;
use App\Models\Feature;
use App\Models\Unit;
use App\Models\UnitDocument;
use App\Models\UnitPhoto;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
| The field whitelist (PLAN 3.1 A). Two guards:
| 1. UnitResource answers exactly the approved keys: adding one is a deliberate change to this list.
| 2. A published unit is filled with sentinel values in every private column, a document and its seller; every
|    keyed GET route of the API is called, and no sentinel may appear in any answer.
*/

const APPROVED_UNIT_KEYS = [
    'code', 'slug', 'state', 'is_verified', 'deal_tag', 'published_at', 'main_type', 'sub_type', 'developer',
    'compound', 'area', 'contract_year', 'size_m2', 'garden_m2', 'roof_m2', 'floor', 'bedrooms', 'bathrooms',
    'finishing', 'delivery_status', 'delivery_year', 'facade', 'description', 'features', 'photos', 'money',
];

const APPROVED_MONEY_KEYS = [
    'total_price', 'paid_amount', 'remaining_amount', 'market_price_estimate', 'installment_amount',
    'installment_frequency', 'installments_per_year', 'next_due_date', 'years_left', 'annual_extra_amount',
    'delivery_payment', 'accepts_less', 'lowest_accepted_amount', 'has_overdue', 'overdue_count', 'overdue_amount',
    'maintenance_due',
];

function sentinelUnit(): Unit
{
    $unit = Unit::factory()->published()->create([
        'contract_number' => 'SENTINEL-CONTRACT-7731',
        'unit_number' => 'SENTINEL-UNITNO-7732',
        'developer_other' => 'SENTINEL-DEVOTHER-7733',
        'compound_other' => 'SENTINEL-CMPOTHER-7734',
        'area_other' => 'SENTINEL-AREAOTHER-7735',
    ]);
    $unit->forceFill([
        'contract_key' => 'SENTINEL-CKEY-7736',
        'unit_key' => 'SENTINEL-UKEY-7737',
        'active_contract_key' => 'SENTINEL-ACKEY-7738',
        'active_unit_key' => 'SENTINEL-AUKEY-7739',
        'withdraw_reason' => 'SENTINEL-WREASON-7740',
    ])->save();
    // PLAN 2.5: the seller's name, phone and e-mail never reach a public output.
    $unit->seller->forceFill([
        'name' => 'SENTINEL-SELLERNAME-7745',
        'email' => 'sentinel-seller-7741@example.test',
        'phone' => '01099997742',
    ])->save();

    UnitDocument::query()->create([
        'unit_id' => $unit->id,
        'type' => DocumentType::NationalIdFront,
        'path' => 'private/SENTINEL-DOCPATH-7743.jpg',
        'original_name' => 'SENTINEL-DOCNAME-7744.jpg',
        'mime' => 'image/jpeg',
        'bytes' => 1000,
        'sha256' => str_repeat('a', 64),
    ]);
    UnitPhoto::query()->create([
        'unit_id' => $unit->id, 'path' => 'units/photo.webp', 'sort' => 0, 'width' => 1200, 'height' => 800, 'bytes' => 1,
    ]);
    $unit->features()->attach(Feature::query()->create([
        'code' => 'elevator', 'group' => 'building', 'name_ar' => 'أسانسير', 'name_en' => 'Elevator',
        'applies_to' => ['residential'],
    ])->id);

    return $unit->refresh();
}

it('answers exactly the approved unit keys', function () {
    $unit = sentinelUnit();

    $data = $this->getJson('/api/v1/units/'.$unit->code, apiHeaders())->assertOk()->json('data');

    expect(array_keys($data))->toEqualCanonicalizing(APPROVED_UNIT_KEYS)
        ->and(array_keys($data['money']))->toEqualCanonicalizing(APPROVED_MONEY_KEYS);
});

it('never sends a private value from any keyed GET route', function () {
    $unit = sentinelUnit();

    // A buyer who saved the unit, so the account routes (favorites) are scanned too: a buyer must never see the
    // seller's details or the unit's private columns through their own account either.
    $buyer = User::factory()->create();
    $buyer->favorites()->attach($unit->id, ['created_at' => now()]);
    $token = $buyer->createToken('web')->plainTextToken;

    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/') && in_array('GET', $r->methods(), true))
        ->map(fn ($r) => str_replace('{code}', (string) $unit->code, $r->uri()))
        ->values();

    expect($routes)->not->toBeEmpty()->and($routes)->toContain('api/v1/me/favorites');

    foreach ($routes as $uri) {
        foreach (['ar', 'en'] as $locale) {
            $url = '/'.$uri.'?locale='.$locale.(str_contains($uri, 'site-texts') ? '&group=seller' : '');
            $body = $this->get($url, [...apiHeaders(), 'Authorization' => 'Bearer '.$token])->assertOk()->getContent();

            expect($body)->not->toContain('SENTINEL')
                ->and($body)->not->toContain('sentinel-seller')
                ->and($body)->not->toContain('01099997742')
                ->and($body)->not->toContain('national_id');
        }
    }
});

it('lists every private column of units in Unit::PRIVATE', function () {
    foreach (['contract_number', 'unit_number', 'contract_key', 'unit_key', 'active_contract_key', 'active_unit_key', 'seller_id', 'verified_by'] as $column) {
        expect(Unit::PRIVATE)->toContain($column);
    }
});
