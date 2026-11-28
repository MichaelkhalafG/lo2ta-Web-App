<?php

namespace App\Mail;

use App\Models\Unit;
use App\Support\Listing\UnitFacts;
use App\Support\Mail\OutboxTemplate;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Deal e-mails to the buyer and the seller (PLAN 2.3, 2.4, 2.2.7; part 7.5): a deal opened, the developer's approval
 * obtained, completed, cancelled; and to other buyers when the unit they asked about is sold (G12). The unit is named
 * by its public code, card title, compound and area only (PLAN 2.5): never the seller's details in a buyer's e-mail,
 * never the buyer's in the seller's. Our wording, for the client to review (G20).
 */
class DealNoticeMail extends Mailable implements OutboxTemplate
{
    public const KINDS = ['opened', 'approval', 'completed', 'cancelled', 'unit_sold'];

    /**
     * @param  array{code: string, title: string, place: string}  $unit
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $role,
        public readonly string $reference,
        public readonly array $unit,
        public readonly ?string $link,
    ) {}

    public static function fromPayload(array $payload, string $locale): Mailable
    {
        $kind = is_string($payload['kind'] ?? null) && in_array($payload['kind'], self::KINDS, true) ? $payload['kind'] : 'opened';
        $role = ($payload['role'] ?? null) === 'seller' ? 'seller' : 'buyer';
        $id = $payload['unit_id'] ?? null;
        $unit = is_int($id) ? Unit::query()->with(['compound', 'area.parent'])->find($id) : null;
        $facts = ['code' => '', 'title' => '', 'place' => ''];
        if ($unit instanceof Unit) {
            $area = $unit->area;
            $facts = [
                'code' => (string) $unit->code,
                'title' => UnitFacts::title($unit, $locale),
                'place' => implode(' · ', array_filter([$unit->compound?->nameFor($locale), $area?->nameFor($locale), $area?->parent?->nameFor($locale)])),
            ];
        }
        $reference = $payload['reference'] ?? '';
        $link = $payload['link'] ?? null;

        return new self($kind, $role, is_scalar($reference) ? (string) $reference : '', $facts, is_string($link) ? $link : null);
    }

    public function envelope(): Envelope
    {
        $subject = __("mail.deal.{$this->kind}.subject", ['code' => $this->unit['code']]);

        return new Envelope(subject: is_string($subject) ? $subject : "mail.deal.{$this->kind}.subject");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.deal-notice', with: ['kind' => $this->kind, 'role' => $this->role, 'reference' => $this->reference, 'unit' => $this->unit, 'link' => $this->link]);
    }
}
