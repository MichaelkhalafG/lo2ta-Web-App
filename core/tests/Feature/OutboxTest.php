<?php

use App\Enums\MailState;
use App\Mail\SystemTestMail;
use App\Models\MailOutbox;
use App\Support\Mail\Outbox;
use App\Support\Mail\OutboxTemplate;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/** A template that always throws, to prove the retry path. */
class BrokenTestMail extends Mailable implements OutboxTemplate
{
    public static function fromPayload(array $payload, string $locale): Mailable
    {
        throw new RuntimeException('smtp down');
    }
}

it('writes the row first, then sends in the request', function () {
    Mail::fake();

    $row = app(Outbox::class)->send('buyer@example.test', 'Buyer', 'en', 'system.test', ['sent_at' => '2026-11-17 16:30']);

    expect($row->refresh()->state)->toBe(MailState::Sent)
        ->and($row->attempts)->toBe(1)
        ->and($row->sent_at)->not->toBeNull();
    Mail::assertSent(SystemTestMail::class, fn (SystemTestMail $m) => $m->hasTo('buyer@example.test') && $m->locale === 'en');
});

it('keeps a failed mail, backs off, retries from the scheduler and gives up after six tries', function () {
    config(['mail_templates' => [...config('mail_templates'), 'test.broken' => BrokenTestMail::class]]);
    $outbox = app(Outbox::class);

    $row = $outbox->send('seller@example.test', null, 'ar', 'test.broken', []);
    expect($row->refresh()->state)->toBe(MailState::Failed)
        ->and($row->attempts)->toBe(1)
        ->and($row->last_error)->toContain('smtp down')
        ->and($row->available_at->isFuture())->toBeTrue();

    // Not due yet: the scheduler leaves it alone.
    expect($outbox->sendDue())->toBe(['sent' => 0, 'failed' => 0]);

    foreach (range(2, Outbox::MAX_ATTEMPTS) as $attempt) {
        $this->travel(3)->hours();
        $this->artisan('outbox:send')->assertSuccessful();
        expect($row->refresh()->attempts)->toBe($attempt);
    }

    expect($row->state)->toBe(MailState::Dead);
    $this->travel(5)->hours();
    expect($outbox->sendDue())->toBe(['sent' => 0, 'failed' => 0]);
});

it('refuses an unknown template or language before writing anything', function () {
    expect(fn () => app(Outbox::class)->send('a@example.test', null, 'ar', 'nope', []))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(Outbox::class)->send('a@example.test', null, 'fr', 'system.test', []))->toThrow(InvalidArgumentException::class)
        ->and(MailOutbox::query()->count())->toBe(0);
});

it('sends a test mail with mail:test', function () {
    Mail::fake();

    $this->artisan('mail:test', ['email' => 'me@example.test'])->expectsOutputToContain('SENT')->assertSuccessful();
    $this->artisan('mail:test', ['email' => 'not-an-email'])->assertFailed();
});

it('runs outbox:send every minute from the scheduler', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($e) => str_contains((string) $e->command, 'outbox:send'));

    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('* * * * *');
});
