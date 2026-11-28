<?php

use App\Enums\AdminRole as AdminRoleCode;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\UnitState;
use App\Models\ActivityLog;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\Area;
use App\Models\Compound;
use App\Models\Developer;
use App\Models\DuplicateAttempt;
use App\Models\MailOutbox;
use App\Models\Unit;
use App\Models\User;
use App\Support\Seller\ContractKeys;
use App\Support\Uploads\UploadService;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Part 7.2 (PLAN 2.2.6, 2.4, 2.7; G1-G3, G19): the units review queue and the four outcomes, verified (admin only),
| document and photo review, the team's figure corrections with a reason the seller sees, mapping "other" places,
| national ID copies per G2, and withdrawal requests. Every action is logged with who, before and after.
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
    $this->seller = User::factory()->create(['locale' => 'en']);
});

/** A unit the seller has sent: complete, with its keys held, as the submit step leaves it. */
function sentUnit(User $seller, array $overrides = []): Unit
{
    $unit = completeDraft($seller, $overrides);
    $keys = ContractKeys::of($unit);
    $unit->forceFill([
        'state' => UnitState::Submitted, 'serial' => random_int(100, 9999), 'submitted_at' => now(), 'step_reached' => 6,
        'contract_key' => $keys['contract'] ?? null, 'unit_key' => $keys['unit'] ?? null,
        'active_contract_key' => $keys['contract'] ?? null, 'active_unit_key' => $keys['unit'] ?? null,
    ])->save();

    return $unit->refresh();
}

/** The area under the unit gets a code, so the public code can be formed. */
function withAreaCode(Unit $unit): Unit
{
    $area = Area::query()->findOrFail($unit->area_id);
    if ($area->code === null && ($area->parent === null || $area->parent->code === null)) {
        $area->forceFill(['code' => 'TS'])->save();
    }

    return $unit->refresh();
}

function approveAll(Unit $unit): void
{
    $unit->documents()->update(['status' => DocumentStatus::Approved->value]);
}

it('lists sent units for both roles, never drafts, and refuses the queue to the signed out', function () {
    $sent = sentUnit($this->seller);
    $draft = completeDraft($this->seller);

    $this->get('/dashboard/units')->assertRedirect('/dashboard/login');
    $this->actingAs($this->reviewer, 'admin')->get('/dashboard/units')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Units/Index')->has('table.data', 1)->where('table.data.0.id', $sent->id));
    $this->actingAs($this->reviewer, 'admin')->get("/dashboard/units/{$draft->id}")->assertNotFound();
    $this->actingAs($this->reviewer, 'admin')->get("/dashboard/units/{$sent->id}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Units/Show')->where('unit.id', $sent->id)->where('can.verify', false)->has('documents', 4)->where('blockers', fn ($b) => count($b) > 0));
    $this->actingAs($this->reviewer, 'admin')->get('/dashboard/units?q='.urlencode($this->seller->email))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('table.data', 1));
});

it('publishes only when places are mapped and every document is approved, then forms the code and e-mails the report', function () {
    $unit = withAreaCode(sentUnit($this->seller));

    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/units/{$unit->id}/publish")->assertSessionHasErrors('publish');
    expect($unit->refresh()->state)->toBe(UnitState::Submitted);

    foreach ($unit->documents as $document) {
        $this->actingAs($this->reviewer, 'admin')->put("/dashboard/units/{$unit->id}/documents/{$document->id}", ['status' => 'approved'])->assertSessionHasNoErrors();
    }
    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/units/{$unit->id}/publish")->assertSessionHasNoErrors();

    $unit->refresh();
    expect($unit->state)->toBe(UnitState::Published)
        ->and($unit->code)->toStartWith('LK-')->and($unit->slug)->toEndWith(strtolower((string) $unit->code))
        ->and($unit->verified_at)->toBeNull()
        ->and($unit->photos()->whereNotNull('public_path')->count())->toBe(1)
        ->and($unit->exitReports()->where('kind', 'verified')->count())->toBe(2)
        ->and($unit->reviews()->sole()->outcome->value)->toBe('published');
    $mail = MailOutbox::query()->where('template', 'seller.unit_published')->sole();
    expect($mail->to_email)->toBe($this->seller->email)->and($mail->locale)->toBe('en')
        ->and($mail->payload['exit_report_id'])->toBe($unit->exitReports()->where('kind', 'verified')->where('locale', 'en')->value('id'))
        ->and(json_encode($mail->payload))->not->toContain($this->reviewer->name);
    expect(ActivityLog::query()->where('action', 'unit.published')->where('actor_id', $this->reviewer->id)->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', 'document.reviewed')->count())->toBe(4);
    // The seller downloads the report after review from My account (G19).
    $this->get("/api/v1/me/units/{$unit->public_id}/exit-report?locale=en", sellerHeaders($this->seller))->assertOk();
});

it('lets only an admin publish as verified and turn verified on or off, with a reason (G1)', function () {
    $unit = withAreaCode(sentUnit($this->seller));
    approveAll($unit);

    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/units/{$unit->id}/publish-verified")->assertForbidden();
    $this->actingAs($this->admin, 'admin')->post("/dashboard/units/{$unit->id}/publish-verified")->assertSessionHasNoErrors();
    expect($unit->refresh()->isVerified())->toBeTrue()->and($unit->verified_by)->toBe($this->admin->id)
        ->and(MailOutbox::query()->where('template', 'seller.unit_published')->sole()->payload['kind'])->toBe('published_verified');

    $this->actingAs($this->reviewer, 'admin')->put("/dashboard/units/{$unit->id}/verified", ['verified' => false, 'reason' => 'test'])->assertForbidden();
    $this->actingAs($this->admin, 'admin')->put("/dashboard/units/{$unit->id}/verified", ['verified' => false])->assertSessionHasErrors('reason');
    $this->actingAs($this->admin, 'admin')->put("/dashboard/units/{$unit->id}/verified", ['verified' => false, 'reason' => 'Receipts unclear'])->assertSessionHasNoErrors();
    expect($unit->refresh()->isVerified())->toBeFalse()
        ->and(ActivityLog::query()->where('action', 'unit.unverified')->value('reason'))->toBe('Receipts unclear');
    $this->actingAs($this->admin, 'admin')->put("/dashboard/units/{$unit->id}/verified", ['verified' => true, 'reason' => 'Inspected'])->assertSessionHasNoErrors();
    expect(MailOutbox::query()->where('template', 'seller.unit_verified')->count())->toBe(1);
    // Who verified never reaches the public answer.
    $this->getJson("/api/v1/units/{$unit->code}", apiHeaders())->assertOk()->assertJsonMissingPath('data.verified_by')->assertJsonMissingPath('data.verified_at');
});

it('sends a unit back with a reason, field notes and documents to replace, and shows them to the seller', function () {
    $unit = sentUnit($this->seller);
    $contract = $unit->documents()->where('type', DocumentType::Contract->value)->sole();

    $this->actingAs($this->reviewer, 'admin')->put("/dashboard/units/{$unit->id}/documents/{$contract->id}", ['status' => 'needs_replacement'])->assertSessionHasErrors('note');
    $this->actingAs($this->reviewer, 'admin')->put("/dashboard/units/{$unit->id}/documents/{$contract->id}", ['status' => 'needs_replacement', 'note' => 'Page 2 is cut off'])->assertSessionHasNoErrors();
    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/units/{$unit->id}/request-changes", ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/units/{$unit->id}/request-changes", [
        'reason' => 'The paid amount does not match the receipts.',
        'fields' => ['paid_amount' => 'Check the last receipt', 'not_a_field' => 'ignored'],
    ])->assertSessionHasNoErrors();

    expect($unit->refresh()->state)->toBe(UnitState::ChangesRequested)->and($unit->active_contract_key)->not->toBeNull();
    $mail = MailOutbox::query()->where('template', 'seller.unit_changes_requested')->sole();
    expect($mail->payload['fields'])->toBe([['field' => 'paid_amount', 'note' => 'Check the last receipt']])
        ->and($mail->payload['documents'])->toBe([['type' => 'contract', 'note' => 'Page 2 is cut off']]);

    $this->getJson("/api/v1/me/units/{$unit->public_id}?locale=en", sellerHeaders($this->seller))->assertOk()
        ->assertJsonPath('data.review.outcome', 'changes_requested')
        ->assertJsonPath('data.review.reason', 'The paid amount does not match the receipts.')
        ->assertJsonPath('data.review.fields.0.label', 'Paid to date')
        ->assertJsonPath('data.review.documents.0.note', 'Page 2 is cut off')
        ->assertJsonPath('data.documents.0.note', 'Page 2 is cut off');
    expect(ActivityLog::query()->where('action', 'unit.changes_requested')->value('reason'))->toBe('The paid amount does not match the receipts.');
    // Back with the seller: it cannot be published or changed by the team now.
    $this->actingAs($this->reviewer, 'admin')->put("/dashboard/units/{$unit->id}/fields", ['reason' => 'x typo', 'values' => ['bedrooms' => 2]])->assertSessionHasErrors('fields');
});

it('rejects with a reason, releases the contract keys and tells the seller', function () {
    $unit = sentUnit($this->seller);

    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/units/{$unit->id}/reject", ['reason' => 'The seller is a broker.'])->assertSessionHasNoErrors();

    $unit->refresh();
    expect($unit->state)->toBe(UnitState::Rejected)->and($unit->rejected_at)->not->toBeNull()
        ->and($unit->active_contract_key)->toBeNull()->and($unit->active_unit_key)->toBeNull()
        ->and(MailOutbox::query()->where('template', 'seller.unit_rejected')->sole()->payload['reason'])->toBe('The seller is a broker.');
    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/units/{$unit->id}/reject", ['reason' => 'Again'])->assertSessionHasErrors('unit');
});

it('lets the team correct figures with a reason, checked by the seller rules, and shows each change to the seller (G3)', function () {
    $unit = sentUnit($this->seller);

    $this->actingAs($this->reviewer, 'admin')->put("/dashboard/units/{$unit->id}/fields", ['values' => ['paid_amount' => 1_600_000]])->assertSessionHasErrors('reason');
    $this->actingAs($this->reviewer, 'admin')->put("/dashboard/units/{$unit->id}/fields", ['reason' => 'Receipts', 'values' => ['paid_amount' => 9_000_000]])->assertSessionHasErrors();
    expect($unit->refresh()->paid_amount)->toBe(1_500_000);

    $this->actingAs($this->reviewer, 'admin')->put("/dashboard/units/{$unit->id}/fields", [
        'reason' => 'Matches the last receipt', 'values' => ['paid_amount' => 1_600_000, 'contract_number' => 'X-1', 'bedrooms' => 3],
    ])->assertSessionHasNoErrors();
    expect($unit->refresh()->paid_amount)->toBe(1_600_000)->and($unit->contract_number)->not->toBe('X-1');
    $line = ActivityLog::query()->where('action', 'unit.team_edit')->sole();
    expect($line->before)->toBe(['paid_amount' => 1_500_000])->and($line->after)->toBe(['paid_amount' => 1_600_000])
        ->and($line->reason)->toBe('Matches the last receipt')->and($line->actor_id)->toBe($this->reviewer->id);

    $this->getJson("/api/v1/me/units/{$unit->public_id}?locale=en", sellerHeaders($this->seller))->assertOk()
        ->assertJsonPath('data.team_changes.0.label', 'Paid to date')
        ->assertJsonPath('data.team_changes.0.from', '1,500,000')
        ->assertJsonPath('data.team_changes.0.to', '1,600,000')
        ->assertJsonPath('data.team_changes.0.reason', 'Matches the last receipt');
});

it('opens national ID copies for a reviewer only during review, for an admin always, and logs each opening (G2)', function () {
    $unit = withAreaCode(sentUnit($this->seller));
    $id = $unit->documents()->where('type', DocumentType::NationalIdFront->value)->sole();
    $contract = $unit->documents()->where('type', DocumentType::Contract->value)->sole();

    $this->actingAs($this->reviewer, 'admin')->get("/dashboard/files/documents/{$id->public_id}")->assertOk();
    approveAll($unit);
    $this->actingAs($this->admin, 'admin')->post("/dashboard/units/{$unit->id}/publish")->assertSessionHasNoErrors();

    $this->actingAs($this->reviewer, 'admin')->get("/dashboard/files/documents/{$id->public_id}")->assertForbidden();
    $this->actingAs($this->reviewer, 'admin')->get("/dashboard/files/documents/{$contract->public_id}")->assertOk();
    $this->actingAs($this->admin, 'admin')->get("/dashboard/files/documents/{$id->public_id}")->assertOk();
    $this->actingAs($this->reviewer, 'admin')->get("/dashboard/units/{$unit->id}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('documents', fn ($docs) => collect($docs)->firstWhere('type', 'national_id_front')['url'] === null));

    expect(ActivityLog::query()->where('action', 'document.viewed')->count())->toBe(3)
        ->and(ActivityLog::query()->where('action', 'document.view_refused')->where('actor_id', $this->reviewer->id)->count())->toBe(1);
});

it('hides a photo with a reason, removes its public copy, and can show it again', function () {
    $unit = withAreaCode(sentUnit($this->seller));
    approveAll($unit);
    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/units/{$unit->id}/publish")->assertSessionHasNoErrors();
    $photo = $unit->photos()->sole();
    $public = $photo->public_path;
    Storage::disk(UploadService::PUBLIC_DISK)->assertExists($public);

    $this->actingAs($this->reviewer, 'admin')->put("/dashboard/units/{$unit->id}/photos/{$photo->id}", ['hidden' => true])->assertSessionHasErrors('reason');
    $this->actingAs($this->reviewer, 'admin')->put("/dashboard/units/{$unit->id}/photos/{$photo->id}", ['hidden' => true, 'reason' => 'Building number visible'])->assertSessionHasNoErrors();
    expect($photo->refresh()->is_hidden)->toBeTrue()->and($photo->public_path)->toBeNull()->and($photo->hidden_by)->toBe($this->reviewer->id);
    Storage::disk(UploadService::PUBLIC_DISK)->assertMissing($public);

    $this->actingAs($this->reviewer, 'admin')->put("/dashboard/units/{$unit->id}/photos/{$photo->id}", ['hidden' => false, 'project_image' => true])->assertSessionHasNoErrors();
    expect($photo->refresh()->is_hidden)->toBeFalse()->and($photo->public_path)->not->toBeNull()->and($photo->is_project_image)->toBeTrue();
    expect(ActivityLog::query()->whereIn('action', ['photo.hidden', 'photo.shown', 'photo.label_changed'])->count())->toBe(3);
});

it('maps a seller\'s other developer, compound and area to the lists, and refuses a mapping onto an active contract', function () {
    $compound = Compound::factory()->create();
    $developer = Developer::query()->findOrFail($compound->developer_id);
    $unit = sentUnit($this->seller, ['developer_id' => null, 'developer_other' => 'Some Developer', 'compound_id' => null, 'compound_other' => 'Some Compound', 'area_id' => $compound->area_id, 'contract_number' => 'MAP-1']);

    $this->actingAs($this->reviewer, 'admin')->get("/dashboard/units/{$unit->id}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('places.mappable', true)->has('choices.compounds'));
    $this->actingAs($this->reviewer, 'admin')->put("/dashboard/units/{$unit->id}/places", ['developer_id' => $developer->id, 'compound_id' => $compound->id])->assertSessionHasNoErrors();
    expect($unit->refresh()->developer_id)->toBe($developer->id)->and($unit->compound_id)->toBe($compound->id)
        ->and($unit->active_contract_key)->toBe(hash('sha256', 'd'.$developer->id.'|MAP1'))
        ->and(ActivityLog::query()->where('action', 'unit.places_mapped')->exists())->toBeTrue();

    $twin = sentUnit(User::factory()->create(), ['developer_id' => null, 'developer_other' => 'Other Co', 'compound_id' => null, 'compound_other' => 'Elsewhere', 'area_id' => $compound->area_id, 'contract_number' => 'MAP-1']);
    $this->actingAs($this->reviewer, 'admin')->put("/dashboard/units/{$twin->id}/places", ['developer_id' => $developer->id])->assertSessionHasErrors('places');
    expect($twin->refresh()->developer_id)->toBeNull();
});

it('approves or declines a withdrawal request on a reserved unit', function () {
    $unit = withAreaCode(sentUnit($this->seller));
    $unit->forceFill(['state' => UnitState::Reserved, 'code' => 'LK-TS-0001', 'slug' => 'x-lk-ts-0001', 'published_at' => now(), 'withdraw_requested_at' => now(), 'withdraw_reason' => 'Sold elsewhere'])->save();

    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/units/{$unit->id}/withdrawal", ['approve' => false])->assertSessionHasErrors('reason');
    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/units/{$unit->id}/withdrawal", ['approve' => false, 'reason' => 'The buyer signs this week'])->assertSessionHasNoErrors();
    expect($unit->refresh()->state)->toBe(UnitState::Reserved)->and($unit->withdraw_requested_at)->toBeNull()
        ->and(MailOutbox::query()->where('template', 'seller.withdraw_declined')->sole()->payload['reason'])->toBe('The buyer signs this week');

    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/units/{$unit->id}/withdrawal", ['approve' => true])->assertSessionHasErrors('unit');
    $unit->forceFill(['withdraw_requested_at' => now()])->save();
    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/units/{$unit->id}/withdrawal", ['approve' => true])->assertSessionHasNoErrors();
    expect($unit->refresh()->state)->toBe(UnitState::Withdrawn)->and($unit->active_contract_key)->toBeNull()
        ->and(ActivityLog::query()->where('action', 'unit.withdraw_approved')->exists())->toBeTrue();
});

it('sets and clears the Lokta deal tag on a public unit only, logged', function () {
    $unit = sentUnit($this->seller);
    $this->actingAs($this->reviewer, 'admin')->put("/dashboard/units/{$unit->id}/deal-tag", ['deal_tag' => true])->assertSessionHasErrors('unit');

    $unit->forceFill(['state' => UnitState::Published, 'code' => 'LK-TS-0002', 'slug' => 'x-lk-ts-0002', 'published_at' => now()])->save();
    $this->actingAs($this->reviewer, 'admin')->put("/dashboard/units/{$unit->id}/deal-tag", ['deal_tag' => true])->assertSessionHasNoErrors();
    expect($unit->refresh()->deal_tag)->toBeTrue()->and(ActivityLog::query()->where('action', 'unit.deal_tag_set')->exists())->toBeTrue();
    // After publishing, the team corrects only the market estimate here.
    $this->actingAs($this->reviewer, 'admin')->put("/dashboard/units/{$unit->id}/fields", ['reason' => 'Market moved', 'values' => ['market_price_estimate' => 8_500_000, 'paid_amount' => 1]])->assertSessionHasNoErrors();
    expect($unit->refresh()->market_price_estimate)->toBe(8_500_000)->and($unit->paid_amount)->toBe(1_500_000);
});

it('lists duplicate attempts for both roles with both units named', function () {
    $held = sentUnit($this->seller);
    $attempt = completeDraft(User::factory()->create());
    DuplicateAttempt::query()->create(['user_id' => $attempt->seller_id, 'unit_id' => $attempt->id, 'matched_unit_id' => $held->id, 'key_kind' => 'contract', 'stage' => 'step1', 'blocked' => true]);

    $this->actingAs($this->reviewer, 'admin')->get('/dashboard/duplicates')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Duplicates/Index')->has('table.data', 1)
            ->where('table.data.0.matched.id', $held->id)->where('table.data.0.unit.openable', false));
});
