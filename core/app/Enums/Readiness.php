<?php

namespace App\Enums;

/** How soon the buyer can move (PLAN 2.3: ready now / within 1-3 months / just looking). */
enum Readiness: string
{
    use HasLabel;

    case Now = 'now';
    case WithinThreeMonths = 'within_3_months';
    case Looking = 'looking';
}
