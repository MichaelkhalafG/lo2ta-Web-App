<?php

namespace App\Enums;

/** A unit request (PLAN 2.3): new -> contacted -> matched (deal opened) | closed (reason). */
enum RequestState: string
{
    use HasLabel;

    case New = 'new';
    case Contacted = 'contacted';
    case Matched = 'matched';
    case Closed = 'closed';

    /** Open requests count toward the 5-per-buyer cap (Q15). */
    public function isOpen(): bool
    {
        return $this === self::New || $this === self::Contacted;
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::New->value, self::Contacted->value];
    }
}
