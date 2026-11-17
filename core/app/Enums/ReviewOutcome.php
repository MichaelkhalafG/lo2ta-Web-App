<?php

namespace App\Enums;

enum ReviewOutcome: string
{
    use HasLabel;

    case ChangesRequested = 'changes_requested';
    case Rejected = 'rejected';
    case Published = 'published';
    case PublishedVerified = 'published_verified';
}
