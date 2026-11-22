<?php

namespace App\Mail;

use App\Support\Mail\OutboxTemplate;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * To the team (PLAN 2.3, 2.6): a new unit request, contact message or partnership request, as a list of facts. Until
 * the phase 7 inbox exists this e-mail is where the team reads contact and partnership messages (PLAN D25); a unit
 * request carries only its reference, the unit code and the readiness, never the buyer's details.
 */
class TeamFactsMail extends Mailable implements OutboxTemplate
{
    public const KINDS = [
        'unit_request' => ['reference', 'code', 'readiness'],
        'contact_message' => ['name', 'email', 'phone', 'message'],
        'partnership_request' => ['company', 'contact_name', 'job_title', 'email', 'phone', 'projects_count', 'message'],
    ];

    /**
     * @param  array<string, string>  $facts
     */
    public function __construct(public readonly string $kind, public readonly array $facts) {}

    public static function fromPayload(array $payload, string $locale): Mailable
    {
        $kind = $payload['kind'] ?? '';
        $kind = is_string($kind) && isset(self::KINDS[$kind]) ? $kind : 'contact_message';
        $facts = [];
        foreach (self::KINDS[$kind] as $key) {
            $value = $payload[$key] ?? '';
            $facts[$key] = is_scalar($value) ? (string) $value : '';
        }

        return new self($kind, $facts);
    }

    public function envelope(): Envelope
    {
        $subject = __("mail.team_facts.{$this->kind}.subject", ['reference' => $this->facts['reference'] ?? '']);

        return new Envelope(subject: is_string($subject) ? $subject : "mail.team_facts.{$this->kind}.subject");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.team-facts', with: ['kind' => $this->kind, 'facts' => $this->facts]);
    }
}
