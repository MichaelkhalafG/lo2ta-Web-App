<?php

namespace App\Support\Seller;

use App\Models\Unit;

/**
 * The duplicate keys (PLAN 3.4 A): developer + contract number, and developer + compound + unit number, both
 * normalised so "C-00 123", "c00123" and "C٠٠١٢٣" are one contract. Stored as SHA-256 (private, opaque).
 */
final class ContractKeys
{
    public static function normalize(string $value): string
    {
        $value = strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي',
        ]);

        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtoupper($value));
    }

    /**
     * @return array{contract: string, unit: string}|null null while step 1 is incomplete
     */
    public static function of(Unit $unit): ?array
    {
        $developer = $unit->developer_id !== null ? 'd'.$unit->developer_id : self::other($unit->developer_other);
        $compound = $unit->compound_id !== null ? 'c'.$unit->compound_id : self::other($unit->compound_other);
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

    private static function other(?string $text): ?string
    {
        $clean = self::normalize((string) $text);

        return $clean === '' ? null : 'o:'.$clean;
    }
}
