<?php

namespace App\Enums;

enum DocumentType: string
{
    use HasLabel;

    case Contract = 'contract';
    case Schedule = 'schedule';
    case NationalIdFront = 'national_id_front';
    case NationalIdBack = 'national_id_back';
    case Receipt = 'receipt';
    case Statement = 'statement';

    /** National ID copies follow the retention rule of PLAN 2.2.8. */
    public function isNationalId(): bool
    {
        return $this === self::NationalIdFront || $this === self::NationalIdBack;
    }
}
