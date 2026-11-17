<?php

namespace App\Enums;

enum DocumentStatus: string
{
    use HasLabel;

    case Uploaded = 'uploaded';
    case Approved = 'approved';
    case NeedsReplacement = 'needs_replacement';
}
