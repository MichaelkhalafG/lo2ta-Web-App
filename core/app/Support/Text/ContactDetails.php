<?php

namespace App\Support\Text;

/**
 * Phone numbers, e-mail addresses and links in free text (PLAN 2.2.9 point 6, 2.3): refused in a seller's description,
 * masked in a buyer's question, so nobody can take a deal around Lokta. Arabic and Persian digits count as digits,
 * and digits split by spaces, dashes or dots still count as one number.
 */
final class ContactDetails
{
    public const MASK = '[…]';

    private const EMAIL = '/[A-Z0-9._%+\-]+\s*(@|\(at\)|\[at\])\s*[A-Z0-9.\-]+\.[A-Z]{2,}/i';

    private const LINK = '/https?:\/\/\S+|www\.\S+|\b[a-z0-9\-]+\.(com|net|org|eg|io|me|co|info|biz|link|ly|app|site|online)\b\S*/i';

    /** Egyptian mobiles (01x, +20, 0020) and landlines, after joining separated digits. */
    private const PHONE = '/(?<!\d)(?:\+?20|0020|0)(?:1[0125]\d{8}|[2-9]\d{7,8})(?!\d)/';

    private const DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    public static function has(string $text): bool
    {
        $ascii = strtr($text, self::DIGITS);
        if (preg_match(self::EMAIL, $ascii) === 1 || preg_match(self::LINK, $ascii) === 1) {
            return true;
        }

        return preg_match(self::PHONE, self::joinDigits($ascii)) === 1;
    }

    /** The text with every contact detail replaced by […]; digits are written in Latin form in the result. */
    public static function mask(string $text): string
    {
        $out = strtr($text, self::DIGITS);
        $out = (string) preg_replace(self::EMAIL, self::MASK, $out);
        $out = (string) preg_replace(self::LINK, self::MASK, $out);

        // A phone number may be written with separators: mask each run of digits and separators that forms one.
        return (string) preg_replace_callback('/\+?\d[\d\s\-.()\/]{6,}\d/', static function (array $m): string {
            return preg_match(self::PHONE, self::joinDigits($m[0])) === 1 ? self::MASK : $m[0];
        }, $out);
    }

    private static function joinDigits(string $text): string
    {
        return (string) preg_replace('/(?<=\d)[\s\-.()\/]+(?=\d)/', '', $text);
    }
}
