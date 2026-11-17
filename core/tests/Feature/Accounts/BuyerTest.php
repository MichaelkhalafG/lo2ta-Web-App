<?php

use App\Enums\UnitState;
use App\Models\AdminUser;
use App\Models\Area;
use App\Models\MailOutbox;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => Mail::fake());

/** @return array<string, string> */
function asUser(User $user): array
{
    return [...apiHeaders(), 'Authorization' => 'Bearer '.$user->createToken('web')->plainTextToken];
}

it('shows and updates my account, normalising the mobile', function () {
    $user = User::factory()->create(['name' => 'Old', 'locale' => 'ar']);
    $auth = asUser($user);

    $this->getJson('/api/v1/me', $auth)->assertOk()->assertJson(['data' => ['name' => 'Old', 'has_budget' => false]]);

    $this->putJson('/api/v1/me', ['name' => 'New Name', 'phone' => '+201512345678', 'locale' => 'en'], $auth)
        ->assertOk()->assertJson(['data' => ['name' => 'New Name', 'phone' => '01512345678', 'locale' => 'en']]);
    $this->putJson('/api/v1/me', ['name' => 'New Name', 'phone' => '123', 'locale' => 'en'], $auth)->assertJsonValidationErrors('phone');
});

it('saves a favorite only while the unit is public and marks it when it leaves the market', function () {
    $user = User::factory()->create();
    $auth = asUser($user);
    $unit = Unit::factory()->published()->create();
    $draft = Unit::factory()->create();

    $this->postJson('/api/v1/me/favorites', ['code' => strtolower((string) $unit->code)], $auth)->assertCreated();
    $this->postJson('/api/v1/me/favorites', ['code' => $unit->code], $auth)->assertCreated();
    $this->postJson('/api/v1/me/favorites', ['code' => 'LK-XX-9999'], $auth)->assertJsonValidationErrors('code');
    expect($draft->code)->toBeNull();

    $list = $this->getJson('/api/v1/me/favorites', $auth)->assertOk()->json('data');
    expect($list)->toHaveCount(1)->and($list[0]['available'])->toBeTrue()->and($list[0]['unit']['code'])->toBe($unit->code);

    $unit->forceFill(['state' => UnitState::Sold])->save();
    $list = $this->getJson('/api/v1/me/favorites', $auth)->json('data');
    expect($list[0])->toBe(['available' => false, 'unit' => ['code' => $unit->code]]);

    $this->deleteJson('/api/v1/me/favorites/'.$unit->code, [], $auth)->assertNoContent();
    expect($this->getJson('/api/v1/me/favorites', $auth)->json('data'))->toBe([]);
});

it('registers a budget once, tells the team by e-mail the first time only, and lets the buyer edit it', function () {
    $this->seed(ReferenceSeeder::class);
    AdminUser::factory()->admin()->create(['email' => 'boss@example.test']);
    AdminUser::factory()->inactive()->create(['email' => 'gone@example.test']);
    $user = User::factory()->create();
    $auth = asUser($user);

    $budget = [
        'cash_available' => 1_500_000,
        'max_installment' => 120_000,
        'installment_frequency' => 'quarterly',
        'main_types' => ['residential'],
        'areas' => ['new-cairo', 'fifth-settlement'],
        'compounds' => [],
        'target_price' => null,
        'min_bedrooms' => 2,
        'delivery_pref' => 'either',
    ];

    $this->putJson('/api/v1/me/budget', $budget, $auth)->assertCreated()
        ->assertJson(['data' => ['cash_available' => 1_500_000, 'areas' => ['new-cairo', 'fifth-settlement']]]);
    $this->putJson('/api/v1/me/budget', [...$budget, 'cash_available' => 2_000_000], $auth)->assertOk();

    expect($this->getJson('/api/v1/me/budget', $auth)->json('data.cash_available'))->toBe(2_000_000)
        ->and(MailOutbox::query()->where('template', 'admin.new_budget')->pluck('to_email')->all())->toBe(['boss@example.test']);
});

it('validates the budget against the managed lists and the limits', function () {
    $this->seed(ReferenceSeeder::class);
    $auth = asUser(User::factory()->create());

    $this->putJson('/api/v1/me/budget', [
        'cash_available' => -1,
        'max_installment' => 200_000_000,
        'installment_frequency' => 'weekly',
        'main_types' => ['castle'],
        'areas' => ['atlantis'],
        'delivery_pref' => 'never',
    ], $auth)->assertJsonValidationErrors([
        'cash_available', 'max_installment', 'installment_frequency', 'main_types.0', 'areas.0', 'delivery_pref',
    ]);

    $this->putJson('/api/v1/me/budget', [
        'cash_available' => 1, 'max_installment' => 1, 'installment_frequency' => 'monthly', 'main_types' => ['land'],
        'areas' => array_slice(Area::query()->pluck('slug')->all(), 0, 11), 'delivery_pref' => 'ready',
    ], $auth)->assertJsonValidationErrors('areas');
});

it('serves the choice lists in the language asked for', function () {
    $en = $this->getJson('/api/v1/choices?locale=en', apiHeaders())->assertOk()->json('data');
    $ar = $this->getJson('/api/v1/choices', apiHeaders())->json('data');

    expect($en['main_types'][0])->toBe(['code' => 'residential', 'label' => 'Residential'])
        ->and($ar['delivery_preferences'][2])->toBe(['code' => 'either', 'label' => 'أي حالة']);
});
