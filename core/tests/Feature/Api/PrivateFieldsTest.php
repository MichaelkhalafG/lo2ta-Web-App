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
    'code', 'slug', 'title', 'state', 'is_verified', 'deal_tag', 'published_at', 'main_type', 'sub_type', 'developer',
    'compound', 'location', 'contract_year', 'contract_month', 'size_m2', 'garden_m2', 'roof_m2', 'floor', 'bedrooms',
    'bathrooms', 'finishing', 'delivery_status', 'delivery_year', 'delivery_month', 'facade', 'description', 'features',
    'photos', 'money', 'schedule',
];

const APPROVED_MONEY_KEYS = [
    'total_price', 'paid_amount', 'remaining_amount', 'market_price_estimate', 'installment_amount',
    'installment_frequency', 'installments_per_year', 'next_due_date', 'years_left', 'annual_extra_amount',
    'delivery_payment', 'accepts_less', 'lowest_accepted_amount', 'has_overdue', 'overdue_count', 'overdue_amount',
    'overdue_settlement', 'to_developer_for_arrears', 'maintenance_due', 'owner_from', 'paid_percent',
    'installments_left', 'buyer_fee',
];

const APPROVED_CARD_KEYS = [
    'code', 'slug', 'title', 'main_type', 'sub_type', 'size_m2', 'facade', 'place', 'delivery', 'total_price',
    'market_price_estimate', 'paid_amount', 'remaining_amount', 'paid_percent', 'owner_from', 'accepts_less',
    'installments', 'is_verified', 'deal_tag', 'is_reserved', 'cover',
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
        'transfer_fee_bp' => 1739,
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
    // A published photo: its private path must never show, only the public copy (PLAN 2.2.15).
    UnitPhoto::query()->create([
        'unit_id' => $unit->id, 'path' => 'photos/SENTINEL-PRIVPATH-7748.webp', 'public_path' => 'units/photo.webp',
        'sort' => 0, 'width' => 1200, 'height' => 800, 'bytes' => 1,
    ]);
    // A photo the admin hid (it showed a building number): it must never reach a public answer (PLAN 2.5), even if a
    // public copy were still on disk.
    UnitPhoto::query()->create([
        'unit_id' => $unit->id, 'path' => 'photos/SENTINEL-HIDDENPHOTO-7746.webp', 'public_path' => 'units/SENTINEL-HIDDENPUB-7749.webp',
        'sort' => 1, 'width' => 1200, 'height' => 800, 'bytes' => 1, 'is_hidden' => true, 'hidden_reason' => 'SENTINEL-HIDDENWHY-7747',
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

    // The seller's own routes take the unit's real public IDs: the buyer is not the owner, so they must answer 404
    // and leak nothing (Michael 2026-11-18: ownership on every endpoint).
    $ids = [
        '{code}' => (string) $unit->code,
        '{unit}' => (string) $unit->public_id,
        '{document}' => (string) $unit->documents()->value('public_id'),
        '{photo}' => (string) $unit->photos()->value('public_id'),
        '{slug}' => (string) $unit->compound?->slug,
    ];
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/') && in_array('GET', $r->methods(), true))
        ->map(fn ($r) => $r->uri())
        ->values();

    expect($routes)->not->toBeEmpty()->and($routes)->toContain('api/v1/me/favorites')->and($routes)->toContain('api/v1/me/units/{unit}/documents/{document}');

    foreach ($routes as $route) {
        $uri = strtr($route, $ids);
        $ownerOnly = str_contains($route, '{unit}');
        foreach (['ar', 'en'] as $locale) {
            $url = '/'.$uri.'?locale='.$locale.(str_contains($uri, 'site-texts') ? '&group=seller' : '');
            $response = $this->get($url, [...apiHeaders(), 'Authorization' => 'Bearer '.$token]);
            $ownerOnly ? $response->assertNotFound() : $response->assertOk();
            $body = (string) $response->getContent();

            expect($body)->not->toContain('SENTINEL')
                ->and($body)->not->toContain('sentinel-seller')
                ->and($body)->not->toContain('01099997742')
                // No document entry: the type codes are public in /choices, a document of the unit never is.
                ->and($body)->not->toContain('"type":"national_id')
                // The seller's own transfer fee (1739 bp); the general default rate in /settings is public by design.
                ->and($body)->not->toContain('1739')
                ->and($body)->not->toContain('is_hidden')
                ->and($body)->not->toContain((string) $unit->public_id);
        }
    }
});

it('answers exactly the approved card keys on the opportunities list', function () {
    sentinelUnit();

    $cards = $this->getJson('/api/v1/units', apiHeaders())->assertOk()->json('data');

    expect($cards)->toHaveCount(1)->and(array_keys($cards[0]))->toEqualCanonicalizing(APPROVED_CARD_KEYS);
});

it('lists every private column of units in Unit::PRIVATE', function () {
    foreach (['contract_number', 'unit_number', 'contract_key', 'unit_key', 'active_contract_key', 'active_unit_key', 'seller_id', 'verified_by'] as $column) {
        expect(Unit::PRIVATE)->toContain($column);
    }
});
