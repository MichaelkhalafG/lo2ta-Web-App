<?php

use App\Enums\AdminRole as AdminRoleCode;
use App\Enums\UnitState;
use App\Models\ActivityLog;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\DuplicateAttempt;
use App\Models\DuplicateOverride;
use App\Models\MailOutbox;
use App\Models\Unit;
use App\Models\User;
use App\Support\Uploads\UploadService;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| The duplicate override (Michael's answer to Q23, 2026-11-28; PLAN 2.4; D113-D115): admin only, with a written reason,
| logged with who and when; it excuses one blocked unit from the one unit it matched, and the seller is never told
| which unit that was.
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

    // The first seller's unit holds the contract; a second seller sends the same contract and is blocked.
    $firstSeller = User::factory()->create();
    $this->first = completeDraft($firstSeller, ['contract_number' => 'OVR-1']);
    $this->postJson("/api/v1/me/units/{$this->first->public_id}/submit", declarations(), sellerHeaders($firstSeller))->assertOk();
    $this->first->refresh();
    $this->seller = User::factory()->create(['locale' => 'en']);
    $this->h = sellerHeaders($this->seller);
    $this->copy = completeDraft($this->seller, ['contract_number' => 'ovr 1', 'developer_id' => $this->first->developer_id, 'compound_id' => $this->first->compound_id, 'area_id' => $this->first->area_id]);
    $this->postJson("/api/v1/me/units/{$this->copy->public_id}/submit", declarations(), $this->h)->assertStatus(422)->assertJsonValidationErrors('contract_number');
    $this->attempt = DuplicateAttempt::query()->where('unit_id', $this->copy->id)->sole();
});

/** Every place the seller could read something: the units answers and every e-mail queued to them. */
function sellerSees(string $unit, array $headers, User $seller): string
{
    $mails = MailOutbox::query()->where('to_email', $seller->email)->get()->map(fn (MailOutbox $m) => json_encode($m->payload))->implode('|');

    return test()->getJson("/api/v1/me/units/{$unit}", $headers)->getContent().'|'.test()->getJson('/api/v1/me/units', $headers)->getContent().'|'.$mails;
}

it('refuses a reviewer the override, writes nothing, and shows the reviewer no override button', function () {
    $logged = ActivityLog::query()->count();

    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/duplicates/{$this->attempt->id}/override", ['reason' => 'The seller showed the original contract'])->assertForbidden();

    expect(DuplicateOverride::query()->count())->toBe(0)->and(ActivityLog::query()->count())->toBe($logged);
    $this->postJson("/api/v1/me/units/{$this->copy->public_id}/submit", declarations(), $this->h)->assertStatus(422);
    $this->actingAs($this->reviewer, 'admin')->get('/dashboard/duplicates')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Duplicates/Index')->where('abilities.override-duplicates', false));

    // An inactive admin is refused as well.
    $this->actingAs(AdminUser::factory()->admin()->inactive()->create(), 'admin')->post("/dashboard/duplicates/{$this->attempt->id}/override", ['reason' => 'The seller showed the original contract'])->assertRedirect();
    expect(DuplicateOverride::query()->count())->toBe(0);
});

it('lets an admin override one block with a written reason, logged with who and when, and tells the seller nothing', function () {
    $url = "/dashboard/duplicates/{$this->attempt->id}/override";
    $this->actingAs($this->admin, 'admin')->post($url, [])->assertSessionHasErrors('reason');
    $this->actingAs($this->admin, 'admin')->post($url, ['reason' => 'ok'])->assertSessionHasErrors('reason');
    expect(DuplicateOverride::query()->count())->toBe(0);

    $this->actingAs($this->admin, 'admin')->post($url, ['reason' => 'The seller showed the original contract; the first listing is checked'])->assertSessionHasNoErrors();
    $override = DuplicateOverride::query()->sole();
    $line = ActivityLog::query()->where('action', 'duplicate.overridden')->sole();
    expect($override->created_by)->toBe($this->admin->id)->and($override->matched_unit_id)->toBe($this->first->id)
        ->and($line->actor_type)->toBe('admin')->and($line->actor_id)->toBe($this->admin->id)
        ->and($line->subject_id)->toBe($this->copy->id)->and($line->created_at)->not->toBeNull()
        ->and($line->reason)->toBe('The seller showed the original contract; the first listing is checked')
        ->and($line->after)->toMatchArray(['attempt' => $this->attempt->id, 'excused_unit' => $this->first->id, 'key' => 'contract']);

    // Once only.
    $this->actingAs($this->admin, 'admin')->post($url, ['reason' => 'Again'])->assertSessionHasErrors('attempt');
    expect(DuplicateOverride::query()->count())->toBe(1);

    // The seller can now send the unit; both units are active, the overridden one under its own suffix.
    $this->postJson("/api/v1/me/units/{$this->copy->public_id}/submit", declarations(), $this->h)->assertOk();
    expect($this->copy->refresh()->state)->toBe(UnitState::Submitted)
        ->and($this->copy->active_contract_key)->toBe($this->first->active_contract_key.'#u'.$this->copy->id)
        ->and($this->first->refresh()->active_contract_key)->toBe($this->copy->contract_key);

    // Nothing the seller reads names the other unit, the override or its reason.
    $seen = sellerSees($this->copy->public_id, $this->h, $this->seller);
    expect($seen)->not->toContain((string) $this->first->public_id)
        ->not->toContain(sprintf('#%04d', (int) $this->first->serial))
        ->not->toContain('first listing is checked')
        ->not->toContain('overrid');

    // Both roles see the override on the screens.
    $this->actingAs($this->reviewer, 'admin')->get('/dashboard/duplicates')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('table.data.0.override.by', $this->admin->name)->where('table.data.0.overridable', false));
    $this->actingAs($this->reviewer, 'admin')->get("/dashboard/units/{$this->copy->id}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('overrides', 1)->where('overrides.0.other_id', $this->first->id)
            ->where('history', fn ($rows): bool => collect($rows)->contains(fn ($r): bool => $r['by'] === $this->admin->name && $r['event'] !== 'duplicate.overridden' && str_contains((string) $r['note'], 'first listing is checked'))));
});

it('keeps blocking every other listing of the contract, whichever of the two units holds it', function () {
    $this->actingAs($this->admin, 'admin')->post("/dashboard/duplicates/{$this->attempt->id}/override", ['reason' => 'The seller showed the original contract'])->assertSessionHasNoErrors();
    $this->postJson("/api/v1/me/units/{$this->copy->public_id}/submit", declarations(), $this->h)->assertOk();

    // A third seller is blocked by either unit.
    $third = User::factory()->create();
    $again = completeDraft($third, ['contract_number' => 'OVR-1', 'developer_id' => $this->first->developer_id, 'compound_id' => $this->first->compound_id, 'area_id' => $this->first->area_id]);
    $this->postJson("/api/v1/me/units/{$again->public_id}/submit", declarations(), sellerHeaders($third))->assertStatus(422)->assertJsonValidationErrors('contract_number');

    // With the first unit gone from the market, the overridden unit still blocks the contract.
    $this->first->forceFill(['state' => UnitState::Withdrawn, 'active_contract_key' => null, 'active_unit_key' => null])->save();
    $this->postJson("/api/v1/me/units/{$again->public_id}/submit", declarations(), sellerHeaders($third))->assertStatus(422);
    expect(DuplicateAttempt::query()->where('unit_id', $again->id)->latest('id')->first()?->matched_unit_id)->toBe($this->copy->id)
        ->and($again->refresh()->state)->toBe(UnitState::Draft);

    // The override excuses the one matched unit only: another unit holding the contract blocks the overridden one.
    $other = completeDraft(User::factory()->create(), ['contract_number' => 'OVR-1', 'developer_id' => $this->first->developer_id, 'compound_id' => $this->first->compound_id, 'area_id' => $this->first->area_id]);
    $this->copy->refresh();
    expect($this->copy->contract_key)->toBeString();
    $other->forceFill(['state' => UnitState::Submitted, 'serial' => 900, 'active_contract_key' => $this->copy->contract_key, 'active_unit_key' => $this->copy->unit_key])->save();
    $this->copy->forceFill(['state' => UnitState::ChangesRequested])->save();
    $this->postJson("/api/v1/me/units/{$this->copy->public_id}/submit", declarations(), $this->h)->assertStatus(422)->assertJsonValidationErrors('contract_number');
});

it('overrides only a block whose unit can still be sent', function () {
    $warning = DuplicateAttempt::query()->create([
        'user_id' => $this->seller->id, 'unit_id' => $this->copy->id, 'matched_unit_id' => $this->first->id,
        'key_kind' => 'file_sha256', 'stage' => 'upload', 'blocked' => false,
    ]);
    $this->actingAs($this->admin, 'admin')->post("/dashboard/duplicates/{$warning->id}/override", ['reason' => 'Only a warning'])->assertSessionHasErrors('attempt');

    $this->copy->forceFill(['state' => UnitState::Rejected])->save();
    $this->actingAs($this->admin, 'admin')->post("/dashboard/duplicates/{$this->attempt->id}/override", ['reason' => 'Rejected meanwhile'])->assertSessionHasErrors('attempt');

    $this->actingAs($this->admin, 'admin')->post('/dashboard/duplicates/999999/override', ['reason' => 'No such attempt'])->assertNotFound();
    expect(DuplicateOverride::query()->count())->toBe(0)->and(ActivityLog::query()->where('action', 'duplicate.overridden')->count())->toBe(0);
});
