<?php

namespace App\Enums;

enum DeclarationKind: string
{
    use HasLabel;

    case NoOver = 'no_over';
    case OwnerNotBroker = 'owner_not_broker';
    case DataMatches = 'data_matches';
    case TermsPrivacy = 'terms_privacy';
}
