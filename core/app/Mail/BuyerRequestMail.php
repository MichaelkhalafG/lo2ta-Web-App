<?php

namespace App\Mail;

use App\Support\Mail\OutboxTemplate;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * To the buyer after "Request this unit" (PLAN 2.3: request received, with the reference): the unit code and title and
 * the 1% amount consented to. Never the seller's details. Placeholder wording (PLAN 6 row 24).
 */
class BuyerRequestMail extends Mailable implements OutboxTemplate
{
    /**
     * @param  array{reference: string, code: string, title: string, fee: string}  $facts
     */
    public function __construct(public readonly array $facts) {}

    public static function fromPayload(array $payload, string $locale): Mailable
    {
        $get = static fn (string $key): string => is_scalar($payload[$key] ?? null) ? (string) $payload[$key] : '';

        return new self(['reference' => $get('reference'), 'code' => $get('code'), 'title' => $get('title'), 'fee' => $get('fee')]);
    }

    public function envelope(): Envelope
    {
        $subject = __('mail.request_received.subject', ['reference' => $this->facts['reference']]);

        return new Envelope(subject: is_string($subject) ? $subject : 'mail.request_received.subject');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.buyer-request-received', with: $this->facts);
    }
}
