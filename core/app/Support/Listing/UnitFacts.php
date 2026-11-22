<?php

namespace App\Support\Listing;

use App\Enums\InstallmentFrequency;
use App\Models\Area;
use App\Models\Unit;
use App\Support\Settings\Settings;
use Carbon\CarbonImmutable;

/**
 * Figures and words a buyer reads, built from public fields only (PLAN 2.5): the card title, the amount to the
 * owner, the remaining installments, the computed schedule, and the 1% buyer fee.
 */
final class UnitFacts
{
    /** "شقة 3 غرف في التجمع الخامس" / "Apartment with 3 bedrooms in Fifth Settlement" (PLAN 2.5 card title). */
    public static function title(Unit $unit, string $locale): string
    {
        $type = $unit->sub_type?->label($locale) ?? '';
        $place = $unit->area instanceof Area ? $unit->area->nameFor($locale) : '';
        $rooms = $unit->sub_type?->hasRooms() === true && ($unit->bedrooms ?? 0) > 0 ? $unit->bedrooms : null;
        $key = $rooms !== null ? 'listing.title_rooms' : 'listing.title';
        $text = __($key, ['type' => $type, 'rooms' => (string) $rooms, 'place' => $place], $locale);

        return is_string($text) ? trim($text) : $type;
    }

    /**
     * The least the buyer pays the owner: the lowest amount accepted when the seller opted in, else what was paid
     * (Michael 2026-11-22: the "pay the owner up to" filter compares with this).
     */
    public static function ownerFrom(Unit $unit): ?int
    {
        return $unit->accepts_less && $unit->lowest_accepted_amount !== null ? $unit->lowest_accepted_amount : $unit->paid_amount;
    }

    /** SQL for ownerFrom(), for filters and sorting. */
    public const OWNER_FROM_SQL = 'CASE WHEN accepts_less = 1 AND lowest_accepted_amount IS NOT NULL THEN lowest_accepted_amount ELSE paid_amount END';

    public static function installmentsLeft(Unit $unit): ?int
    {
        $frequency = $unit->installment_frequency;
        if (! $frequency instanceof InstallmentFrequency || $unit->years_left === null) {
            return null;
        }

        return $unit->years_left * $frequency->perYear();
    }

    public static function paidPercent(Unit $unit): ?int
    {
        return $unit->total_price !== null && $unit->total_price > 0 && $unit->paid_amount !== null
            ? (int) round($unit->paid_amount * 100 / $unit->total_price) : null;
    }

    /**
     * Every installment date until the last, from the amount, the frequency and the next due date (board 06.3).
     *
     * @return list<array{n: int, date: string, amount: int}>
     */
    public static function schedule(Unit $unit): array
    {
        $count = self::installmentsLeft($unit);
        $frequency = $unit->installment_frequency;
        if ($count === null || $count < 1 || ! $frequency instanceof InstallmentFrequency || $unit->next_due_date === null || $unit->installment_amount === null) {
            return [];
        }
        $step = intdiv(12, $frequency->perYear());
        $first = CarbonImmutable::parse($unit->next_due_date->toDateString());
        $out = [];
        for ($n = 0; $n < min($count, 480); $n++) {
            $out[] = ['n' => $n + 1, 'date' => $first->addMonthsNoOverflow($n * $step)->toDateString(), 'amount' => $unit->installment_amount];
        }

        return $out;
    }

    /**
     * The buyer's 1% on the total contract price (PLAN 4.2, Q3), with VAT at vat_bp (0 today).
     *
     * @return array{bp: int, amount: int, vat_bp: int, vat: int}
     */
    public static function buyerFee(Unit $unit, Settings $settings): array
    {
        $bp = $settings->int('buyer_fee_bp');
        $vatBp = $settings->int('vat_bp');
        $amount = intdiv((int) $unit->total_price * $bp + 5_000, 10_000);

        return ['bp' => $bp, 'amount' => $amount, 'vat_bp' => $vatBp, 'vat' => intdiv($amount * $vatBp + 5_000, 10_000)];
    }
}
