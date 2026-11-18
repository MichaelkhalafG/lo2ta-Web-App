<?php

namespace App\Support\Uploads;

use App\Support\Settings\Settings;
use GdImage;

/**
 * Every uploaded image is decoded and written out as a NEW file (Michael 2026-11-18); the uploaded bytes are never
 * kept or served. Whatever an attacker hid in the file (a script after the image data, a polyglot, EXIF with the
 * phone's location) does not survive, because only decoded pixels are written back.
 *
 * Order of checks, all before any pixel is decoded:
 * 1. the header is read with getimagesize (it reads the header only) and must match the sniffed type;
 * 2. width, height and megapixels against the settings (8000 px a side, 25 MP), which stops decompression bombs:
 *    a PNG that claims 50,000 x 50,000 is refused before GD allocates anything;
 * 3. the memory the decode needs against the PHP memory_limit, so a large but allowed image fails cleanly instead of
 *    killing the request.
 * Then GD decodes, the decoded size must equal the header's, and the image is re-encoded.
 */
final class ImageSanitizer
{
    /** Photos are stored at most this many pixels on the long side (PLAN 2.2.14). */
    public const PHOTO_MAX_SIDE = 2000;

    public const PHOTO_WEBP_QUALITY = 82;

    public const DOCUMENT_JPEG_QUALITY = 92;

    public function __construct(private readonly Settings $settings) {}

    /**
     * A listing photo or floor plan: re-encoded as WebP, at most 2000 px on the long side.
     *
     * @return array{bytes: string, width: int, height: int, mime: string, ext: string}
     */
    public function photo(string $path, string $mime): array
    {
        $image = $this->load($path, $mime, $this->settings->int('photo_min_px'));
        $image = self::fit($image, self::PHOTO_MAX_SIDE);

        return self::encode($image, FileSniffer::WEBP);
    }

    /**
     * An image sent as a document (a contract page, an ID scan): re-encoded in its own format at full size.
     *
     * @return array{bytes: string, width: int, height: int, mime: string, ext: string}
     */
    public function document(string $path, string $mime): array
    {
        $image = $this->load($path, $mime, 1);
        $out = $mime === FileSniffer::PNG ? FileSniffer::PNG : FileSniffer::JPEG;

        return self::encode($image, $out);
    }

    private function load(string $path, string $mime, int $minLongSide): GdImage
    {
        $info = @getimagesize($path);
        if ($info === false) {
            throw new UploadRefused('image_unreadable');
        }
        [$width, $height, $type] = $info;
        $expected = match ($mime) {
            FileSniffer::JPEG => IMAGETYPE_JPEG,
            FileSniffer::PNG => IMAGETYPE_PNG,
            FileSniffer::WEBP => IMAGETYPE_WEBP,
            default => null,
        };
        if ($expected === null || $type !== $expected || $width < 1 || $height < 1) {
            throw new UploadRefused('image_unreadable');
        }

        $maxSide = $this->settings->int('image_max_side_px');
        $maxPixels = $this->settings->int('image_max_mp') * 1_000_000;
        if ($width > $maxSide || $height > $maxSide || $width * $height > $maxPixels) {
            throw new UploadRefused('image_too_large', ['side' => $maxSide, 'mp' => $this->settings->int('image_max_mp')]);
        }
        if (max($width, $height) < $minLongSide) {
            throw new UploadRefused('image_too_small', ['px' => $minLongSide]);
        }
        self::ensureMemory($width, $height);

        $image = match ($mime) {
            FileSniffer::JPEG => @imagecreatefromjpeg($path),
            FileSniffer::PNG => @imagecreatefrompng($path),
            default => @imagecreatefromwebp($path),
        };
        // GD refuses animated WebP and broken data: both end here.
        if (! $image instanceof GdImage || imagesx($image) !== $width || imagesy($image) !== $height) {
            throw new UploadRefused('image_unreadable');
        }
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        return $image;
    }

    private static function fit(GdImage $image, int $maxSide): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $long = max($width, $height);
        if ($long <= $maxSide) {
            return $image;
        }

        $newWidth = max(1, (int) round($width * $maxSide / $long));
        $newHeight = max(1, (int) round($height * $maxSide / $long));
        $target = imagecreatetruecolor($newWidth, $newHeight);
        if (! $target instanceof GdImage) {
            throw new UploadRefused('image_unreadable');
        }
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $target;
    }

    /**
     * @return array{bytes: string, width: int, height: int, mime: string, ext: string}
     */
    private static function encode(GdImage $image, string $mime): array
    {
        ob_start();
        $ok = match ($mime) {
            FileSniffer::WEBP => imagewebp($image, null, self::PHOTO_WEBP_QUALITY),
            FileSniffer::PNG => (imagesavealpha($image, true) && imagepng($image, null, 6)),
            default => imagejpeg($image, null, self::DOCUMENT_JPEG_QUALITY),
        };
        $bytes = (string) ob_get_clean();
        if (! $ok || $bytes === '') {
            throw new UploadRefused('image_unreadable');
        }

        return [
            'bytes' => $bytes,
            'width' => imagesx($image),
            'height' => imagesy($image),
            'mime' => $mime,
            'ext' => match ($mime) {
                FileSniffer::WEBP => 'webp',
                FileSniffer::PNG => 'png',
                default => 'jpg',
            },
        ];
    }

    /** GD keeps 4 bytes a pixel; the decode, a resized copy and the encoder need headroom on top. */
    private static function ensureMemory(int $width, int $height): void
    {
        $limit = self::iniBytes((string) ini_get('memory_limit'));
        if ($limit <= 0) {
            return;
        }
        $need = $width * $height * 5 + self::PHOTO_MAX_SIDE * self::PHOTO_MAX_SIDE * 4 + 16 * 1024 * 1024;
        if (memory_get_usage(true) + $need > $limit) {
            throw new UploadRefused('image_too_large_memory');
        }
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
