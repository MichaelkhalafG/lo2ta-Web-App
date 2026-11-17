<?php

namespace App\Enums;

/** For land the same codes read "utilities delivered / under development" (PLAN 2.2.3). */
enum DeliveryStatus: string
{
    use HasLabel;

    case Ready = 'ready';
    case UnderConstruction = 'under_construction';
}
