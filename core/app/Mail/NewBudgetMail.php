<?php

namespace App\Mail;

use App\Support\Mail\OutboxTemplate;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * To the team: a buyer registered a budget (PLAN 2.3, "to the admin team: new budget registered").
 */
class NewBudgetMail extends Mailable implements OutboxTemplate
{
    /**
     * @param  array<string, string>  $facts
     */
    public function __construct(public readonly array $facts) {}

    public static function fromPayload(array $payload, string $locale): Mailable
    {
        $facts = [];
        foreach (['buyer', 'cash', 'installment', 'verified'] as $key) {
            $value = $payload[$key] ?? '';
            $facts[$key] = is_scalar($value) ? (string) $value : '';
        }

        return new self($facts);
    }

    public function envelope(): Envelope
    {
        $subject = __('mail.new_budget.subject');

        return new Envelope(subject: is_string($subject) ? $subject : 'mail.new_budget.subject');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.admin-new-budget', with: $this->facts);
    }
}
