<?php

use App\Enums\OverdueSettlement;
use App\Models\AdminUser;
use App\Models\Area;
use App\Models\Compound;
use App\Models\MailOutbox;
use App\Models\Unit;
use App\Models\UnitPhoto;
use App\Models\User;
use App\Support\Mail\TeamNotice;
use App\Support\Settings\Settings;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Support\Facades\Mail;

/*
| Phase 4 data (PLAN 2.2.12, 2.2.13, 2.5): months, overdue settlement, hidden photos, the location chain, and the
| team mailbox setting (Michael 2026-11-18).
*/

it('shows governorate, area and district for a unit placed in a district', function () {
    $this->seed(ReferenceSeeder::class);
    $district = Area::query()->where('slug', 'fifth-settlement')->sole();
    $unit = Unit::factory()->published()->create(['area_id' => $district->id, 'contract_month' => 3, 'delivery_month' => 12]);

    $data = $this->getJson('/api/v1/units/'.$unit->code.'?locale=en', apiHeaders())->assertOk()->json('data');

    expect($data['location'])->toBe([
        'governorate' => ['slug' => 'cairo', 'name' => 'Cairo'],
        'area' => ['slug' => 'new-cairo', 'name' => 'New Cairo'],
        'district' => ['slug' => 'fifth-settlement', 'name' => 'Fifth Settlement'],
    ])->and($data['contract_month'])->toBe(3)->and($data['delivery_month'])->toBe(12);
});

it('shows a unit placed at area level with no district', function () {
    $this->seed(ReferenceSeeder::class);
    $area = Area::query()->where('slug', 'madinaty')->sole();
    $unit = Unit::factory()->published()->create(['area_id' => $area->id]);

    $data = $this->getJson('/api/v1/units/'.$unit->code, apiHeaders())->json('data.location');

    expect($data['governorate']['slug'])->toBe('cairo')
        ->and($data['area']['slug'])->toBe('madinaty')
        ->and($data['district'])->toBeNull();
});

it('shows the arrears paid to the developer only when they are deducted', function () {
    $deduct = Unit::factory()->published()->create([
        'has_overdue' => true, 'overdue_count' => 1, 'overdue_amount' => 126_000, 'overdue_settlement' => OverdueSettlement::Deducted,
    ]);
    $self = Unit::factory()->published()->create([
        'has_overdue' => true, 'overdue_count' => 1, 'overdue_amount' => 126_000, 'overdue_settlement' => OverdueSettlement::SellerPaysFirst,
    ]);
    $none = Unit::factory()->published()->create();

    $money = fn (Unit $u) => $this->getJson('/api/v1/units/'.$u->code.'?locale=en', apiHeaders())->json('data.money');

    expect($money($deduct)['to_developer_for_arrears'])->toBe(126_000)
        ->and($money($deduct)['overdue_settlement']['code'])->toBe('deduct')
        ->and($money($self)['to_developer_for_arrears'])->toBeNull()
        ->and($money($none)['overdue_settlement'])->toBeNull();
});

it('never shows a photo the admin hid, nor one without a public copy', function () {
    $unit = Unit::factory()->published()->create();
    $photo = ['unit_id' => $unit->id, 'width' => 10, 'height' => 10, 'bytes' => 1];
    UnitPhoto::query()->create([...$photo, 'path' => 'photos/a.webp', 'public_path' => 'units/a.webp', 'sort' => 0]);
    UnitPhoto::query()->create([...$photo, 'path' => 'photos/b.webp', 'public_path' => 'units/b.webp', 'sort' => 1, 'is_hidden' => true]);
    UnitPhoto::query()->create([...$photo, 'path' => 'photos/c.webp', 'sort' => 2]);

    $photos = $this->getJson('/api/v1/units/'.$unit->code, apiHeaders())->json('data.photos');

    expect($photos)->toHaveCount(1)->and($photos[0]['src'])->toEndWith('units/a.webp');
});

it('keeps the seller transfer fee private and on the unit', function () {
    $unit = Unit::factory()->published()->create(['transfer_fee_bp' => 250]);

    expect($unit->refresh()->transfer_fee_bp)->toBe(250)
        ->and(Unit::PRIVATE)->toContain('transfer_fee_bp');
});

it('sends team notices to the team mailbox once set, else to every active admin', function () {
    Mail::fake();
    app(Settings::class)->seedDefaults();
    AdminUser::factory()->admin()->create(['email' => 'one@example.test']);
    AdminUser::factory()->create(['email' => 'two@example.test']);
    AdminUser::factory()->inactive()->create(['email' => 'off@example.test']);
    $notice = app(TeamNotice::class);
    $payload = ['buyer' => 'B', 'cash' => '1', 'installment' => '1', 'verified' => '✓'];

    expect($notice->send('admin.new_budget', $payload))->toBe(2);

    app(Settings::class)->set('team_email', 'info@loktaeg.com');
    expect($notice->send('admin.new_budget', $payload))->toBe(1)
        ->and(MailOutbox::query()->latest('id')->value('to_email'))->toBe('info@loktaeg.com');

    expect(fn () => app(Settings::class)->set('team_email', 'not-an-address'))->toThrow(InvalidArgumentException::class);
});

it('rolls a first budget notice through the team mailbox', function () {
    Mail::fake();
    $this->seed(ReferenceSeeder::class);
    app(Settings::class)->set('team_email', 'info@loktaeg.com');
    $user = User::factory()->create();
    $auth = [...apiHeaders(), 'Authorization' => 'Bearer '.$user->createToken('web')->plainTextToken];

    $this->putJson('/api/v1/me/budget', [
        'cash_available' => 1, 'max_installment' => 1, 'installment_frequency' => 'monthly', 'main_types' => ['land'],
        'areas' => ['new-cairo'], 'delivery_pref' => 'ready',
    ], $auth)->assertCreated();

    expect(MailOutbox::query()->where('template', 'admin.new_budget')->pluck('to_email')->all())->toBe(['info@loktaeg.com']);
    expect(Compound::query()->count())->toBe(0);
});
