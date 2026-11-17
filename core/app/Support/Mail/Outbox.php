<?php

namespace App\Support\Mail;

use App\Enums\MailState;
use App\Models\MailOutbox;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

/**
 * Handover 2.5: the outbox row is written first, then the mail is sent in the request (queues run sync on shared
 * hosting). A failure leaves the row "failed" with a back-off; `outbox:send` (every minute) retries due rows.
 * A row is never lost and never sent twice by design: the scheduler only takes rows whose available_at passed,
 * and a fresh row is first available to it RETRY_GRACE_MINUTES after creation.
 */
final class Outbox
{
    public const MAX_ATTEMPTS = 6;

    public const RETRY_GRACE_MINUTES = 5;

    /**
     * @param  array<string, mixed>  $payload  plain data only: never secrets, never document paths
     */
    public function send(string $toEmail, ?string $toName, string $locale, string $template, array $payload): MailOutbox
    {
        $this->templateClass($template);
        if (! in_array($locale, ['ar', 'en'], true)) {
            throw new InvalidArgumentException("Unknown mail locale {$locale}.");
        }

        $row = MailOutbox::query()->create([
            'to_email' => $toEmail,
            'to_name' => $toName,
            'locale' => $locale,
            'template' => $template,
            'payload' => $payload,
            'state' => MailState::Pending,
            'attempts' => 0,
            'available_at' => now()->addMinutes(self::RETRY_GRACE_MINUTES),
        ]);

        $this->deliver($row);

        return $row;
    }

    /**
     * Try one send of the row and record the outcome. Returns true when sent.
     */
    public function deliver(MailOutbox $row): bool
    {
        if ($row->state === MailState::Sent || $row->state === MailState::Dead) {
            return $row->state === MailState::Sent;
        }

        $attempts = $row->attempts + 1;

        try {
            $class = $this->templateClass($row->template);
            /** @var array<string, mixed> $payload */
            $payload = $row->payload;
            $mailable = $class::fromPayload($payload, $row->locale)->locale($row->locale);

            Mail::to($row->to_email, $row->to_name)->send($mailable);

            $row->forceFill([
                'state' => MailState::Sent,
                'attempts' => $attempts,
                'sent_at' => now(),
                'last_error' => null,
            ])->save();

            return true;
        } catch (Throwable $e) {
            $dead = $attempts >= self::MAX_ATTEMPTS;
            $row->forceFill([
                'state' => $dead ? MailState::Dead : MailState::Failed,
                'attempts' => $attempts,
                'last_error' => mb_substr($e::class.': '.$e->getMessage(), 0, 2000),
                // Back-off: 5, 10, 20, 40, 80 minutes.
                'available_at' => now()->addMinutes(self::RETRY_GRACE_MINUTES * (2 ** ($attempts - 1))),
            ])->save();

            report($e);

            return false;
        }
    }

    /**
     * Send every row that is due. Used by the outbox:send command.
     *
     * @return array{sent: int, failed: int}
     */
    public function sendDue(int $limit = 50): array
    {
        $sent = 0;
        $failed = 0;

        $rows = MailOutbox::query()
            ->whereIn('state', [MailState::Pending->value, MailState::Failed->value])
            ->where('available_at', '<=', now())
            ->orderBy('available_at')
            ->limit($limit)
            ->get();

        foreach ($rows as $row) {
            $this->deliver($row) ? $sent++ : $failed++;
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * @return class-string<OutboxTemplate>
     */
    private function templateClass(string $template): string
    {
        // Template codes contain dots ("seller.received"), so look the code up as one key, not a config path.
        $templates = config('mail_templates');
        $class = is_array($templates) ? ($templates[$template] ?? null) : null;
        if (! is_string($class) || ! is_subclass_of($class, OutboxTemplate::class)) {
            throw new InvalidArgumentException("Unknown mail template {$template}.");
        }

        return $class;
    }
}
