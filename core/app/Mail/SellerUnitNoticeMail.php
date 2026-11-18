<?php

namespace App\Mail;

use App\Support\Mail\OutboxTemplate;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Short notices to the seller about their unit (PLAN 2.2.7): withdrawn now; the review outcomes join in phase 7.
 * Placeholder wording (PLAN 6 row 23).
 */
class SellerUnitNoticeMail extends Mailable implements OutboxTemplate
{
    public const KINDS = ['withdrawn'];

    public function __construct(public readonly string $reference, public readonly string $kind) {}

    public static function fromPayload(array $payload, string $locale): Mailable
    {
        $reference = $payload['reference'] ?? '';
        $kind = $payload['kind'] ?? 'withdrawn';

        return new self(is_scalar($reference) ? (string) $reference : '', is_string($kind) && in_array($kind, self::KINDS, true) ? $kind : 'withdrawn');
    }

    public function envelope(): Envelope
    {
        $subject = __("mail.unit_notice.{$this->kind}.subject", ['reference' => $this->reference]);

        return new Envelope(subject: is_string($subject) ? $subject : "mail.unit_notice.{$this->kind}.subject");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.seller-unit-notice', with: ['reference' => $this->reference, 'kind' => $this->kind]);
    }
}
