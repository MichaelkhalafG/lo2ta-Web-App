<?php

namespace App\Support\Uploads\Pdf;

use App\Support\Uploads\UploadRefused;

/**
 * Is this a real PDF, and is it passive? (Michael 2026-11-18, PLAN 2.2.15.)
 *
 * Refused: anything that is not a PDF from its first byte; JavaScript, embedded files, file attachments, launch
 * actions, XFA forms, rich media and embedded go-to actions, wherever they sit: in the plain file, inside compressed
 * object streams, inside object streams of an encrypted file (decrypted in memory with the empty password), or as an
 * object hidden inside another stream's bytes for a reader in repair mode; a password needed to open it; any
 * structure we cannot read with certainty (an object stream with an unknown filter or a predictor, an indirect
 * filter, a stream that does not decompress). Decompression is capped, so a "zip bomb" PDF is refused.
 *
 * Accepted with the flag "encrypted": a PDF that opens without a password but restricts printing or copying.
 */
final class PdfInspector
{
    /** Names that make a PDF active (ISO 32000-1 12.6 actions, 7.11 file specifications, 12.7.8 XFA, 13.6 3D/rich media). */
    public const ACTIVE = [
        'JavaScript', 'JS', 'EmbeddedFile', 'EmbeddedFiles', 'EF', 'FileAttachment', 'Launch', 'XFA', 'RichMedia', 'GoToE',
    ];

    /**
     * Decompressed bytes allowed per object stream and for the whole file. Real object streams are well under 1 MB;
     * the caps keep the peak memory of one inspection far below the host's 256M.
     */
    public const STREAM_CAP = 16 * 1024 * 1024;

    public const TOTAL_CAP = 32 * 1024 * 1024;

    /** How far past a hidden "N G obj" header to look for active names. */
    private const HIDDEN_WINDOW = 65536;

    /**
     * @param  int  $streamCap  bytes one object stream may inflate to
     * @param  int  $totalCap  bytes all object streams together may inflate to
     */
    public function __construct(private readonly int $streamCap = self::STREAM_CAP, private readonly int $totalCap = self::TOTAL_CAP) {}

    /**
     * @return list<string> flags for the reviewer
     */
    public function inspect(string $pdf): array
    {
        if (preg_match('/^%PDF-[12]\.\d/', $pdf) !== 1
            || ! str_contains(substr($pdf, -2048), '%%EOF')
            || ! str_contains($pdf, 'startxref')
            || preg_match(PdfSyntax::OBJECT_HEADER, $pdf) !== 1) {
            throw new UploadRefused('pdf_invalid');
        }

        $lex = PdfSyntax::lex($pdf);
        self::refuseActive($lex['names']);

        // Objects tucked inside another stream's bytes: a reader rebuilding a broken file would index them.
        foreach ($lex['streams'] as $stream) {
            $body = substr($pdf, $stream['start'], $stream['end'] - $stream['start']);
            foreach (PdfSyntax::headers($body) as [, , , $end]) {
                $window = substr($body, $end, self::HIDDEN_WINDOW);
                $close = strpos($window, 'endobj');
                self::refuseActive(PdfSyntax::names($close === false ? $window : substr($window, 0, $close)));
            }
        }

        $crypt = isset($lex['names']['Encrypt']) ? PdfDecryptor::open($pdf) : null;

        // Object streams (any stream a reader could parse as one: it has /N and /First) are opened and read.
        $budget = $this->totalCap;
        foreach ($lex['streams'] as $stream) {
            $dictNames = PdfSyntax::names($stream['dict']);
            if (! isset($dictNames['N'], $dictNames['First'])) {
                continue;
            }
            $data = substr($pdf, $stream['start'], $stream['end'] - $stream['start']);

            if ($crypt !== null) {
                if ($stream['num'] === null || $stream['gen'] === null) {
                    throw new UploadRefused('pdf_unsupported');
                }
                $data = $crypt->stream($data, $stream['num'], $stream['gen'], $dictNames);
                if ($data === null) {
                    throw new UploadRefused('pdf_invalid');
                }
            }

            $filters = PdfSyntax::filters($stream['dict']);
            if ($filters === null || isset($dictNames['Predictor'])) {
                throw new UploadRefused('pdf_unsupported');
            }
            foreach ($filters as $filter) {
                if ($filter !== 'FlateDecode' && $filter !== 'Fl') {
                    throw new UploadRefused('pdf_unsupported');
                }
                $data = self::inflate($data, min($this->streamCap, $budget));
            }
            $budget -= strlen($data);

            self::refuseActive(PdfSyntax::names($data));
        }

        return $crypt === null ? [] : ['encrypted'];
    }

    /**
     * @param  array<string, true>  $names
     */
    private static function refuseActive(array $names): void
    {
        foreach (self::ACTIVE as $name) {
            if (isset($names[$name])) {
                throw new UploadRefused('pdf_active');
            }
        }
    }

    /** zlib inflate in small steps with a hard cap; anything that does not inflate is refused. */
    private static function inflate(string $data, int $cap): string
    {
        $context = @inflate_init(ZLIB_ENCODING_DEFLATE);
        if ($context === false) {
            throw new UploadRefused('pdf_invalid');
        }
        $out = '';
        $length = strlen($data);
        for ($at = 0; $at < $length; $at += 4096) {
            $piece = @inflate_add($context, substr($data, $at, 4096), ZLIB_SYNC_FLUSH);
            if ($piece === false) {
                if ($out === '') {
                    throw new UploadRefused('pdf_invalid');
                }
                break;
            }
            $out .= $piece;
            if (strlen($out) > $cap) {
                throw new UploadRefused('pdf_too_complex');
            }
            if (inflate_get_status($context) === ZLIB_STREAM_END) {
                break;
            }
        }
        if ($out === '' && $length > 0) {
            throw new UploadRefused('pdf_invalid');
        }

        return $out;
    }
}
