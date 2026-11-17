<?php

use App\Enums\MailState;
use App\Models\ActivityLog;
use App\Models\MailOutbox;
use App\Models\User;
use App\Support\Accounts\Links;
use App\Support\Accounts\Phone;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

beforeEach(fn () => Mail::fake());

/** @return array<string, mixed> */
function registration(array $overrides = []): array
{
    return [
        'name' => 'Mona Adel',
        'email' => 'Mona@Example.test',
        'phone' => '+20 10 1234 5678',
        'password' => 'long-enough-1',
        'password_confirmation' => 'long-enough-1',
        'terms' => true,
        'website' => '',
        ...$overrides,
    ];
}

it('registers with an Egyptian mobile, stores it normalised, and returns a token', function () {
    $response = $this->postJson('/api/v1/auth/register?locale=en', registration(), apiHeaders())->assertCreated();

    $user = User::query()->sole();
    expect($user->email)->toBe('mona@example.test')
        ->and($user->phone)->toBe('01012345678')
        ->and($user->locale)->toBe('en')
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($response->json('token'))->toMatch('/^\d+\|lk_[A-Za-z0-9]{40}/')
        ->and($response->json('user.email_verified'))->toBeFalse()
        ->and(ActivityLog::query()->where('action', 'user.registered')->exists())->toBeTrue();
});

it('sends the verification e-mail through the outbox, in the user language, linking to the public site', function () {
    $this->postJson('/api/v1/auth/register', registration(), apiHeaders())->assertCreated();

    $row = MailOutbox::query()->sole();
    expect($row->template)->toBe('account.verify_email')
        ->and($row->locale)->toBe('ar')
        ->and($row->state)->toBe(MailState::Sent)
        ->and($row->payload['url'])->toStartWith(config('app.frontend_url').'/verify-email?id=');
});

it('refuses a bad mobile, a taken e-mail, a missing consent and a filled honeypot', function () {
    $this->postJson('/api/v1/auth/register', registration(['phone' => '0123']), apiHeaders())->assertJsonValidationErrors('phone');
    $this->postJson('/api/v1/auth/register', registration(['terms' => false]), apiHeaders())->assertJsonValidationErrors('terms');
    $this->postJson('/api/v1/auth/register', registration(['website' => 'http://spam.test']), apiHeaders())->assertJsonValidationErrors('website');

    User::factory()->create(['email' => 'mona@example.test']);
    $this->postJson('/api/v1/auth/register', registration(), apiHeaders())->assertJsonValidationErrors('email');

    expect(User::query()->count())->toBe(1);
});

it('answers validation errors in the language asked for', function () {
    $ar = $this->postJson('/api/v1/auth/register?locale=ar', registration(['phone' => 'x']), apiHeaders());
    $en = $this->postJson('/api/v1/auth/register?locale=en', registration(['phone' => 'x']), apiHeaders());

    expect($ar->json('errors.phone.0'))->toContain('موبايل')
        ->and($en->json('errors.phone.0'))->toContain('Egyptian mobile');
});

it('normalises the Egyptian mobile formats people type', function (string $typed, ?string $stored) {
    expect(Phone::normalize($typed))->toBe($stored);
})->with([
    ['01012345678', '01012345678'],
    ['+201112345678', '01112345678'],
    ['00201212345678', '01212345678'],
    ['٠١٥١٢٣٤٥٦٧٨', '01512345678'],
    ['010-1234-5678', '01012345678'],
    ['01312345678', null],
    ['0101234567', null],
    ['0223456789', null],
]);

it('signs in, rejects a wrong password, and stops the sixth try in a minute', function () {
    User::factory()->create(['email' => 'mona@example.test', 'password' => 'long-enough-1']);
    $headers = [...apiHeaders(), 'X-Client-Ip' => '41.33.1.1'];

    $this->postJson('/api/v1/auth/login', ['email' => 'MONA@example.test', 'password' => 'long-enough-1'], $headers)
        ->assertOk()->assertJsonStructure(['token', 'user' => ['name', 'email', 'email_verified']]);

    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/auth/login', ['email' => 'mona@example.test', 'password' => 'wrong'], $headers)->assertStatus(422);
    }
    $this->postJson('/api/v1/auth/login', ['email' => 'mona@example.test', 'password' => 'long-enough-1'], $headers)->assertStatus(429);

    // Another visitor (another forwarded IP) is not blocked by the first one's mistakes.
    $this->postJson('/api/v1/auth/login', ['email' => 'mona@example.test', 'password' => 'long-enough-1'], [...apiHeaders(), 'X-Client-Ip' => '41.33.1.2'])->assertOk();
});

it('signs out by deleting the token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('web')->plainTextToken;
    $auth = [...apiHeaders(), 'Authorization' => 'Bearer '.$token];

    $this->getJson('/api/v1/me', $auth)->assertOk();
    $this->postJson('/api/v1/auth/logout', [], $auth)->assertNoContent();
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/me', $auth)->assertUnauthorized();
});

it('answers 401, not a redirect, to an API call without a user token', function () {
    $this->getJson('/api/v1/me', apiHeaders())->assertUnauthorized();
    $this->get('/api/v1/me', ['X-Api-Key' => apiHeaders()['X-Api-Key']])->assertUnauthorized();
});

it('never says whether an e-mail has an account when a reset is asked for', function () {
    User::factory()->create(['email' => 'mona@example.test']);

    $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'mona@example.test'], apiHeaders())->assertOk()->json();
    $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.test'], apiHeaders())->assertOk()->json();

    expect($known)->toBe($unknown)
        ->and(MailOutbox::query()->where('template', 'account.reset_password')->count())->toBe(1)
        ->and(MailOutbox::query()->sole()->payload['url'])->toContain('/reset-password/');
});

it('resets the password with a valid token and signs out every session', function () {
    $user = User::factory()->create(['email' => 'mona@example.test']);
    $user->createToken('web');
    $token = Password::broker()->createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'wrong', 'email' => 'mona@example.test', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1',
    ], apiHeaders())->assertJsonValidationErrors('email');

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token, 'email' => 'mona@example.test', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1',
    ], apiHeaders())->assertOk();

    $this->postJson('/api/v1/auth/login', ['email' => 'mona@example.test', 'password' => 'new-password-1'], apiHeaders())->assertOk();
    expect($user->tokens()->where('name', 'web')->count())->toBe(1);
});

it('verifies the e-mail with the signed link and refuses a tampered or expired one', function () {
    $user = User::factory()->unverified()->create();
    parse_str((string) parse_url(Links::verifyEmail($user), PHP_URL_QUERY), $q);

    $this->postJson('/api/v1/auth/verify-email', [...$q, 'signature' => str_repeat('a', 64)], apiHeaders())->assertJsonValidationErrors('link');
    $this->postJson('/api/v1/auth/verify-email', [...$q, 'id' => $user->id + 1], apiHeaders())->assertJsonValidationErrors('link');

    $this->travel(Links::VERIFY_HOURS + 1)->hours();
    $this->postJson('/api/v1/auth/verify-email', $q, apiHeaders())->assertJsonValidationErrors('link');
    $this->travelBack();

    $this->postJson('/api/v1/auth/verify-email', $q, apiHeaders())->assertOk();
    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});

it('resends the verification e-mail at most once a minute', function () {
    $user = User::factory()->unverified()->create();
    $auth = [...apiHeaders(), 'Authorization' => 'Bearer '.$user->createToken('web')->plainTextToken];

    $this->postJson('/api/v1/auth/resend-verification', [], $auth)->assertOk()->assertJson(['sent' => true]);
    $this->postJson('/api/v1/auth/resend-verification', [], $auth)->assertStatus(429);

    expect(MailOutbox::query()->where('template', 'account.verify_email')->count())->toBe(1);
});
