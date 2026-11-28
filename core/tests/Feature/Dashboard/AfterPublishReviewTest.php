<?php

use App\Enums\AdminRole as AdminRoleCode;
use App\Enums\DocumentType;
use App\Enums\UnitState;
use App\Models\ActivityLog;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\MailOutbox;
use App\Models\Unit;
use App\Models\User;
use App\Support\Uploads\PhotoPublisher;
use App\Support\Uploads\UploadService;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Part 7.3 (PLAN 2.2.7; G15, G17): figure changes sent after publishing are approved (live together, shown in My
| account) or rejected (the seller is e-mailed the reason); free edits go on "check after edit", where a description
| can be put back from the log; documents added after publishing are listed. Both roles; every step logged.
*/

beforeEach(function () {
    Mail::fake();
    Storage::fake(UploadService::PRIVATE_DISK);
    Storage::fake(UploadService::PUBLIC_DISK);
    $this->seed(ReferenceSeeder::class);
    foreach (AdminRoleCode::cases() as $code) {
        AdminRole::query()->firstOrCreate(['code' => $code->value], ['name_ar' => $code->value, 'name_en' => $code->value]);
    }
    $this->reviewer = AdminUser::factory()->create();
    $this->seller = User::factory()->create(['locale' => 'en']);
    $this->h = sellerHeaders($this->seller);
});

function liveUnit(User $seller): Unit
{
    $unit = completeDraft($seller);
    $unit->forceFill(['state' => UnitState::Published, 'serial' => random_int(100, 9999), 'code' => 'LK-TS-'.random_int(1000, 9999), 'slug' => 'test-'.random_int(1000, 9999), 'published_at' => now()->subDay()])->save();
    foreach ($unit->photos()->get() as $photo) {
        app(PhotoPublisher::class)->publish($photo);
    }

    return $unit->refresh();
}

it('approves a figure change: the new values go live together and the seller sees the approval', function () {
    $unit = liveUnit($this->seller);
    $this->putJson("/api/v1/me/units/{$unit->public_id}/live", ['paid_amount' => 1_700_000, 'years_left' => 9], $this->h)->assertOk()->assertJsonPath('meta.pending', true);
    $change = $unit->pendingChanges()->where('state', 'pending')->sole();

    $this->get('/dashboard/changes')->assertRedirect('/dashboard/login');
    $this->actingAs($this->reviewer, 'admin')->get('/dashboard/changes')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Changes/Index')->has('pending', 1)->where('pending.0.fields.0.from', '1,500,000')->where('pending.0.fields.0.to', '1,700,000'));
    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/changes/{$change->id}/approve")->assertSessionHasNoErrors();

    expect($unit->refresh()->paid_amount)->toBe(1_700_000)->and($unit->years_left)->toBe(9)
        ->and($change->refresh()->state)->toBe('approved')->and($change->reviewed_by)->toBe($this->reviewer->id);
    $line = ActivityLog::query()->where('action', 'unit.change_approved')->sole();
    expect($line->before)->toBe(['paid_amount' => 1_500_000, 'years_left' => 10])->and($line->actor_id)->toBe($this->reviewer->id);
    $this->getJson("/api/v1/me/units/{$unit->public_id}?locale=en", $this->h)->assertOk()
        ->assertJsonPath('data.last_change.state', 'approved')->assertJsonPath('data.pending_change', null);
    // Decided once only.
    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/changes/{$change->id}/approve")->assertSessionHasErrors('change');
    expect(MailOutbox::query()->where('template', 'seller.change_rejected')->count())->toBe(0);
});

it('rejects a figure change with a reason, keeps the live values and e-mails the seller (G15)', function () {
    $unit = liveUnit($this->seller);
    $this->putJson("/api/v1/me/units/{$unit->public_id}/live", ['paid_amount' => 1_700_000], $this->h)->assertOk();
    $change = $unit->pendingChanges()->where('state', 'pending')->sole();

    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/changes/{$change->id}/reject")->assertSessionHasErrors('reason');
    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/changes/{$change->id}/reject", ['reason' => 'No receipt for the last payment'])->assertSessionHasNoErrors();

    expect($unit->refresh()->paid_amount)->toBe(1_500_000)->and($change->refresh()->state)->toBe('rejected')->and($change->note)->toBe('No receipt for the last payment');
    $mail = MailOutbox::query()->where('template', 'seller.change_rejected')->sole();
    expect($mail->payload['reason'])->toBe('No receipt for the last payment')
        ->and($mail->payload['fields'])->toBe([['field' => 'paid_amount', 'note' => '1,500,000 → 1,700,000']])
        ->and(json_encode($mail->payload))->not->toContain($this->reviewer->name);
    $this->getJson("/api/v1/me/units/{$unit->public_id}?locale=en", $this->h)->assertOk()
        ->assertJsonPath('data.last_change.state', 'rejected')->assertJsonPath('data.last_change.note', 'No receipt for the last payment');
});

it('refuses to approve a change that no longer passes the checks against the live unit', function () {
    $unit = liveUnit($this->seller);
    $this->putJson("/api/v1/me/units/{$unit->public_id}/live", ['paid_amount' => 1_700_000], $this->h)->assertOk();
    $change = $unit->pendingChanges()->where('state', 'pending')->sole();
    $change->forceFill(['changes' => ['paid_amount' => [1_500_000, 9_000_000]]])->save();

    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/changes/{$change->id}/approve")->assertSessionHasErrors('change');
    expect($unit->refresh()->paid_amount)->toBe(1_500_000)->and($change->refresh()->state)->toBe('pending');
});

it('lists free edits to check, puts a replaced description back from the log, and marks the unit checked (G17)', function () {
    $unit = liveUnit($this->seller);
    $old = $unit->description;
    $new = str_repeat('وصف جديد بعد النشر للوحدة ', 4);
    $this->putJson("/api/v1/me/units/{$unit->public_id}/live", ['description' => $new], $this->h)->assertOk();
    $edit = ActivityLog::query()->where('action', 'unit.live_edit')->sole();

    $this->actingAs($this->reviewer, 'admin')->get('/dashboard/changes')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('recheck', 1)->where('recheck.0.edits.0.id', $edit->id)->where('recheck.0.edits.0.description_before', $old));
    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/changes/units/{$unit->id}/restore-description", ['log' => $edit->id])->assertSessionHasErrors('reason');
    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/changes/units/{$unit->id}/restore-description", ['log' => $edit->id, 'reason' => 'The new text names the building'])->assertSessionHasNoErrors();
    expect($unit->refresh()->description)->toBe($old)
        ->and(ActivityLog::query()->where('action', 'unit.description_restored')->value('reason'))->toBe('The new text names the building');

    // A log line of another unit cannot be used.
    $other = liveUnit(User::factory()->create());
    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/changes/units/{$other->id}/restore-description", ['log' => $edit->id, 'reason' => 'x y z'])->assertSessionHasErrors('description');

    $this->actingAs($this->reviewer, 'admin')->post("/dashboard/changes/units/{$unit->id}/checked")->assertSessionHasNoErrors();
    expect($unit->refresh()->recheck_after_edit_at)->toBeNull();
    $this->actingAs($this->reviewer, 'admin')->get('/dashboard/changes')->assertInertia(fn (Assert $page) => $page->has('recheck', 0));
});

it('lists documents a seller adds after publishing until the team reviews them', function () {
    $unit = liveUnit($this->seller);
    $unit->documents()->update(['status' => 'approved']);
    $path = UploadService::randomName('documents', 'pdf');
    Storage::disk(UploadService::PRIVATE_DISK)->put($path, '%PDF-1.7 test');
    $receipt = $unit->documents()->create(['type' => DocumentType::Receipt, 'path' => $path, 'original_name' => 'r.pdf', 'mime' => 'application/pdf', 'bytes' => 13, 'sha256' => hash('sha256', $path)]);

    $this->actingAs($this->reviewer, 'admin')->get('/dashboard/changes')->assertInertia(fn (Assert $page) => $page->has('documents', 1)->where('documents.0.id', $receipt->id));
    $this->actingAs($this->reviewer, 'admin')->put("/dashboard/units/{$unit->id}/documents/{$receipt->id}", ['status' => 'approved'])->assertSessionHasNoErrors();
    $this->actingAs($this->reviewer, 'admin')->get('/dashboard/changes')->assertInertia(fn (Assert $page) => $page->has('documents', 0));
});
