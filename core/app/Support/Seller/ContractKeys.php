<?php

namespace App\Support\Seller;

use App\Models\Compound;
use App\Models\Developer;
use App\Models\Unit;
use App\Support\Text\Digits;

/**
 * The duplicate keys (PLAN 3.4 A): developer + contract number, and developer + compound + unit number, both
 * normalised so "C-00 123", "c00123" and "C٠٠١٢٣" are one contract. Stored as SHA-256 (private, opaque).
 *
 * A developer or compound typed by hand is matched to the lists first (audit M3, Michael 2026-11-23 decision 3A):
 * "palm hills developments" or "بالم هيلز" typed instead of chosen gives the same key as the list entry, so typing
 * cannot get round the block. Matching ignores case, Arabic letter variants, digits' script, spacing and the
 * company words around a name (Developments, Group, شركة, للتطوير العقاري ...). A name that matches several entries
 * stays typed text: the key never guesses between two developers.
 */
final class ContractKeys
{
    /** Words that dress a company or project name without telling it apart (compared after normalising). */
    private const COMPANY_WORDS = [
        'THE', 'CO', 'COMPANY', 'GROUP', 'HOLDING', 'DEVELOPER', 'DEVELOPERS', 'DEVELOPMENT', 'DEVELOPMENTS',
        'PROPERTY', 'PROPERTIES', 'REAL', 'ESTATE', 'REALESTATE', 'EGYPT', 'SAE', 'LLC', 'COMPOUND',
        'شركه', 'ش', 'م', 'مجموعه', 'جروب', 'للتطوير', 'التطوير', 'تطوير', 'العقاري', 'العقاريه', 'عقاري', 'للاستثمار',
        'مصر', 'كمبوند', 'كومباوند',
    ];

    public static function normalize(string $value): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', self::fold($value));
    }

    /** A developer or compound name reduced to what tells it apart; '' when nothing is left. */
    public static function nameKey(?string $value): string
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', self::fold((string) $value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $kept = array_filter($tokens, static fn (string $t): bool => ! in_array($t, self::COMPANY_WORDS, true));

        return implode('', $kept === [] ? $tokens : $kept);
    }

    /**
     * @return array{contract: string, unit: string}|null null while step 1 is incomplete
     */
    public static function of(Unit $unit): ?array
    {
        $developerId = $unit->developer_id ?? self::match(Developer::query()->get(['id', 'name_ar', 'name_en']), $unit->developer_other);
        $developer = $developerId !== null ? 'd'.$developerId : self::other($unit->developer_other);

        $compoundId = $unit->compound_id;
        if ($compoundId === null) {
            $compounds = Compound::query()
                ->when($developerId !== null, fn ($q) => $q->where('developer_id', $developerId))
                ->get(['id', 'name_ar', 'name_en']);
            $compoundId = self::match($compounds, $unit->compound_other);
        }
        $compound = $compoundId !== null ? 'c'.$compoundId : self::other($unit->compound_other);

        $contract = self::normalize((string) $unit->contract_number);
        $number = self::normalize((string) $unit->unit_number);
        if ($developer === null || $compound === null || $contract === '' || $number === '') {
            return null;
        }

        return [
            'contract' => hash('sha256', $developer.'|'.$contract),
            'unit' => hash('sha256', $developer.'|'.$compound.'|'.$number),
        ];
    }

    /**
     * The one list entry whose Arabic or English name matches the typed text, or null (none, or more than one).
     *
     * @param  iterable<Developer|Compound>  $entries
     */
    private static function match(iterable $entries, ?string $typed): ?int
    {
        $key = self::nameKey($typed);
        if ($key === '') {
            return null;
        }
        $found = [];
        foreach ($entries as $entry) {
            if (self::nameKey($entry->name_ar) === $key || self::nameKey($entry->name_en) === $key) {
                $found[$entry->id] = true;
            }
        }

        return count($found) === 1 ? (int) array_key_first($found) : null;
    }

    private static function other(?string $text): ?string
    {
        $clean = self::nameKey($text);

        return $clean === '' ? null : 'o:'.$clean;
    }

    /** Upper case, Western digits, one spelling of the Arabic letters that are often swapped. */
    private static function fold(string $value): string
    {
        return mb_strtoupper(strtr(Digits::latin($value), [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي',
        ]));
    }
}
