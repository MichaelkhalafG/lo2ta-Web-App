<?php

namespace App\Enums;

/**
 * Deal stages (Q16, decided 2026-11-17): opened → buyer confirmed → seller accepted → documents and developer
 * approval → signing and payment → completed; cancelled from any open stage. A deal moves one stage at a time, or back
 * one with a note; completed is final (G18).
 */
enum DealStage: string
{
    use HasLabel;

    case Opened = 'opened';
    case BuyerConfirmed = 'buyer_confirmed';
    case SellerAccepted = 'seller_accepted';
    case DocumentsApproval = 'documents_approval';
    case SigningPayment = 'signing_payment';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** The forward order, without cancelled. */
    private const ORDER = ['opened', 'buyer_confirmed', 'seller_accepted', 'documents_approval', 'signing_payment', 'completed'];

    public function isOpen(): bool
    {
        return $this !== self::Completed && $this !== self::Cancelled;
    }

    public function next(): ?self
    {
        $i = array_search($this->value, self::ORDER, true);

        return $this->isOpen() && is_int($i) && isset(self::ORDER[$i + 1]) ? self::from(self::ORDER[$i + 1]) : null;
    }

    public function previous(): ?self
    {
        $i = array_search($this->value, self::ORDER, true);

        return $this->isOpen() && is_int($i) && $i > 0 ? self::from(self::ORDER[$i - 1]) : null;
    }

    /** Q16: the unit is reserved from the seller's acceptance until the deal ends. */
    public function reservesUnit(): bool
    {
        return in_array($this, [self::SellerAccepted, self::DocumentsApproval, self::SigningPayment], true);
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return ['opened', 'buyer_confirmed', 'seller_accepted', 'documents_approval', 'signing_payment'];
    }

    /**
     * @return list<self>
     */
    public static function track(): array
    {
        return array_map(static fn (string $s): self => self::from($s), self::ORDER);
    }
}
