<?php

namespace App\Support\Uploads;

use finfo;

/**
 * The real type of an uploaded file, from its content only (Michael 2026-11-18). The file name, its extension and the
 * Content-Type the client sent are never read. Two independent checks must agree: our own magic-byte test and
 * libmagic (finfo). Anything else is refused by the caller.
 */
final class FileSniffer
{
    public const JPEG = 'image/jpeg';

    public const PNG = 'image/png';

    public const WEBP = 'image/webp';

    public const PDF = 'application/pdf';

    public static function detect(string $path): ?string
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }
        $head = (string) fread($handle, 16);
        fclose($handle);

        $magic = match (true) {
            str_starts_with($head, "\xFF\xD8\xFF") => self::JPEG,
            str_starts_with($head, "\x89PNG\r\n\x1A\n") => self::PNG,
            strlen($head) >= 12 && substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP' => self::WEBP,
            // The PDF header must be the very first bytes; leading junk (a common polyglot trick) is refused.
            str_starts_with($head, '%PDF-') => self::PDF,
            default => null,
        };
        if ($magic === null) {
            return null;
        }

        $libmagic = (new finfo(FILEINFO_MIME_TYPE))->file($path);

        return $libmagic === $magic ? $magic : null;
    }
}
