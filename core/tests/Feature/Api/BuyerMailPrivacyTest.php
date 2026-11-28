<?php

use App\Enums\AdminRole as AdminRoleCode;
use App\Enums\DocumentType;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\Deal;
use App\Models\MailOutbox;
use App\Models\Unit;
use App\Models\UnitDocument;
use App\Models\UnitRequest;
use App\Models\User;
use App\Support\Listing\UnitFacts;
use App\Support\Settings\Settings;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/*
| Part 7.7 (PLAN 2.5, "and phase 7 over buyer e-mails"): a unit is filled with sentinel values in every private column,
| its seller and a document; a buyer then goes through every e-mail the platform can send them (request received, a
| deal opened, developer approval, completed, cancelled, the unit sold under another buyer's request), driven through
| the real routes. Every e-mail to a buyer, rendered in both languages with its subject, must hold none of the
| sentinels. The seller's deal e-mails must hold none of the buyer's details either (D102).
*/

const BUYER_MAIL_SENTINELS = [
    'SENTINEL-CONTRACT-8831', 'SENTINEL-UNITNO-8832', 'SENTINEL-DEVOTHER-8833', 'SENTINEL-CMPOTHER-8834', 'SENTINEL-AREAOTHER-8835',
    'SENTINEL-WREASON-8840', 'SENTINEL-SELLERNAME-8845', 'sentinel-seller-8841@example.test', '01099998842',
    'SENTINEL-DOCPATH-8843', 'SENTINEL-DOCNAME-8844', 'SENTINEL-STAFFNAME-8846',
];

const SELLER_MAIL_SENTINELS = ['SENTINEL-BUYERNAME-8851', 'sentinel-buyer-8852@example.test', '01099998853'];

beforeEach(function () {
    Mail::fake();
    $this->seed(ReferenceSeeder::class);
    foreach (AdminRoleCode::cases() as $code) {
        AdminRole::query()->firstOrCreate(['code' => $code->value], ['name_ar' => $code->value, 'name_en' => $code->value]);
    }
    $this->staff = AdminUser::factory()->create(['name' => 'SENTINEL-STAFFNAME-8846']);
});

function mailSentinelUnit(?User $seller = null): Unit
{
    $unit = Unit::factory()->published()->create([
        ...($seller !== null ? ['seller_id' => $seller->id] : []),
        'contract_number' => 'SENTINEL-CONTRACT-8831', 'unit_number' => 'SENTINEL-UNITNO-8832',
        'developer_other' => 'SENTINEL-DEVOTHER-8833', 'compound_other' => 'SENTINEL-CMPOTHER-8834', 'area_other' => 'SENTINEL-AREAOTHER-8835',
    ]);
    $unit->forceFill(['withdraw_reason' => 'SENTINEL-WREASON-8840', 'transfer_fee_bp' => 1739])->save();
    if ($seller === null) {
        $unit->seller->forceFill(['name' => 'SENTINEL-SELLERNAME-8845', 'email' => 'sentinel-seller-8841@example.test', 'phone' => '01099998842'])->save();
    }
    UnitDocument::query()->create([
        'unit_id' => $unit->id, 'type' => DocumentType::Contract, 'path' => 'private/SENTINEL-DOCPATH-8843.pdf',
        'original_name' => 'SENTINEL-DOCNAME-8844.pdf', 'mime' => 'application/pdf', 'bytes' => 10, 'sha256' => str_repeat('b', 64),
    ]);

    return $unit->refresh();
}

/** Subject and body of an outbox row, as the recipient gets it. */
function renderedOutbox(MailOutbox $row): string
{
    $templates = config('mail_templates');
    $class = is_array($templates) ? $templates[$row->template] : null;
    /** @var Mailable $mail */
    $mail = $class::fromPayload($row->payload, $row->locale)->locale($row->locale);

    return $mail->envelope()->subject."\n".$mail->render();
}

it('keeps every private field, the seller and the staff out of every e-mail a buyer can get', function () {
    $unit = mailSentinelUnit();
    $fee = UnitFacts::buyerFee($unit, app(Settings::class));
    $buyers = [];
    foreach (['ar', 'en'] as $locale) {
        $buyer = User::factory()->create(['locale' => $locale, 'email_verified_at' => now()]);
        $this->postJson('/api/v1/me/requests', ['code' => $unit->code, 'readiness' => 'now', 'fee_consent' => true, 'fee_amount' => $fee['amount'], 'question' => 'Is it still available?'], sellerHeaders($buyer))
            ->assertCreated();
        $buyers[] = $buyer;
    }
    [$first, $second] = $buyers;

    // A deal with the first buyer, taken through every stage that e-mails the parties; the second buyer's request
    // closes as "unit sold" (G12).
    $openFrom = UnitRequest::query()->where('buyer_id', $first->id)->sole();
    $this->actingAs($this->staff, 'admin')->post('/dashboard/deals', ['unit_id' => $unit->id, 'buyer_id' => $first->id, 'request_id' => $openFrom->id])->assertSessionHasNoErrors();
    $deal = Deal::query()->sole();
    foreach (range(1, 5) as $step) {
        $this->actingAs($this->staff, 'admin')->post("/dashboard/deals/{$deal->id}/forward", ['note' => 'SENTINEL-STAFFNAME-8846 note'])->assertSessionHasNoErrors();
    }
    // And a cancelled deal on another unit, for the "cancelled" e-mail.
    $other = mailSentinelUnit($unit->seller);
    $this->actingAs($this->staff, 'admin')->post('/dashboard/deals', ['unit_id' => $other->id, 'buyer_id' => $second->id])->assertSessionHasNoErrors();
    $this->actingAs($this->staff, 'admin')->post('/dashboard/deals/'.Deal::query()->latest('id')->value('id').'/cancel', ['reason' => 'SENTINEL-STAFFNAME-8846 reason'])->assertSessionHasNoErrors();

    $toBuyers = MailOutbox::query()->whereIn('to_email', [$first->email, $second->email])->get();
    expect($toBuyers->pluck('template')->unique()->sort()->values()->all())->toBe(['buyer.request_closed_sold', 'buyer.request_received', 'deal.notice'])
        ->and($toBuyers->where('template', 'deal.notice')->pluck('payload.kind')->unique()->sort()->values()->all())->toBe(['approval', 'cancelled', 'completed', 'opened']);
    foreach ($toBuyers as $row) {
        $text = renderedOutbox($row);
        foreach (BUYER_MAIL_SENTINELS as $sentinel) {
            expect($text)->not->toContain($sentinel, "{$row->template} ({$row->locale}) carries {$sentinel}");
        }
        expect(json_encode($row->payload))->not->toContain('SENTINEL');
    }

    // The seller's deal e-mails never carry the buyer's details (D102).
    $first->forceFill(['name' => 'SENTINEL-BUYERNAME-8851', 'email' => 'sentinel-buyer-8852@example.test', 'phone' => '01099998853'])->save();
    foreach (MailOutbox::query()->where('to_email', 'sentinel-seller-8841@example.test')->where('template', 'deal.notice')->get() as $row) {
        $text = renderedOutbox($row);
        foreach ([...SELLER_MAIL_SENTINELS, 'SENTINEL-STAFFNAME-8846'] as $sentinel) {
            expect($text)->not->toContain($sentinel);
        }
    }
});
