<?php

use App\Enums\UnitState;
use App\Models\ActivityLog;
use App\Models\Unit;
use App\Models\User;
use App\Support\Uploads\UploadService;
use Illuminate\Support\Facades\Storage;

/*
 * Audit L8: the web now offers "delete draft" on /account/units. With the cap of open drafts, a seller who cannot
 * delete gets stuck; these pin down what the button relies on.
 */

beforeEach(function () {
    Storage::fake(UploadService::PRIVATE_DISK);
    Storage::fake(UploadService::PUBLIC_DISK);
    $this->seller = User::factory()->create();
    $this->h = sellerHeaders($this->seller);
});

it('deletes a draft that was never sent, with its files, and logs it', function () {
    $unit = completeDraft($this->seller);
    expect(Storage::disk(UploadService::PRIVATE_DISK)->allFiles())->not->toBe([]);

    $this->deleteJson("/api/v1/me/units/{$unit->public_id}", [], $this->h)->assertOk();

    expect(Unit::query()->whereKey($unit->id)->exists())->toBeFalse()
        ->and(Storage::disk(UploadService::PRIVATE_DISK)->allFiles())->toBe([])
        ->and(ActivityLog::query()->where('action', 'unit.draft_deleted')->exists())->toBeTrue();
});

it('frees a place under the cap of open drafts', function () {
    foreach (range(1, 10) as $i) {
        newDraft($this->seller);
    }
    $this->postJson('/api/v1/me/units', ['no_over' => true], $this->h)->assertStatus(422)->assertJsonValidationErrors('no_over');

    $one = Unit::query()->where('seller_id', $this->seller->id)->firstOrFail();
    $this->deleteJson("/api/v1/me/units/{$one->public_id}", [], $this->h)->assertOk();
    $this->postJson('/api/v1/me/units', ['no_over' => true], $this->h)->assertCreated();
});

it('refuses to delete a sent unit, and another seller cannot even see the draft', function () {
    $sent = completeDraft($this->seller);
    $sent->forceFill(['state' => UnitState::Submitted, 'serial' => 4321])->save();
    $this->deleteJson("/api/v1/me/units/{$sent->public_id}", [], $this->h)->assertStatus(422);

    $draft = completeDraft($this->seller);
    $other = User::factory()->create();
    $this->deleteJson("/api/v1/me/units/{$draft->public_id}", [], sellerHeaders($other))->assertNotFound();
    expect(Unit::query()->whereKey([$sent->id, $draft->id])->count())->toBe(2);
});
