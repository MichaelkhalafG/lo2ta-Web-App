<?php

namespace App\Enums;

enum InstallmentFrequency: string
{
    use HasLabel;

    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case SemiAnnual = 'semi_annual';
    case Annual = 'annual';

    public function perYear(): int
    {
        return match ($this) {
            self::Monthly => 12,
            self::Quarterly => 4,
            self::SemiAnnual => 2,
            self::Annual => 1,
        };
    }
}
