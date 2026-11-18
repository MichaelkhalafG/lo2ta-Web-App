<?php

namespace App\Support\Uploads;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Streams a private file after the route has checked who is asking. The headers make sure a browser treats it as the
 * stored type only (nosniff), cannot run anything in it (CSP sandbox), never caches it and never sends it on.
 * PDFs are always downloads, never shown inline in our origin.
 */
final class ServeFile
{
    public static function private(string $path, string $mime, string $downloadName): StreamedResponse
    {
        $disk = Storage::disk(UploadService::PRIVATE_DISK);
        if (! $disk->exists($path)) {
            throw new NotFoundHttpException;
        }
        $inline = $mime !== FileSniffer::PDF;
        $size = $disk->size($path);

        return response()->stream(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) $size,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$downloadName.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Frame-Options' => 'DENY',
        ]);
    }
}
