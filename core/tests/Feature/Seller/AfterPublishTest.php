<?php

use App\Enums\DocumentType;
use App\Enums\UnitState;
use App\Models\ActivityLog;
use App\Models\AdminUser;
use App\Models\MailOutbox;
use App\Models\Unit;
use App\Models\UnitDocument;
use App\Models\UnitPhoto;
use App\Models\User;
use App\Support\Uploads\PhotoPublisher;
use App\Support\Uploads\UploadService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/*
| After publishing (PLAN 2.2.7, 2.2.8): editing tiers, withdrawal, and the national ID retention job.
*/

beforeEach(function () {
    Mail::fake();
    Storage::fake(UploadService::PRIVATE_DISK);
    Storage::fake(UploadService::PUBLIC_DISK);
    AdminUser::factory()->create();
    $this->seller = User::factory()->create();
    $this->h = sellerHeaders($this->seller);
});

function publishedUnit(User $seller, array $overrides = []): Unit
{
    $unit = completeDraft($seller, $overrides);
    $unit->forceFill(['state' => UnitState::Published, 'serial' => random_int(100, 9999), 'code' => 'LK-TS-'.random_int(1000, 9999), 'published_at' => now()])->save();
    foreach ($unit->photos()->get() as $photo) {
        app(PhotoPublisher::class)->publish($photo);
    }

    return $unit->refresh();
}

it('puts free edits live at once and flags a new description for the admin', function () {
    $unit = publishedUnit($this->seller);
    $text = str_repeat('وصف جديد للوحدة بعد النشر ', 4);

    $this->putJson("/api/v1/me/units/{$unit->public_id}/live", ['description' => $text, 'facade' => ['north', 'east']], $this->h)
        ->assertOk()->assertJsonPath('meta.live', ['description', 'facade'])->assertJsonPath('meta.pending', false);

    expect($unit->refresh()->description)->toBe(trim($text))->and($unit->facade)->toBe(3)
        ->and($unit->recheck_after_edit_at)->not->toBeNull()
        ->and(ActivityLog::query()->where('action', 'unit.live_edit')->exists())->toBeTrue();
});

it('keeps review edits pending while the unit stays live with the old values, merging later edits', function () {
    $unit = publishedUnit($this->seller);

    $this->putJson("/api/v1/me/units/{$unit->public_id}/live", ['paid_amount' => 1_600_000], $this->h)->assertOk()->assertJsonPath('meta.pending', true);
    $this->putJson("/api/v1/me/units/{$unit->public_id}/live", ['paid_amount' => 1_700_000, 'size_m2' => 150], $this->h)->assertOk();

    $pending = $unit->pendingChanges()->where('state', 'pending')->sole();
    expect($unit->refresh()->paid_amount)->toBe(1_500_000)
        ->and($pending->changes['paid_amount'])->toBe([1_500_000, 1_700_000])
        ->and($pending->changes['size_m2'])->toBe(['145.00', '150.00'])
        ->and($this->getJson('/api/v1/units/'.$unit->code, apiHeaders())->json('data.money.paid_amount'))->toBe(1_500_000);
});

it('refuses locked fields and edits that break the step checks', function () {
    $unit = publishedUnit($this->seller);

    $this->putJson("/api/v1/me/units/{$unit->public_id}/live", ['total_price' => 1, 'contract_number' => 'X'], $this->h)
        ->assertStatus(422)->assertJsonValidationErrors(['total_price', 'contract_number']);
    $this->putJson("/api/v1/me/units/{$unit->public_id}/live", ['paid_amount' => 9_000_000], $this->h)
        ->assertStatus(422)->assertJsonValidationErrors('paid_amount');
    $this->putJson("/api/v1/me/units/{$unit->public_id}/live", ['description' => 'call 01001234567 now '.str_repeat('x', 60)], $this->h)
        ->assertStatus(422)->assertJsonValidationErrors('description');

    expect($unit->pendingChanges()->count())->toBe(0);
});

it('publishes a photo added to a live unit at once and flags it for the admin', function () {
    $unit = publishedUnit($this->seller);

    $this->post("/api/v1/me/units/{$unit->public_id}/photos", ['kind' => 'photo', 'file' => upload('p.jpg', testImage('jpeg', 1200, 900))], $this->h)->assertCreated();

    expect(UnitPhoto::query()->where('unit_id', $unit->id)->whereNotNull('public_path')->count())->toBe(2)
        ->and($unit->refresh()->recheck_after_edit_at)->not->toBeNull();
});

it('withdraws a published unit: keys released, public photos removed, seller and team told', function () {
    $unit = publishedUnit($this->seller);
    $unit->forceFill(['active_contract_key' => 'k1', 'active_unit_key' => 'k2'])->save();

    $this->postJson("/api/v1/me/units/{$unit->public_id}/withdraw", ['reason' => 'sold elsewhere'], $this->h)->assertOk()->assertJsonPath('data.state.code', 'withdrawn');

    expect($unit->refresh()->state)->toBe(UnitState::Withdrawn)
        ->and($unit->active_contract_key)->toBeNull()
        ->and(Storage::disk(UploadService::PUBLIC_DISK)->allFiles())->toBe([])
        ->and(MailOutbox::query()->where('template', 'seller.unit_withdrawn')->exists())->toBeTrue()
        ->and(MailOutbox::query()->where('template', 'admin.unit_submitted')->where('payload->kind', 'withdrawn')->exists())->toBeTrue();
    $this->getJson('/api/v1/units/'.$unit->code, apiHeaders())->assertNotFound();
});

it('turns a withdrawal during an open deal into a request for the admin', function () {
    $unit = publishedUnit($this->seller);
    $unit->forceFill(['state' => UnitState::Reserved])->save();

    $this->postJson("/api/v1/me/units/{$unit->public_id}/withdraw", [], $this->h)->assertOk()->assertJsonPath('data.withdraw_requested', true);

    expect($unit->refresh()->state)->toBe(UnitState::Reserved)
        ->and(MailOutbox::query()->where('payload->kind', 'withdraw_request')->count())->toBe(1);
    $this->postJson("/api/v1/me/units/{$unit->public_id}/withdraw", [], $this->h)->assertOk();
    expect(MailOutbox::query()->where('payload->kind', 'withdraw_request')->count())->toBe(1);
});

it('purges ID copies 90 days after a unit closes and 180 days after an idle draft, keeping the record', function () {
    $closed = completeDraft($this->seller);
    $closed->forceFill(['state' => UnitState::Withdrawn, 'serial' => 501, 'withdrawn_at' => now()->subDays(91)])->save();
    $recent = completeDraft($this->seller);
    $recent->forceFill(['state' => UnitState::Withdrawn, 'serial' => 502, 'withdrawn_at' => now()->subDays(30)])->save();
    $idle = completeDraft($this->seller);
    Unit::query()->whereKey($idle->id)->update(['updated_at' => now()->subDays(181)]);
    UnitDocument::query()->where('unit_id', $idle->id)->update(['created_at' => now()->subDays(181)]);
    $active = completeDraft($this->seller);

    $closedFile = (string) $closed->documents()->where('type', 'national_id_front')->value('path');
    $this->artisan('ids:purge', ['--dry-run' => true])->assertSuccessful();
    expect(Storage::disk(UploadService::PRIVATE_DISK)->exists($closedFile))->toBeTrue();

    $this->artisan('ids:purge')->assertSuccessful();

    $purged = fn (Unit $u) => $u->documents()->whereIn('type', ['national_id_front', 'national_id_back'])->whereNull('path')->whereNotNull('purged_at')->count();
    expect($purged($closed))->toBe(2)->and($purged($idle))->toBe(2)->and($purged($recent))->toBe(0)->and($purged($active))->toBe(0)
        ->and(Storage::disk(UploadService::PRIVATE_DISK)->exists($closedFile))->toBeFalse()
        // Other documents stay (PLAN 2.2.8), and so does every row.
        ->and($closed->documents()->where('type', DocumentType::Contract->value)->whereNotNull('path')->count())->toBe(1)
        ->and(UnitDocument::query()->where('unit_id', $closed->id)->count())->toBe(4)
        ->and(ActivityLog::query()->where('action', 'document.purged')->count())->toBe(4);
});
