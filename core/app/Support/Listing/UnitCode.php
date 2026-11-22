<?php

namespace App\Support\Listing;

use App\Models\Area;
use App\Models\Unit;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The public code and slug, formed at publish (PLAN 2.2.8, 5.2 point 7): LK-{area code}-{serial}, where a district
 * takes its area's code; the slug is {compound}-{sub-type}-{bedrooms}-bedrooms-{code}, lower case. The code never
 * changes once set; the slug may (an old slug then redirects to the new one, the code at its end finds the unit).
 */
final class UnitCode
{
    public static function code(Unit $unit): string
    {
        if ($unit->serial === null) {
            throw new RuntimeException('A unit gets its code only after its first submit (no serial yet).');
        }
        $area = $unit->area;
        $code = $area instanceof Area ? ($area->code ?? $area->parent?->code) : null;
        if (! is_string($code) || $code === '') {
            throw new RuntimeException('The unit needs a mapped area with a code before publishing (PLAN 2.2.8).');
        }

        return sprintf('LK-%s-%04d', strtoupper($code), $unit->serial);
    }

    public static function slug(Unit $unit, string $code): string
    {
        $parts = [
            $unit->compound?->slug,
            $unit->sub_type !== null ? str_replace('_', '-', $unit->sub_type->value) : null,
            $unit->sub_type?->hasRooms() === true && ($unit->bedrooms ?? 0) > 0 ? $unit->bedrooms.'-bedrooms' : null,
            strtolower($code),
        ];

        return Str::limit(implode('-', array_filter($parts)), 160, '');
    }

    /** Set both, keeping an existing code (it never changes). */
    public static function assign(Unit $unit): void
    {
        $code = $unit->code ?? self::code($unit);
        $unit->forceFill(['code' => $code, 'slug' => self::slug($unit, $code)]);
    }
}
