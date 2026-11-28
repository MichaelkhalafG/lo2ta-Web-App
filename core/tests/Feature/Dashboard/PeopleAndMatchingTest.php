<?php

use App\Enums\AdminRole as AdminRoleCode;
use App\Enums\InstallmentFrequency;
use App\Enums\UnitState;
use App\Models\ActivityLog;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\Area;
use App\Models\BuyerBudget;
use App\Models\Compound;
use App\Models\Unit;
use App\Models\UnitRequest;
use App\Models\User;
use App\Support\Matching\Matcher;
use App\Support\Uploads\PhotoPublisher;
use App\Support\Uploads\UploadService;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Part 7.4 (PLAN 2.3, 2.4; G4, G9, G10): sellers and buyers for both roles, suspension by an admin only (no sign-in,
| units held off the site, reversible, logged), buyer budgets, matching, and the request inbox.
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
});

function listedUnit(User $seller, array $overrides = []): Unit
{
    $unit = completeDraft($seller, $overrides);
    $serial = random_int(1000, 99999);
    $unit->forceFill(['state' => UnitState::Published, 'serial' => $serial, 'code' => 'LK-TS-'.$serial, 'slug' => 'unit-lk-ts-'.$serial, 'published_at' => now()->subDay()])->save();
    foreach ($unit->photos()->get() as $photo) {
        app(PhotoPublisher::class)->publish($photo);
    }

    return $unit->refresh();
}

/**
 * @param  array<string, mixed>  $values
 * @param  list<int>  $areas
 * @param  list<int>  $compounds
 */
function budgetFor(User $buyer, array $values = [], array $areas = [], array $compounds = []): BuyerBudget
{
    $budget = new BuyerBudget;
    $budget->forceFill([
        'user_id' => $buyer->id, 'cash_available' => 2_000_000, 'max_installment' => 120_000,
        'installment_frequency' => InstallmentFrequency::Quarterly, 'main_types' => ['residential'],
        'delivery_pref' => 'either', ...$values,
    ])->save();
    $budget->areas()->sync($areas);
    $budget->compounds()->sync($compounds);

    return $budget->refresh();
}

it('lets both roles see people and only an admin suspend, which blocks sign-in and holds the units off the site', function () {
    $seller = User::factory()->create(['password' => 'a-long-password-1']);
    $unit = listedUnit($seller);
    $seller->createToken('web');
    $public = $unit->photos()->value('public_path');

    $this->actingAs($this->reviewer, 'admin')->get('/dashboard/people')->assertOk()->assertInertia(fn (Assert $p) => $p->component('People/Index')->where('table.data.0.units', 1));
    $this->actingAs($this->reviewer, 'admin')->get("/dashboard/people/{$seller->id}")->assertOk()->assertInertia(fn (Assert $p) => $p->component('People/Show')->where('canSuspend', false)->has('units', 1));
    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/people/{$seller->id}/suspend", ['reason' => 'Broker'])->assertForbidden();
    $this->actingAs($this->admin, 'admin')->post("/dashboard/people/{$seller->id}/suspend")->assertSessionHasErrors('reason');
    $this->actingAs($this->admin, 'admin')->post("/dashboard/people/{$seller->id}/suspend", ['reason' => 'Acts as a broker for others'])->assertSessionHasNoErrors();

    expect($seller->refresh()->isSuspended())->toBeTrue()->and($seller->suspended_by)->toBe($this->admin->id)
        ->and($seller->tokens()->count())->toBe(0)
        ->and($unit->refresh()->held_at)->not->toBeNull()->and($unit->state)->toBe(UnitState::Published)
        ->and($unit->photos()->whereNotNull('public_path')->count())->toBe(0);
    Storage::disk(UploadService::PUBLIC_DISK)->assertMissing($public);
    $this->getJson('/api/v1/units', apiHeaders())->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/v1/units/{$unit->code}", apiHeaders())->assertNotFound();
    $this->postJson('/api/v1/auth/login', ['email' => $seller->email, 'password' => 'a-long-password-1'], apiHeaders())->assertUnprocessable()->assertJsonPath('errors.email.0', __('accounts.suspended'));
    expect(ActivityLog::query()->where('action', 'user.suspended')->value('reason'))->toBe('Acts as a broker for others')
        ->and(ActivityLog::query()->where('action', 'unit.held')->where('subject_id', $unit->id)->exists())->toBeTrue();

    $this->actingAs($this->admin, 'admin')->post("/dashboard/people/{$seller->id}/lift", ['reason' => 'Checked: the owner'])->assertSessionHasNoErrors();
    expect($seller->refresh()->isSuspended())->toBeFalse()->and($unit->refresh()->held_at)->toBeNull()
        ->and($unit->photos()->whereNotNull('public_path')->count())->toBe(1);
    $this->getJson("/api/v1/units/{$unit->code}", apiHeaders())->assertOk();
    $this->postJson('/api/v1/auth/login', ['email' => $seller->email, 'password' => 'a-long-password-1'], apiHeaders())->assertOk();
});

it('matches on cash with the 1% fee, monthly installment, type and area (districts included), and ranks by compound (G9, G10)', function () {
    $compound = Compound::factory()->create();
    $district = Area::query()->findOrFail($compound->area_id);
    $governorate = Area::query()->findOrFail($district->parent_id);
    $unit = listedUnit(User::factory()->create(), ['compound_id' => $compound->id, 'developer_id' => $compound->developer_id, 'area_id' => $district->id]);
    $matcher = app(Matcher::class);

    // Paid 1,500,000 + 1% of 6,000,000 = 1,560,000 now; 112,500 a quarter = 37,500 a month.
    expect($matcher->cashNeeded($unit))->toBe(1_560_000)->and(Matcher::monthly(112_500, InstallmentFrequency::Quarterly))->toBe(37_500);

    $best = budgetFor(User::factory()->create(['name' => 'Best Buyer']), ['cash_available' => 1_560_000, 'max_installment' => 37_500, 'installment_frequency' => InstallmentFrequency::Monthly], [$governorate->id], [$compound->id]);
    $plain = budgetFor(User::factory()->create(['name' => 'Plain Buyer']), [], [$district->id]);
    $short = budgetFor(User::factory()->create(), ['cash_available' => 1_559_999]);
    $tight = budgetFor(User::factory()->create(), ['max_installment' => 37_499, 'installment_frequency' => InstallmentFrequency::Monthly]);
    $land = budgetFor(User::factory()->create(), ['main_types' => ['land']]);
    $elsewhere = budgetFor(User::factory()->create(), [], [Area::query()->whereNotNull('parent_id')->where('id', '!=', $district->id)->where('parent_id', '!=', $district->id)->whereKeyNot($governorate->id)->value('id')]);
    budgetFor(User::factory()->create(['suspended_at' => now()]));
    budgetFor(User::query()->findOrFail($unit->seller_id));

    expect($matcher->assess($short, $unit)['failed'])->toBe(['cash'])
        ->and($matcher->assess($tight, $unit)['failed'])->toBe(['installment'])
        ->and($matcher->assess($land, $unit)['failed'])->toBe(['type'])
        ->and($matcher->assess($elsewhere, $unit)['failed'])->toBe(['area']);
    $found = $matcher->buyersFor($unit)->map(fn (array $r): int => $r['budget']->id)->all();
    expect($found[0])->toBe($best->id)->and($found)->toContain($plain->id)->and(count($found))->toBe(2);

    // G9: the lowest amount the seller accepts replaces what was paid.
    $unit->forceFill(['accepts_less' => true, 'lowest_accepted_amount' => 1_200_000])->save();
    expect($matcher->cashNeeded($unit->refresh()))->toBe(1_260_000);

    $this->actingAs($this->reviewer, 'admin')->get("/dashboard/matching?unit={$unit->id}")->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('Matching/Index')->where('mode', 'unit')->where('rows.0.title', 'Best Buyer'));
    $this->actingAs($this->reviewer, 'admin')->get("/dashboard/matching?buyer={$plain->user_id}")->assertOk()
        ->assertInertia(fn (Assert $p) => $p->where('mode', 'buyer')->where('rows.0.id', $unit->id));
    $this->actingAs($this->reviewer, 'admin')->get('/dashboard/budgets')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Budgets/Index')->has('table.data', 8));
});

it('works the request inbox: assign, note, contacted, close with a reason, every change logged', function () {
    $unit = listedUnit(User::factory()->create());
    $buyer = User::factory()->create();
    $request = UnitRequest::query()->create([
        'unit_id' => $unit->id, 'buyer_id' => $buyer->id, 'readiness' => 'now', 'fee_consent_amount' => 60_000,
        'fee_consent_bp' => 100, 'fee_text_version' => 'test', 'fee_consent_at' => now(), 'state' => 'new',
    ]);

    $this->actingAs($this->reviewer, 'admin')->get('/dashboard/requests')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Requests/Index')->has('table.data', 1));
    $this->actingAs($this->reviewer, 'admin')->get("/dashboard/requests/{$request->id}")->assertOk()->assertInertia(fn (Assert $p) => $p->component('Requests/Show')->where('request.fee', '60,000'));
    $this->actingAs($this->reviewer, 'admin')->put("/dashboard/requests/{$request->id}", ['action' => 'save', 'assigned_to' => $this->reviewer->id, 'staff_note' => 'Call after 6 pm'])->assertSessionHasNoErrors();
    expect($request->refresh()->assigned_to)->toBe($this->reviewer->id)->and($request->staff_note)->toBe('Call after 6 pm');
    $this->actingAs($this->reviewer, 'admin')->get('/dashboard/requests?filters[mine]=1')->assertInertia(fn (Assert $p) => $p->has('table.data', 1));

    $this->actingAs($this->reviewer, 'admin')->put("/dashboard/requests/{$request->id}", ['action' => 'contacted', 'assigned_to' => $this->reviewer->id, 'staff_note' => 'Call after 6 pm'])->assertSessionHasNoErrors();
    expect($request->refresh()->state->value)->toBe('contacted');
    $this->actingAs($this->reviewer, 'admin')->put("/dashboard/requests/{$request->id}", ['action' => 'close'])->assertSessionHasErrors('reason');
    $this->actingAs($this->reviewer, 'admin')->put("/dashboard/requests/{$request->id}", ['action' => 'close', 'reason' => 'Bought elsewhere'])->assertSessionHasNoErrors();
    expect($request->refresh()->state->value)->toBe('closed')->and($request->closed_reason)->toBe('Bought elsewhere');
    $this->actingAs($this->reviewer, 'admin')->put("/dashboard/requests/{$request->id}", ['action' => 'contacted'])->assertSessionHasErrors('action');

    expect(ActivityLog::query()->where('subject_type', 'unit_requests')->where('subject_id', $request->id)->pluck('action')->all())
        ->toBe(['unit_request.updated', 'unit_request.contacted', 'unit_request.close'])
        ->and(ActivityLog::query()->where('action', 'unit_request.close')->value('reason'))->toBe('Bought elsewhere');
    // The buyer's own list never shows the team's note.
    $this->getJson('/api/v1/me/requests', sellerHeaders($buyer))->assertOk()->assertDontSee('Call after 6 pm');
});
