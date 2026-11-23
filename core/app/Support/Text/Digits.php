<?php

namespace App\Support\Text;

/**
 * Arabic-Indic and Persian digits as Latin 0-9: people type phones, contract numbers and amounts in either script.
 * One map for the whole core (audit C2: it was copied four times).
 */
final class Digits
{
    private const MAP = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    public static function latin(string $value): string
    {
        return strtr($value, self::MAP);
    }
}
