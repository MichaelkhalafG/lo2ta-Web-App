<?php

namespace App\Enums;

/** PLAN 2.4: expected → due (on completion) → invoiced → collected, or waived with a reason. Status only (G8). */
enum CommissionStatus: string
{
    use HasLabel;

    case Expected = 'expected';
    case Due = 'due';
    case Invoiced = 'invoiced';
    case Collected = 'collected';
    case Waived = 'waived';
}
