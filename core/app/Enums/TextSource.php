<?php

namespace App\Enums;

/**
 * Where a site text stands (PLAN 6, K7). `placeholder`: a temporary text, still to be written. `ours`: written by us,
 * the client reviews it later. `legal_review`: written by us, awaiting legal review (Eng. Enas) before launch.
 * `client`: the client's own text.
 */
enum TextSource: string
{
    use HasLabel;

    case Placeholder = 'placeholder';
    case Ours = 'ours';
    case LegalReview = 'legal_review';
    case Client = 'client';

    /** Not final: a placeholder, or a text still awaiting legal review. */
    public function isFinal(): bool
    {
        return $this === self::Ours || $this === self::Client;
    }
}
