<?php

namespace App\Support\Review;

use App\Models\Deal;
use App\Models\Unit;

/**
 * Whether a unit is in an open deal (G2, G11): a deal holds `open_unit_id` while it is open.
 */
final class OpenDeals
{
    public static function has(Unit $unit): bool
    {
        return Deal::query()->where('open_unit_id', $unit->id)->exists();
    }
}
