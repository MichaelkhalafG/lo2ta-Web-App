<?php

namespace App\Mail;

use App\Support\Mail\OutboxTemplate;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * To the team (PLAN 2.2.7): a unit was submitted, resubmitted after changes, got new documents, was withdrawn, or
 * its seller asks to withdraw it during an open deal. Only the reference:
 * the seller's details and the unit's private data stay in the dashboard.
 */
class UnitTeamNoticeMail extends Mailable implements OutboxTemplate
{
    /**
     * @param  'submitted'|'resubmitted'|'documents'|'withdrawn'|'withdraw_request'  $kind
     */
    public function __construct(public readonly string $reference, public readonly string $kind) {}

    public static function fromPayload(array $payload, string $locale): Mailable
    {
        $reference = $payload['reference'] ?? '';
        $kind = match (true) {
            in_array($payload['kind'] ?? null, ['withdrawn', 'withdraw_request'], true) => (string) $payload['kind'],
            ($payload['documents'] ?? '') === '1' => 'documents',
            ($payload['resubmitted'] ?? '') === '1' => 'resubmitted',
            default => 'submitted',
        };

        return new self(is_scalar($reference) ? (string) $reference : '', $kind);
    }

    public function envelope(): Envelope
    {
        $subject = __('mail.unit_team.'.$this->kind, ['reference' => $this->reference]);

        return new Envelope(subject: is_string($subject) ? $subject : 'mail.unit_team.'.$this->kind);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.admin-unit-notice', with: ['reference' => $this->reference, 'kind' => $this->kind]);
    }
}
