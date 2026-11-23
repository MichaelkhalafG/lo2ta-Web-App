<?php

namespace App\Support\Text;

use Normalizer;

/**
 * Phone numbers, e-mail addresses, links and social handles in free text (PLAN 2.2.9 point 6, 2.3): refused in a
 * seller's description, masked in a buyer's question, so nobody can take a deal around Lokta. Arabic and Persian
 * digits count as digits, and digits split by spaces, dashes, dots or Arabic commas still count as one number.
 *
 * Audit M7: the text is first brought to one form (NFKC turns fullwidth digits into 0-9), invisible characters and
 * emoji keycap marks are dropped, a letter O inside a run of digits counts as 0, and a mobile written without its
 * leading 0 (1012345678) counts, unless the run is a grouped amount (1 000 000 000). Numbers spelled out in words
 * are not caught here: the team reads every description before it is published.
 */
final class ContactDetails
{
    public const MASK = '[…]';

    private const EMAIL = '/[A-Z0-9._%+\-]+\s*(@|\(at\)|\[at\])\s*[A-Z0-9.\-]+\.[A-Z]{2,}/i';

    /** "ahmed at gmail dot com". */
    private const EMAIL_WORDS = '/\b[A-Z0-9._%+\-]+\s+(?:at|\(at\)|\[at\])\s+[A-Z0-9\-]+\s*(?:\.|\bdot\b|\(dot\)|\[dot\])\s*(?:com|net|org|eg|io|me|co)\b/i';

    private const LINK = '/https?:\/\/\S+|www\.\S+|\b[a-z0-9\-]+\.(com|net|org|eg|io|me|co|info|biz|link|ly|app|site|online)\b\S*/i';

    /** A social network named next to a handle, or a bare @handle. */
    private const HANDLE = '/(?:facebook|fb|instagram|insta|telegram|tiktok|snapchat|snap|twitter|whatsapp|فيسبوك|فيس|انستجرام|انستغرام|انستا|تيليجرام|تلجرام|تيك\s?توك|سناب|واتساب|واتس)\s*[:\/]?\s*@?[A-Za-z0-9._]{3,}|(?<![A-Za-z0-9._%+\-])@[A-Za-z][A-Za-z0-9._]{2,}/iu';

    /** Egyptian mobiles (01x, +20, 0020) and landlines, after joining separated digits. */
    private const PHONE = '/(?<!\d)(?:\+?20|0020|0)(?:1[0125]\d{8}|[2-9]\d{7,8})(?!\d)/';

    /** A mobile written without its leading 0. */
    private const MOBILE_BARE = '/(?<!\d)1[0125]\d{8}(?!\d)/';

    /** A run of digits and separators that may form one number; a letter O in it is read as 0. */
    private const RUN = '/\+?[\dOo][\dOo\s\-.()\/\x{060C}_]{6,}[\dOo]/u';

    /** An amount written in groups of three ("1 000 000", "1.500.000"): never a phone written without its 0. */
    private const GROUPED = '/^\d{1,3}(?:[\s.]\d{3})+$/';

    public static function has(string $text): bool
    {
        $plain = self::prepare($text);
        foreach ([self::EMAIL, self::EMAIL_WORDS, self::LINK, self::HANDLE] as $pattern) {
            if (preg_match($pattern, $plain) === 1) {
                return true;
            }
        }
        if (preg_match_all(self::RUN, $plain, $runs) > 0) {
            foreach ($runs[0] as $run) {
                if (self::isPhone($run)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** The text with every contact detail replaced by […]; digits are written in Latin form in the result. */
    public static function mask(string $text): string
    {
        $out = self::prepare($text);
        foreach ([self::EMAIL, self::EMAIL_WORDS, self::LINK, self::HANDLE] as $pattern) {
            $out = (string) preg_replace($pattern, self::MASK, $out);
        }

        // A phone number may be written with separators: mask each run of digits and separators that forms one.
        return (string) preg_replace_callback(self::RUN, static fn (array $m): string => self::isPhone($m[0]) ? self::MASK : $m[0], $out);
    }

    private static function isPhone(string $run): bool
    {
        $run = strtr($run, ['o' => '0', 'O' => '0']);
        $joined = self::joinDigits($run);
        if (preg_match(self::PHONE, $joined) === 1) {
            return true;
        }

        return preg_match(self::GROUPED, trim($run)) !== 1 && preg_match(self::MOBILE_BARE, $joined) === 1;
    }

    /** One form for the text: NFKC, no invisible characters or keycap marks, Latin digits. */
    private static function prepare(string $text): string
    {
        $normal = Normalizer::normalize($text, Normalizer::FORM_KC);
        $out = is_string($normal) ? $normal : $text;
        // Format characters (zero-width space and joiners, direction marks, BOM), variation selectors, keycap marks.
        $out = (string) preg_replace('/[\p{Cf}\x{FE00}-\x{FE0F}\x{20E3}]/u', '', $out);

        return Digits::latin($out);
    }

    private static function joinDigits(string $text): string
    {
        return (string) preg_replace('/(?<=\d)[\s\-.()\/\x{060C}_]+(?=\d)/u', '', $text);
    }
}
