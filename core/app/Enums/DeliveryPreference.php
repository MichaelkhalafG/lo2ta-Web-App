<?php

namespace App\Enums;

/** A buyer's delivery preference (PLAN 2.3). */
enum DeliveryPreference: string
{
    use HasLabel;

    case Ready = 'ready';
    case UnderConstruction = 'under_construction';
    case Either = 'either';
}
