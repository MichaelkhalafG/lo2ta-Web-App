<?php

namespace App\Support\Review;

use App\Models\Unit;

/**
 * Whether a unit is in an open deal (G2, G11). Deals arrive in part 7.5; until then no unit is in one.
 */
final class OpenDeals
{
    public static function has(Unit $unit): bool
    {
        return false;
    }
}
