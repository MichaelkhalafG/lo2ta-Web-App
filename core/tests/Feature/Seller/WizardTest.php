<?php

use App\Enums\DeclarationKind;
use App\Enums\SubType;
use App\Enums\UnitState;
use App\Models\AdminUser;
use App\Models\Compound;
use App\Models\Developer;
use App\Models\DuplicateAttempt;
use App\Models\MailOutbox;
use App\Models\Unit;
use App\Models\UnitDeclaration;
use App\Models\User;
use App\Support\Seller\ContractKeys;
use App\Support\Seller\StepRules;
use App\Support\Uploads\UploadService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/*
| The seller wizard's server side (PLAN 2.2): drafts, autosave, the step checks, the duplicate block, submit.
*/

beforeEach(function () {
    Mail::fake();
    Storage::fake(UploadService::PRIVATE_DISK);
    Storage::fake(UploadService::PUBLIC_DISK);
    $this->seller = User::factory()->create();
    $this->h = sellerHeaders($this->seller);
});

function step(string $unit, int $n, array $data, array $headers, bool $complete = false)
{
    return test()->putJson("/api/v1/me/units/{$unit}/steps/{$n}", [...$data, 'complete' => $complete], $headers);
}

it('opens a draft only after the no-over confirmation, stored with its text version', function () {
    $this->postJson('/api/v1/me/units', [], $this->h)->assertStatus(422);
    $id = newDraft($this->seller);

    $declaration = UnitDeclaration::query()->sole();
    expect($declaration->kind)->toBe(DeclarationKind::NoOver)
        ->and($declaration->text_version)->toMatch('/^[a-f0-9]{16}$/')
        ->and($declaration->ip)->toBe('203.0.113.10')
        ->and(Unit::query()->where('public_id', $id)->sole()->state)->toBe(UnitState::Draft);
});

it('autosaves what was typed even when the step check fails', function () {
    $id = newDraft($this->seller);

    step($id, 1, ['contract_number' => ' C-77 ', 'contract_year' => 2022], $this->h, complete: true)
        ->assertStatus(422)->assertJsonValidationErrors(['developer', 'compound', 'sub_type', 'unit_number']);

    $unit = Unit::query()->where('public_id', $id)->sole();
    expect($unit->contract_number)->toBe('C-77')->and($unit->contract_year)->toBe(2022)->and($unit->step_reached)->toBe(1);
});

it('completes step 1, takes the area from the compound, and refuses a list choice and a typed name together', function () {
    $compound = Compound::factory()->create();
    $id = newDraft($this->seller);

    step($id, 1, ['developer' => $compound->developer->slug, 'developer_other' => 'Typed too'], $this->h)->assertStatus(422);

    step($id, 1, [
        'developer' => $compound->developer->slug, 'compound' => $compound->slug, 'main_type' => 'residential',
        'sub_type' => 'apartment', 'contract_number' => 'C-1', 'unit_number' => 'B2-14', 'contract_year' => 2023, 'contract_month' => 5,
    ], $this->h, complete: true)->assertOk()->assertJsonPath('data.step_reached', 2);

    expect(Unit::query()->where('public_id', $id)->sole()->area_id)->toBe($compound->area_id);
});

it('refuses a compound of another developer and a sub-type of another main type', function () {
    $a = Compound::factory()->create();
    $b = Compound::factory()->create();
    $id = newDraft($this->seller);

    step($id, 1, [
        'developer' => $a->developer->slug, 'compound' => $b->slug, 'main_type' => 'land', 'sub_type' => 'apartment',
        'contract_number' => 'C-1', 'unit_number' => '1', 'contract_year' => 2023, 'contract_month' => 1,
    ], $this->h, complete: true)->assertStatus(422)->assertJsonValidationErrors(['compound', 'sub_type']);
});

it('blocks a contract already under review, in any spelling, without saying which unit', function () {
    $other = Unit::factory()->create(['contract_number' => 'C-00123']);
    $other->forceFill(['state' => UnitState::Submitted, 'serial' => 7])->save();
    $keys = ContractKeys::of($other);
    $other->forceFill(['active_contract_key' => $keys['contract'], 'active_unit_key' => $keys['unit']])->save();

    $id = newDraft($this->seller);
    $response = step($id, 1, [
        'developer' => $other->developer->slug, 'compound' => $other->compound->slug, 'main_type' => 'residential', 'sub_type' => 'apartment',
        'contract_number' => 'c ٠٠١٢٣', 'unit_number' => 'X-1', 'contract_year' => 2023, 'contract_month' => 1,
    ], $this->h, complete: true)->assertStatus(422)->assertJsonValidationErrors(['contract_number']);

    expect((string) $response->getContent())->not->toContain('#0007')->not->toContain((string) $other->public_id)
        ->and(DuplicateAttempt::query()->where('blocked', true)->where('key_kind', 'contract')->where('stage', 'step1')->count())->toBe(1);
});

it('clears the fields a sub-type does not have (PLAN 2.2.3)', function () {
    $unit = completeDraft($this->seller, ['sub_type' => SubType::Studio, 'bedrooms' => 3]);
    step($unit->public_id, 2, ['bedrooms' => 2, 'roof_m2' => 40], $this->h)->assertOk();
    expect($unit->refresh()->bedrooms)->toBe(0)->and($unit->roof_m2)->toBeNull();

    $land = completeDraft($this->seller, ['main_type' => 'land', 'sub_type' => SubType::ResidentialLand]);
    step($land->public_id, 2, ['floor' => '3', 'finishing' => 'furnished'], $this->h)->assertOk();
    expect($land->refresh()->floor)->toBeNull()->and($land->finishing)->toBeNull()->and($land->bedrooms)->toBeNull();

    $garden = completeDraft($this->seller, ['sub_type' => SubType::GardenApartment]);
    step($garden->public_id, 2, ['floor' => '5'], $this->h)->assertOk();
    expect($garden->refresh()->floor)->toBe('ground');
});

it('checks the money on step 3 (PLAN 2.2.2, 2.2.9, 2.2.12)', function () {
    $id = completeDraft($this->seller)->public_id;
    $base = ['total_price' => 6_000_000, 'paid_amount' => 1_500_000, 'market_price_estimate' => 8_000_000,
        'installment_amount' => 112_500, 'installment_frequency' => 'quarterly', 'next_due_date' => now()->addMonth()->toDateString(), 'years_left' => 10,
        'accepts_less' => false, 'has_overdue' => false];

    step($id, 3, [...$base, 'paid_amount' => 7_000_000], $this->h, true)->assertStatus(422)->assertJsonValidationErrors('paid_amount');
    step($id, 3, [...$base, 'installment_amount' => 5_000_000], $this->h, true)->assertStatus(422)->assertJsonValidationErrors('installment_amount');
    step($id, 3, [...$base, 'accepts_less' => true, 'lowest_accepted_amount' => 1_500_000], $this->h, true)->assertStatus(422)->assertJsonValidationErrors('lowest_accepted_amount');
    step($id, 3, [...$base, 'next_due_date' => now()->subDay()->toDateString()], $this->h, true)->assertStatus(422)->assertJsonValidationErrors('next_due_date');
    step($id, 3, [...$base, 'has_overdue' => true], $this->h, true)->assertStatus(422)->assertJsonValidationErrors(['overdue_count', 'overdue_amount', 'overdue_settlement']);

    // With arrears the due date may be past; the settlement choice is stored.
    step($id, 3, [...$base, 'next_due_date' => now()->subDay()->toDateString(), 'has_overdue' => true, 'overdue_count' => 1,
        'overdue_amount' => 112_500, 'overdue_settlement' => 'deduct'], $this->h, true)->assertOk();

    // 112,500 x 4 x 10 = 4,500,000 = remaining: no warning. 5 years left: a warning, not a block.
    step($id, 3, [...$base, 'has_overdue' => false], $this->h, true)->assertOk()->assertJsonPath('data.warnings.money', []);
    step($id, 3, [...$base, 'has_overdue' => false, 'years_left' => 5], $this->h, true)->assertOk()->assertJsonPath('data.warnings.money', ['installments_mismatch']);
});

it('treats a fully paid contract as installments finished', function () {
    $unit = completeDraft($this->seller);

    step($unit->public_id, 3, ['total_price' => 5_000_000, 'paid_amount' => 5_000_000, 'installment_amount' => 100_000], $this->h, true)->assertOk();

    expect($unit->refresh()->installment_amount)->toBeNull()->and($unit->next_due_date)->toBeNull()->and($unit->years_left)->toBe(0);
});

it('refuses phone numbers, e-mails and links in the description', function () {
    $id = completeDraft($this->seller)->public_id;
    $pad = str_repeat('شقة مميزة في موقع هادي ', 4);

    foreach (["{$pad} كلمني على 0100 123 4567", "{$pad} ٠١٢٢٣٤٥٦٧٨٩", "{$pad} mail me at owner@example.com", "{$pad} www.example.com"] as $text) {
        expect(StepRules::hasContactDetails($text))->toBeTrue();
        step($id, 5, ['description' => $text], $this->h, true)->assertStatus(422)->assertJsonValidationErrors('description');
    }
    expect(StepRules::hasContactDetails("{$pad} المساحة 145 متر والسعر 6000000 جنيه"))->toBeFalse();
    step($id, 5, ['description' => "{$pad} المساحة 145 متر"], $this->h, true)->assertOk();
});

it('refuses to submit a unit without the no-over confirmation on record', function () {
    $unit = completeDraft($this->seller);
    $unit->declarations()->delete();

    $this->postJson("/api/v1/me/units/{$unit->public_id}/submit", declarations(), $this->h)->assertStatus(422)->assertJsonValidationErrors('no_over');
});

it('needs a confirmed e-mail and every step complete to submit', function () {
    $unverified = User::factory()->unverified()->create();
    $unit = completeDraft($unverified);
    $this->postJson("/api/v1/me/units/{$unit->public_id}/submit", declarations(), sellerHeaders($unverified))->assertStatus(422)->assertJsonValidationErrors('email');

    $missing = completeDraft($this->seller);
    $missing->documents()->where('type', 'schedule')->delete();
    $this->postJson("/api/v1/me/units/{$missing->public_id}/submit", declarations(), $this->h)->assertStatus(422)->assertJsonValidationErrors('steps.4.documents.schedule');

    $this->postJson("/api/v1/me/units/{$missing->public_id}/submit", ['declarations' => ['owner_not_broker' => true]], $this->h)->assertStatus(422);
});

it('submits: a serial, the held keys, the three declarations, the seller and team e-mails', function () {
    AdminUser::factory()->create();
    $unit = completeDraft($this->seller);

    $this->postJson("/api/v1/me/units/{$unit->public_id}/submit", declarations(), $this->h)
        ->assertOk()->assertJsonPath('data.state.code', 'submitted')->assertJsonPath('data.reference', sprintf('#%04d', $unit->refresh()->serial));

    expect($unit->state)->toBe(UnitState::Submitted)
        ->and($unit->active_contract_key)->toBe($unit->contract_key)->not->toBeNull()
        ->and(UnitDeclaration::query()->where('unit_id', $unit->id)->pluck('kind')->map->value->sort()->values()->all())
        ->toBe(['data_matches', 'no_over', 'owner_not_broker', 'terms_privacy'])
        ->and(MailOutbox::query()->where('template', 'seller.unit_received')->value('to_email'))->toBe($this->seller->email)
        ->and(MailOutbox::query()->where('template', 'admin.unit_submitted')->exists())->toBeTrue();

    // Locked while under review.
    step($unit->public_id, 3, ['paid_amount' => 1], $this->h)->assertStatus(422);

    // Back with changes requested: the declarations are accepted again on resubmit, the serial stays.
    $serial = $unit->serial;
    $unit->forceFill(['state' => UnitState::ChangesRequested])->save();
    $this->postJson("/api/v1/me/units/{$unit->public_id}/submit", declarations(), $this->h)->assertOk();
    expect($unit->refresh()->serial)->toBe($serial)
        ->and(UnitDeclaration::query()->where('unit_id', $unit->id)->where('kind', 'data_matches')->count())->toBe(2);
});

it('blocks a second seller submitting the same contract', function () {
    $first = completeDraft($this->seller, ['contract_number' => 'SAME-1']);
    $this->postJson("/api/v1/me/units/{$first->public_id}/submit", declarations(), $this->h)->assertOk();

    $other = User::factory()->create();
    $copy = completeDraft($other, ['contract_number' => 'same 1', 'developer_id' => $first->developer_id, 'compound_id' => $first->compound_id, 'area_id' => $first->area_id]);
    $this->postJson("/api/v1/me/units/{$copy->public_id}/submit", declarations(), sellerHeaders($other))->assertStatus(422)->assertJsonValidationErrors('contract_number');

    expect($copy->refresh()->state)->toBe(UnitState::Draft)
        ->and(DuplicateAttempt::query()->where('stage', 'submit')->where('blocked', true)->count())->toBe(1);
});

it('deletes a never-submitted draft with its files, and nothing else', function () {
    $unit = completeDraft($this->seller);
    $files = Storage::disk(UploadService::PRIVATE_DISK)->allFiles();
    expect($files)->toHaveCount(5);

    $this->deleteJson("/api/v1/me/units/{$unit->public_id}", [], $this->h)->assertOk();

    expect(Unit::query()->whereKey($unit->id)->exists())->toBeFalse()
        ->and(Storage::disk(UploadService::PRIVATE_DISK)->allFiles())->toBe([]);

    $submitted = completeDraft($this->seller);
    $this->postJson("/api/v1/me/units/{$submitted->public_id}/submit", declarations(), $this->h)->assertOk();
    $this->deleteJson("/api/v1/me/units/{$submitted->public_id}", [], $this->h)->assertStatus(422);
});

it('never shows the seller their duplicate keys, file paths, hashes or reviewer flags', function () {
    $unit = completeDraft($this->seller);
    $this->postJson("/api/v1/me/units/{$unit->public_id}/submit", declarations(), $this->h)->assertOk();
    $unit->refresh();

    $body = (string) $this->getJson("/api/v1/me/units/{$unit->public_id}", $this->h)->getContent();

    expect($body)->not->toContain((string) $unit->contract_key)
        ->and($body)->not->toContain('documents/')
        ->and($body)->not->toContain('photos/')
        ->and($body)->not->toContain((string) $unit->documents()->value('sha256'))
        ->and($body)->not->toContain('203.0.113.10');
});

it('answers the wizard data under named step keys, never as a reindexed list', function () {
    $unit = completeDraft($this->seller, ['developer_other' => null]);

    $data = $this->getJson("/api/v1/me/units/{$unit->public_id}", $this->h)->assertOk()->json('data');

    // A resource turns an array whose first key is numeric into a list; step 1 must stay step 1.
    expect(array_keys($data['steps']))->toBe(['step1', 'step2', 'step3', 'step5'])
        ->and(array_keys($data['readiness']))->toBe(['step1', 'step2', 'step3', 'step4', 'step5'])
        ->and($data['steps']['step1'])->toHaveKeys(['developer', 'developer_other', 'contract_number'])
        ->and($data['steps']['step1']['developer_other'])->toBeNull()
        ->and($data['steps']['step3'])->toHaveKey('total_price');
});

it('the audit proof P4: typing the developer and compound instead of choosing them no longer gets round the block (M3)', function (string $developer, string $compound) {
    $listed = Unit::factory()->create(['contract_number' => 'C-00123']);
    $listed->developer->forceFill(['name_ar' => 'بالم هيلز', 'name_en' => 'Palm Hills'])->save();
    $listed->compound->forceFill(['name_ar' => 'هيلز أوف ون', 'name_en' => 'Hills of One'])->save();
    $listed->forceFill(['state' => UnitState::Published, 'serial' => 8])->save();
    $keys = ContractKeys::of($listed->refresh());
    $listed->forceFill(['active_contract_key' => $keys['contract'], 'active_unit_key' => $keys['unit']])->save();

    $id = newDraft($this->seller);
    step($id, 1, [
        'developer_other' => $developer, 'compound_other' => $compound, 'area' => $listed->area->slug,
        'main_type' => 'residential', 'sub_type' => 'apartment',
        'contract_number' => 'c-00123', 'unit_number' => 'Z-9', 'contract_year' => 2023, 'contract_month' => 1,
    ], $this->h, complete: true)->assertStatus(422)->assertJsonValidationErrors(['contract_number']);
})->with([
    'English, other case, company words' => ['palm hills developments', 'Hills Of One compound'],
    'Arabic with company words and letter variants' => ['شركة بالم هيلز للتطوير العقاري', 'هيلز اوف ون'],
]);

it('keys a typed name that matches no entry, or two entries, as typed text', function () {
    $a = Developer::factory()->create(['name_ar' => 'نيو هوم', 'name_en' => 'New Home']);
    $b = Developer::factory()->create(['name_ar' => 'نيو هوم', 'name_en' => 'New Home Group']);
    $typed = Unit::factory()->make(['developer_id' => null, 'developer_other' => 'New Home', 'contract_number' => 'X1']);
    $chosenA = Unit::factory()->make(['developer_id' => $a->id, 'contract_number' => 'X1']);
    $chosenB = Unit::factory()->make(['developer_id' => $b->id, 'contract_number' => 'X1']);

    // Two developers share the name: the key never guesses between them.
    expect(ContractKeys::of($typed)['contract'])->not->toBe(ContractKeys::of($chosenA)['contract'])
        ->and(ContractKeys::of($typed)['contract'])->not->toBe(ContractKeys::of($chosenB)['contract'])
        ->and(ContractKeys::nameKey('Palm Hills Developments'))->toBe(ContractKeys::nameKey('PALM HILLS'))
        ->and(ContractKeys::nameKey('شركة إعمار مصر'))->toBe(ContractKeys::nameKey('اعمار'))
        ->and(ContractKeys::nameKey('Group'))->toBe('GROUP');
});
