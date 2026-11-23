<?php

use App\Models\AdminUser;
use App\Models\ContactMessage;
use App\Models\Unit;
use App\Models\UnitRequest;
use App\Models\User;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

/*
 * The audit's low findings L2, L3, L4, L6, L7 and L9, each pinned down so it cannot come back.
 */

beforeEach(function () {
    Mail::fake();
    AdminUser::factory()->create();
    $this->buyer = User::factory()->create();
    $this->h = sellerHeaders($this->buyer);
});

function requestUnit(Unit $unit, array $headers): TestResponse
{
    return test()->postJson('/api/v1/me/requests', [
        'code' => $unit->code, 'readiness' => 'now', 'fee_consent' => true,
        'fee_amount' => intdiv($unit->total_price * 100 + 5_000, 10_000),
    ], $headers);
}

it('L2 (proof P3): a seller cannot request their own unit', function () {
    $unit = Unit::factory()->published()->create(['seller_id' => $this->buyer->id]);

    requestUnit($unit, $this->h)->assertStatus(422)->assertJsonValidationErrors('code');
    expect(UnitRequest::query()->count())->toBe(0);
});

it('L3: the request reference comes from the random public id, not the row number', function () {
    $unit = Unit::factory()->published()->create();
    $reference = requestUnit($unit, $this->h)->assertCreated()->json('data.reference');
    $row = UnitRequest::query()->sole();

    expect($reference)->toBe('R-'.substr($row->public_id, -6).' · '.$unit->code)
        ->and(explode(' · ', $reference)[0])->not->toBe(sprintf('R-%04d', $row->id));
});

it('L9: the buyer row is locked while open requests are counted and the new one written', function () {
    $unit = Unit::factory()->published()->create();
    $locks = [];
    DB::listen(function ($query) use (&$locks): void {
        if (str_contains(strtolower($query->sql), 'for update')) {
            $locks[] = $query->sql;
        }
    });

    requestUnit($unit, $this->h)->assertCreated();

    expect($locks)->toHaveCount(1)->and($locks[0])->toContain('`users`');
});

it('L4: honeypots on sign-in and forgot-password', function () {
    User::factory()->create(['email' => 'mona@example.test', 'password' => 'long-enough-1']);

    $this->postJson('/api/v1/auth/login', ['email' => 'mona@example.test', 'password' => 'long-enough-1', 'website' => 'x'], apiHeaders())
        ->assertStatus(422)->assertJsonValidationErrors('website');
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'mona@example.test', 'website' => 'x'], apiHeaders())
        ->assertStatus(422)->assertJsonValidationErrors('website');
    $this->postJson('/api/v1/auth/login', ['email' => 'mona@example.test', 'password' => 'long-enough-1', 'website' => ''], apiHeaders())->assertOk();
});

it('L4: profile, favorite and budget writes have an hourly ceiling per user', function () {
    app(Settings::class)->set('rl_account_write_user', 10);
    $unit = Unit::factory()->published()->create();
    $profile = ['name' => 'Mona Adel', 'phone' => '01012345678', 'locale' => 'ar'];

    foreach (range(1, 5) as $i) {
        $this->putJson('/api/v1/me', $profile, $this->h)->assertOk();
        $this->postJson('/api/v1/me/favorites', ['code' => $unit->code], $this->h)->assertSuccessful();
    }
    $this->deleteJson("/api/v1/me/favorites/{$unit->code}", [], $this->h)->assertStatus(429)->assertHeader('Retry-After');
    $this->putJson('/api/v1/me', $profile, $this->h)->assertStatus(429);

    // Another user is not affected.
    $this->putJson('/api/v1/me', $profile, sellerHeaders(User::factory()->create()))->assertOk();
});

it('L6: a name with a line break or another control character is refused everywhere it is typed', function (string $name) {
    $this->postJson('/api/v1/auth/register', [
        'name' => $name, 'email' => 'new@example.test', 'phone' => '01012345678', 'password' => 'long-enough-1',
        'password_confirmation' => 'long-enough-1', 'terms' => true, 'website' => '',
    ], apiHeaders())->assertStatus(422)->assertJsonValidationErrors('name');
    $this->putJson('/api/v1/me', ['name' => $name, 'phone' => '01012345678', 'locale' => 'ar'], $this->h)
        ->assertStatus(422)->assertJsonValidationErrors('name');
    $this->postJson('/api/v1/contact', ['name' => $name, 'email' => 'a@example.test', 'message' => 'A message long enough.', 'website' => ''], apiHeaders())
        ->assertStatus(422)->assertJsonValidationErrors('name');
    expect(ContactMessage::query()->count())->toBe(0);
})->with([
    'CR LF (proof P10)' => ["Mona\r\nBcc: x@example.test"],
    'tab' => ["Mona\tAdel"],
    'line separator' => ["Mona\u{2028}Adel"],
]);

it('L6: ordinary Arabic and English names still pass', function () {
    $this->putJson('/api/v1/me', ['name' => 'منى عادل-Smith', 'phone' => '01012345678', 'locale' => 'ar'], $this->h)->assertOk();
});

it('L7: the API answers no cross-origin request', function () {
    $preflight = $this->call('OPTIONS', '/api/v1/areas', [], [], [], [
        'HTTP_ORIGIN' => 'https://evil.example', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'x-api-key',
    ]);
    $get = $this->getJson('/api/v1/areas', [...apiHeaders(), 'Origin' => 'https://evil.example']);

    expect($preflight->headers->has('Access-Control-Allow-Origin'))->toBeFalse()
        ->and($get->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
});
