<?php

use App\Enums\UnitState;
use App\Mail\TeamFactsMail;
use App\Models\AdminUser;
use App\Models\ContactMessage;
use App\Models\MailOutbox;
use App\Models\PartnershipRequest;
use App\Models\Unit;
use App\Models\UnitRequest;
use App\Models\User;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\Mail;

/*
| Request a unit (PLAN 2.3) and the two public forms (PLAN 2.6; Michael 2026-11-22).
*/

beforeEach(function () {
    Mail::fake();
    AdminUser::factory()->create();
    $this->buyer = User::factory()->create();
    $this->h = sellerHeaders($this->buyer);
});

function ask(Unit $unit, array $headers, array $over = [])
{
    $fee = intdiv($unit->total_price * 100 + 5_000, 10_000);

    return test()->postJson('/api/v1/me/requests', [
        'code' => $unit->code, 'readiness' => 'now', 'fee_consent' => true, 'fee_amount' => $fee, ...$over,
    ], $headers);
}

it('records a request with the exact fee consent, masks contact details and tells both sides', function () {
    $unit = Unit::factory()->published()->create(['total_price' => 4_848_000]);

    ask($unit, $this->h, ['question' => 'هل السعر نهائي؟ كلمني 0100 123 4567 أو me@example.com'])->assertCreated();

    $request = UnitRequest::query()->sole();
    expect($request->fee_consent_amount)->toBe(48_480)->and($request->fee_consent_bp)->toBe(100)
        ->and($request->fee_text_version)->toMatch('/^[a-f0-9]{16}$/')
        ->and($request->question)->not->toContain('0100')->not->toContain('example.com')->toContain('[…]')
        ->and(MailOutbox::query()->where('template', 'buyer.request_received')->value('to_email'))->toBe($this->buyer->email)
        ->and(MailOutbox::query()->where('template', 'admin.unit_request')->sole()->payload['kind'])->toBe('unit_request');

    // The team notice carries the reference, never the buyer's details.
    $team = MailOutbox::query()->where('template', 'admin.unit_request')->sole();
    expect(json_encode($team->payload))->not->toContain($this->buyer->email)->not->toContain($this->buyer->name);
    $mail = TeamFactsMail::fromPayload($team->payload, 'en');
    expect($mail->kind)->toBe('unit_request')->and($mail->facts)->toHaveKeys(['reference', 'code', 'readiness']);
});

it('refuses a stale fee, an unconfirmed e-mail, an unlisted unit and a second open request on a unit', function () {
    $unit = Unit::factory()->published()->create(['total_price' => 5_000_000]);

    ask($unit, $this->h, ['fee_amount' => 40_000])->assertStatus(422)->assertJsonValidationErrors('fee_consent');
    ask($unit, $this->h, ['fee_consent' => false])->assertStatus(422)->assertJsonValidationErrors('fee_consent');

    $unconfirmed = User::factory()->unverified()->create();
    ask($unit, sellerHeaders($unconfirmed, '198.51.100.2'))->assertStatus(422)->assertJsonValidationErrors('email');

    $draft = Unit::factory()->create(['state' => UnitState::Draft, 'code' => 'LK-TS-0001']);
    ask($draft, $this->h)->assertStatus(422)->assertJsonValidationErrors('code');

    ask($unit, $this->h)->assertCreated();
    ask($unit, $this->h)->assertStatus(422)->assertJsonValidationErrors('code');
    expect(UnitRequest::query()->count())->toBe(1);
});

it('caps open requests per buyer at 5 and requires a signed-in user', function () {
    foreach (range(1, 5) as $i) {
        ask(Unit::factory()->published()->create(), $this->h)->assertCreated();
    }
    ask(Unit::factory()->published()->create(), $this->h)->assertStatus(422)->assertJsonValidationErrors('code');

    $this->postJson('/api/v1/me/requests', ['code' => 'LK-TS-1000'], apiHeaders())->assertUnauthorized();
});

it('limits requests per user per hour', function () {
    app(Settings::class)->set('rl_request_user', 2);
    $unit = Unit::factory()->published()->create();

    ask($unit, $this->h, ['readiness' => 'bad'])->assertStatus(422);
    ask($unit, $this->h, ['readiness' => 'bad'])->assertStatus(422);
    ask($unit, $this->h)->assertStatus(429);
});

it('lists only the buyer own requests, and only public unit details', function () {
    $unit = Unit::factory()->published()->create();
    ask($unit, $this->h)->assertCreated();
    $other = User::factory()->create();
    ask(Unit::factory()->published()->create(), sellerHeaders($other, '198.51.100.4'))->assertCreated();
    $unit->forceFill(['state' => UnitState::Withdrawn])->save();

    $rows = $this->getJson('/api/v1/me/requests', $this->h)->assertOk()->json('data');

    expect($rows)->toHaveCount(1)->and($rows[0]['unit'])->toBe(['code' => $unit->code, 'slug' => null, 'title' => null])
        ->and($rows[0]['reference'])->toStartWith('R-');
});

it('stores a contact message and mails it to the team, with no e-mail to the sender', function () {
    $this->postJson('/api/v1/contact', ['name' => 'منى', 'email' => 'Mona@Example.test', 'phone' => '01012345678', 'message' => 'عندي سؤال عن الرسوم والخطوات.'], apiHeaders())
        ->assertCreated();

    expect(ContactMessage::query()->sole()->email)->toBe('mona@example.test')
        ->and(MailOutbox::query()->where('template', 'admin.contact_message')->count())->toBe(1)
        ->and(MailOutbox::query()->where('to_email', 'mona@example.test')->exists())->toBeFalse();
});

it('answers a bot that fills the honeypot like a person, and stores nothing', function () {
    $this->postJson('/api/v1/contact', ['name' => 'Bot', 'email' => 'bot@example.test', 'message' => 'Buy cheap things now please.', 'website' => 'http://spam.test'], apiHeaders())
        ->assertCreated();
    $this->postJson('/api/v1/partnerships', ['company' => 'Spam', 'contact_name' => 'Bot', 'email' => 'b@example.test', 'phone' => '0223456789', 'message' => 'Partnership spam text.', 'website' => 'x'], apiHeaders())
        ->assertCreated();

    expect(ContactMessage::query()->count())->toBe(0)->and(PartnershipRequest::query()->count())->toBe(0)
        ->and(MailOutbox::query()->count())->toBe(0);
});

it('takes a partnership request with a landline, and limits the forms per IP', function () {
    $this->postJson('/api/v1/partnerships', ['company' => 'شركة تطوير', 'contact_name' => 'أحمد', 'email' => 'dev@example.test', 'phone' => '02 2345 6789', 'projects_count' => 4, 'message' => 'نود عرض مشروعاتنا على المنصة.'], apiHeaders())
        ->assertCreated();
    expect(PartnershipRequest::query()->sole()->phone)->toBe('0223456789');

    $this->postJson('/api/v1/partnerships', ['company' => 'X', 'contact_name' => 'Y', 'email' => 'not-an-email', 'phone' => '12', 'message' => 'short'], apiHeaders())
        ->assertStatus(422)->assertJsonValidationErrors(['company', 'email', 'message']);

    app(Settings::class)->set('rl_contact_ip', 1);
    $send = fn () => $this->postJson('/api/v1/contact', ['name' => 'Sara', 'email' => 's@example.test', 'message' => 'A real question here.'], [...apiHeaders(), 'X-Client-Ip' => '203.0.113.50']);
    $send()->assertCreated();
    $send()->assertStatus(429);
});
