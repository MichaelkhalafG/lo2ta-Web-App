<?php

use App\Enums\AdminRole as AdminRoleCode;
use App\Enums\DocumentType;
use App\Enums\UnitState;
use App\Mail\DealNoticeMail;
use App\Models\ActivityLog;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\Commission;
use App\Models\Deal;
use App\Models\MailOutbox;
use App\Models\Unit;
use App\Models\UnitRequest;
use App\Models\User;
use App\Support\Seller\ContractKeys;
use App\Support\Settings\Settings;
use App\Support\Uploads\PhotoPublisher;
use App\Support\Uploads\UploadService;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Part 7.5 (PLAN 2.4, Q16; G7, G8, G11, G12, G18): one open deal per unit, stages one at a time (or back one with a
| note), cancel with a reason, completed is final; the unit reserved from the seller's acceptance and sold at the end;
| the commission frozen at opening and worked by an admin only; e-mails to both parties; other requests closed on sale.
*/

beforeEach(function () {
    Mail::fake();
    Storage::fake(UploadService::PRIVATE_DISK);
    Storage::fake(UploadService::PUBLIC_DISK);
    $this->seed(ReferenceSeeder::class);
    foreach (AdminRoleCode::cases() as $code) {
        AdminRole::query()->firstOrCreate(['code' => $code->value], ['name_ar' => $code->value, 'name_en' => $code->value]);
    }
    $this->admin = AdminUser::factory()->admin()->create();
    $this->reviewer = AdminUser::factory()->create();
    $this->seller = User::factory()->create(['name' => 'Seller Sentinel Name', 'phone' => '01099999999']);
    $this->buyer = User::factory()->create(['locale' => 'en']);
    $unit = completeDraft($this->seller);
    $keys = ContractKeys::of($unit);
    $unit->forceFill(['state' => UnitState::Published, 'serial' => 4321, 'code' => 'LK-TS-4321', 'slug' => 'unit-lk-ts-4321', 'published_at' => now()->subDay(),
        'active_contract_key' => $keys['contract'] ?? null, 'active_unit_key' => $keys['unit'] ?? null])->save();
    foreach ($unit->photos()->get() as $photo) {
        app(PhotoPublisher::class)->publish($photo);
    }
    $this->unit = $unit->refresh();
});

function requestOn(Unit $unit, User $buyer, int $amount = 60_000): UnitRequest
{
    return UnitRequest::query()->create([
        'unit_id' => $unit->id, 'buyer_id' => $buyer->id, 'readiness' => 'now', 'fee_consent_amount' => $amount,
        'fee_consent_bp' => 100, 'fee_text_version' => 'test', 'fee_consent_at' => now(), 'state' => 'new',
    ]);
}

function openDeal(object $test, Unit $unit, User $buyer, ?UnitRequest $request = null): Deal
{
    $test->actingAs($test->reviewer, 'admin')->post('/dashboard/deals', ['unit_id' => $unit->id, 'buyer_id' => $buyer->id, 'request_id' => $request?->id])->assertSessionHasNoErrors();

    return Deal::query()->latest('id')->firstOrFail();
}

it('opens one deal per unit from a request, freezing the amount the buyer consented to (G7, G11)', function () {
    $request = requestOn($this->unit, $this->buyer, 60_000);
    app(Settings::class)->set('buyer_fee_bp', 150);

    $deal = openDeal($this, $this->unit, $this->buyer, $request);
    expect($deal->stage->value)->toBe('opened')->and($deal->open_unit_id)->toBe($this->unit->id)
        ->and($deal->commission->amount)->toBe(60_000)->and($deal->commission->rate_bp)->toBe(100)->and($deal->commission->source)->toBe('consent')
        ->and($request->refresh()->state->value)->toBe('matched');
    expect(MailOutbox::query()->where('template', 'deal.notice')->count())->toBe(2)
        ->and(ActivityLog::query()->where('action', 'deal.opened')->where('actor_id', $this->reviewer->id)->exists())->toBeTrue();

    // G11: a second buyer waits as a request.
    $other = User::factory()->create();
    $this->actingAs($this->reviewer, 'admin')->post('/dashboard/deals', ['unit_id' => $this->unit->id, 'buyer_id' => $other->id])->assertSessionHasErrors('unit');
    // The seller cannot buy their own unit.
    expect(Deal::query()->count())->toBe(1);
});

it('uses the rate of the opening day when there is no request', function () {
    app(Settings::class)->set('buyer_fee_bp', 150);
    $deal = openDeal($this, $this->unit, $this->buyer);
    expect($deal->commission->rate_bp)->toBe(150)->and($deal->commission->amount)->toBe(90_000)->and($deal->commission->source)->toBe('rate');
});

it('moves one stage at a time, back one with a note, reserves the unit at acceptance and sells it at completion (G12, G18)', function () {
    $waiting = requestOn($this->unit, $other = User::factory()->create());
    $deal = openDeal($this, $this->unit, $this->buyer);
    $go = fn (string $path, array $data = []) => $this->actingAs($this->reviewer, 'admin')->post("/dashboard/deals/{$deal->id}/{$path}", $data);

    $go('back', ['note' => 'x y z'])->assertSessionHasErrors('stage');
    $go('forward')->assertSessionHasNoErrors();
    $go('forward', ['note' => 'The seller agreed by phone'])->assertSessionHasNoErrors();
    expect($deal->refresh()->stage->value)->toBe('seller_accepted')->and($this->unit->refresh()->state)->toBe(UnitState::Reserved);
    $go('back')->assertSessionHasErrors('note');
    $go('back', ['note' => 'The seller asked for a day'])->assertSessionHasNoErrors();
    expect($deal->refresh()->stage->value)->toBe('buyer_confirmed')->and($this->unit->refresh()->state)->toBe(UnitState::Published);
    $go('forward')->assertSessionHasNoErrors();
    $go('forward')->assertSessionHasNoErrors();
    $go('forward')->assertSessionHasNoErrors();
    expect($deal->refresh()->stage->value)->toBe('signing_payment')
        ->and(MailOutbox::query()->where('template', 'deal.notice')->get()->filter(fn ($m) => $m->payload['kind'] === 'approval')->count())->toBe(2);
    $go('forward')->assertSessionHasNoErrors();

    $unit = $this->unit->refresh();
    expect($deal->refresh()->stage->value)->toBe('completed')->and($deal->open_unit_id)->toBeNull()
        ->and($unit->state)->toBe(UnitState::Sold)->and($unit->sold_at)->not->toBeNull()->and($unit->active_contract_key)->toBeNull()
        ->and($unit->photos()->whereNotNull('public_path')->count())->toBe(0)
        ->and($deal->commission->status->value)->toBe('due')
        ->and($waiting->refresh()->state->value)->toBe('closed')->and($waiting->closed_reason)->toBe('unit sold');
    expect(MailOutbox::query()->where('template', 'buyer.request_closed_sold')->where('to_email', $other->email)->count())->toBe(1);
    // Completed is final.
    $go('forward')->assertSessionHasErrors('stage');
    $go('cancel', ['reason' => 'Too late'])->assertSessionHasErrors('stage');
    expect(ActivityLog::query()->where('action', 'deal.stage_changed')->count())->toBe(7);
});

it('cancels with a reason: the unit returns to the market, the commission is waived, the request is followed up again', function () {
    $request = requestOn($this->unit, $this->buyer);
    $deal = openDeal($this, $this->unit, $this->buyer, $request);
    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/deals/{$deal->id}/forward");
    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/deals/{$deal->id}/forward");
    expect($this->unit->refresh()->state)->toBe(UnitState::Reserved);

    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/deals/{$deal->id}/cancel")->assertSessionHasErrors('reason');
    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/deals/{$deal->id}/cancel", ['reason' => 'The buyer found financing hard'])->assertSessionHasNoErrors();
    expect($deal->refresh()->stage->value)->toBe('cancelled')->and($deal->cancelled_reason)->toBe('The buyer found financing hard')
        ->and($this->unit->refresh()->state)->toBe(UnitState::Published)
        ->and($deal->commission->status->value)->toBe('waived')
        ->and($request->refresh()->state->value)->toBe('contacted')
        ->and(MailOutbox::query()->where('template', 'deal.notice')->get()->filter(fn ($m) => $m->payload['kind'] === 'cancelled')->count())->toBe(2);
    // A new deal can open now.
    openDeal($this, $this->unit, User::factory()->create());
});

it('keeps commissions to admins: status and reference only, every change logged (G8)', function () {
    $deal = openDeal($this, $this->unit, $this->buyer);
    $commission = $deal->commission;

    $this->actingAs($this->reviewer, 'admin')->get('/dashboard/commissions')->assertForbidden();
    $this->actingAs($this->reviewer, 'admin')->get("/dashboard/deals/{$deal->id}")->assertOk()->assertInertia(fn (Assert $p) => $p->component('Deals/Show')->where('commission', null));
    $this->actingAs($this->admin, 'admin')->get("/dashboard/deals/{$deal->id}")->assertInertia(fn (Assert $p) => $p->where('commission.amount', '60,000'));

    $this->actingAs($this->admin, 'admin')->put("/dashboard/commissions/{$commission->id}", ['status' => 'invoiced', 'on' => '2026-12-01', 'reference' => 'INV-1'])->assertSessionHasErrors('status');
    $commission->forceFill(['status' => 'due'])->save();
    $this->actingAs($this->admin, 'admin')->put("/dashboard/commissions/{$commission->id}", ['status' => 'invoiced'])->assertSessionHasErrors('reference');
    $this->actingAs($this->admin, 'admin')->put("/dashboard/commissions/{$commission->id}", ['status' => 'invoiced', 'on' => '2026-12-01', 'reference' => 'INV-1'])->assertSessionHasNoErrors();
    $this->actingAs($this->admin, 'admin')->put("/dashboard/commissions/{$commission->id}", ['status' => 'collected', 'on' => '2026-12-05', 'reference' => 'BANK-77'])->assertSessionHasNoErrors();
    expect($commission->refresh()->status->value)->toBe('collected')->and($commission->invoiced_on?->format('Y-m-d'))->toBe('2026-12-01')->and($commission->reference)->toBe('BANK-77');
    $this->actingAs($this->admin, 'admin')->put("/dashboard/commissions/{$commission->id}", ['status' => 'waived', 'reason' => 'x y z'])->assertSessionHasErrors('status');
    $this->actingAs($this->admin, 'admin')->get('/dashboard/commissions')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Commissions/Index')->has('table.data', 1));
    expect(ActivityLog::query()->whereIn('action', ['commission.invoiced', 'commission.collected'])->count())->toBe(2);
});

it('turns a seller\'s withdrawal into a request while any deal is open, and approving it cancels the deal (G2 included)', function () {
    $deal = openDeal($this, $this->unit, $this->buyer);
    $h = sellerHeaders($this->seller);
    $this->postJson("/api/v1/me/units/{$this->unit->public_id}/withdraw", ['reason' => 'Changed my mind'], $h)->assertOk()->assertJsonPath('data.withdraw_requested', true);
    expect($this->unit->refresh()->state)->toBe(UnitState::Published);

    // G2: a reviewer opens the ID copies while the unit is in an open deal.
    $id = $this->unit->documents()->where('type', DocumentType::NationalIdFront->value)->sole();
    $this->actingAs($this->reviewer, 'admin')->get("/dashboard/files/documents/{$id->public_id}")->assertOk();

    // Only an admin decides it (Q24, D116).
    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/units/{$this->unit->id}/withdrawal", ['approve' => true])->assertForbidden();
    expect($deal->refresh()->stage->value)->not->toBe('cancelled');
    $this->actingAs($this->admin, 'admin')->post("/dashboard/units/{$this->unit->id}/withdrawal", ['approve' => true])->assertSessionHasNoErrors();
    expect($deal->refresh()->stage->value)->toBe('cancelled')->and($this->unit->refresh()->state)->toBe(UnitState::Withdrawn);
    $this->actingAs($this->reviewer, 'admin')->get("/dashboard/files/documents/{$id->public_id}")->assertForbidden();
});

it('never puts the seller\'s details in the buyer\'s deal e-mails', function () {
    openDeal($this, $this->unit, $this->buyer);
    $mail = MailOutbox::query()->where('template', 'deal.notice')->where('to_email', $this->buyer->email)->sole();
    $html = DealNoticeMail::fromPayload($mail->payload, 'en')->locale('en')->render();

    expect($html)->toContain('LK-TS-4321')
        ->not->toContain('Seller Sentinel Name')->not->toContain($this->seller->email)->not->toContain('01099999999')
        ->not->toContain((string) $this->unit->contract_number)->not->toContain((string) $this->unit->unit_number);
});
