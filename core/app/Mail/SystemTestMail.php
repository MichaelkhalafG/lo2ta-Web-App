<?php

namespace App\Mail;

use App\Support\Mail\OutboxTemplate;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A plain test mail, sent with `php artisan mail:test {email}` to prove the mailer and the outbox on a server.
 */
class SystemTestMail extends Mailable implements OutboxTemplate
{
    public function __construct(public readonly string $sentAt) {}

    public static function fromPayload(array $payload, string $locale): Mailable
    {
        $sentAt = $payload['sent_at'] ?? '';

        return new self(is_string($sentAt) ? $sentAt : '');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.system_test.subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.system-test');
    }
}
