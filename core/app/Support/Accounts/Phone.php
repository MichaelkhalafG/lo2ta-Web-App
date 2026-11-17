<?php

namespace App\Support\Accounts;

/**
 * Egyptian mobile numbers (Q11: required, Egyptian format, not verified). Accepts 01XXXXXXXXX, +201XXXXXXXXX,
 * 00201XXXXXXXXX and 201XXXXXXXXX, with spaces, dashes or Arabic-Indic digits, and stores 01XXXXXXXXX.
 * Mobile prefixes: 010, 011, 012, 015.
 */
final class Phone
{
    public static function normalize(string $input): ?string
    {
        $digits = strtr($input, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $digits = (string) preg_replace('/[\s\-().]/u', '', $digits);
        $digits = (string) preg_replace('/^(\+20|0020|20)(?=1)/', '0', $digits);

        return preg_match('/^01[0125]\d{8}$/', $digits) === 1 ? $digits : null;
    }
}
