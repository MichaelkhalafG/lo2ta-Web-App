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
 * Also refused (audit M2, Michael 2026-11-23 decision 2A): anything that acts by itself or reaches out to another
 * file or machine: remote go-to, form submission, data import, media, additional actions (triggers on page open,
 * focus, mouse-over), an open action other than "show this page", and a link to a network path or a local file.
 * A web link the reader clicks (/URI to http, https or mailto) is kept.
 *
 * Accepted with the flag "encrypted": a PDF that opens without a password but restricts printing or copying.
 */
final class PdfInspector
{
    /** Names that make a PDF active (ISO 32000-1 12.6 actions, 7.11 file specifications, 12.7.8 XFA, 13.6 3D/rich media). */
    public const ACTIVE = [
        'JavaScript', 'JS', 'EmbeddedFile', 'EmbeddedFiles', 'EF', 'FileAttachment', 'Launch', 'XFA', 'RichMedia', 'GoToE',
        // Audit M2: other files and machines (12.6.4.3, 12.7.5.2-4), media (12.6.4.13, 13.2-13.4), automatic triggers (12.6.3).
        'GoToR', 'SubmitForm', 'ImportData', 'Rendition', 'Movie', 'Sound', 'AA',
    ];

    /** Names an open action may hold: a destination (12.3.2) or a go-to / named-page action with nothing chained. */
    private const OPEN_ACTION_NAMES = [
        'S', 'Type', 'Action', 'GoTo', 'Named', 'N', 'D', 'NextPage', 'PrevPage', 'FirstPage', 'LastPage',
        'XYZ', 'Fit', 'FitH', 'FitV', 'FitR', 'FitB', 'FitBH', 'FitBV',
    ];

    /** Link targets a reader may open on a click; anything else (file:, a \\host path, smb:, //host) is refused. */
    private const URI_SCHEMES = ['http:', 'https:', 'mailto:'];

    private const BLANK = " \t\r\n\f\0";

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
        // Text a reader parses as objects: the file itself, then each object stream once opened (below).
        $texts = [$pdf];
        $packed = [];

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
            $texts[] = $data;
            $packed += self::packedObjects($data, PdfSyntax::intValue($stream['dict'], 'First'));
        }

        foreach ($texts as $text) {
            self::refuseOpenAction($text, $pdf, $packed);
            self::refuseLinks($text);
        }

        return $crypt === null ? [] : ['encrypted'];
    }

    /**
     * The open action runs when the file opens: only a destination or a go-to / named-page action may stand there.
     * A value we cannot follow is refused (pdf_unsupported), like every structure we cannot read with certainty.
     *
     * @param  array<int, string>  $packed  objects of the object streams, by number
     */
    private static function refuseOpenAction(string $text, string $pdf, array $packed): void
    {
        if (! isset(PdfSyntax::names($text)['OpenAction'])) {
            return;
        }
        $normal = self::decodeNames($text);
        if (preg_match_all('~/OpenAction(?![^\s()<>\[\]{}/%])~', $normal, $all, PREG_OFFSET_CAPTURE) < 1) {
            throw new UploadRefused('pdf_unsupported');
        }
        foreach ($all[0] as [$match, $at]) {
            $value = ltrim(substr($normal, $at + strlen($match), 4096), self::BLANK);
            if (preg_match('~^(\d{1,10})[ \t\r\n\f\0]+(\d{1,5})[ \t\r\n\f\0]+R(?![A-Za-z0-9])~', $value, $ref) === 1) {
                $target = PdfSyntax::objectText($pdf, (int) $ref[1], (int) $ref[2]) ?? $packed[(int) $ref[1]] ?? null;
                if ($target === null) {
                    throw new UploadRefused('pdf_unsupported');
                }
                $value = ltrim(self::decodeNames($target), self::BLANK);
            }
            if (str_starts_with($value, '[')) {
                continue;
            }
            $end = str_starts_with($value, '<<') ? self::dictEnd($value) : null;
            if ($end === null) {
                throw new UploadRefused('pdf_unsupported');
            }
            if (array_diff(array_keys(PdfSyntax::names(substr($value, 0, $end))), self::OPEN_ACTION_NAMES) !== []) {
                throw new UploadRefused('pdf_active');
            }
        }
    }

    /**
     * Every /URI string must be a web or mail address: a network path or a local file leaks or opens on a click.
     * Strings of an encrypted file stay encrypted here and are not read; its remote actions are still refused by name.
     */
    private static function refuseLinks(string $text): void
    {
        if (! isset(PdfSyntax::names($text)['URI'])) {
            return;
        }
        $normal = self::decodeNames($text);
        preg_match_all('~/URI(?=[\s(<])~', $normal, $all, PREG_OFFSET_CAPTURE);
        foreach ($all[0] as [, $at]) {
            $uri = PdfSyntax::stringValue(substr($normal, $at, 4096), 'URI');
            if ($uri === null || preg_match('~^[\x20-\x7e]*$~', $uri) !== 1) {
                // A hex or binary string we cannot read as an address (an encrypted one): only plain ones are judged.
                continue;
            }
            $uri = strtolower(ltrim($uri, self::BLANK));
            $allowed = false;
            foreach (self::URI_SCHEMES as $scheme) {
                $allowed = $allowed || str_starts_with($uri, $scheme);
            }
            if (! $allowed) {
                throw new UploadRefused('pdf_active');
            }
        }
    }

    /**
     * The objects of an inflated object stream, by number (7.5.7: "num offset" pairs, then the objects from /First).
     *
     * @return array<int, string>
     */
    private static function packedObjects(string $data, ?int $first): array
    {
        if ($first === null || $first <= 0 || $first > strlen($data)) {
            return [];
        }
        preg_match_all('~(\d+)[ \t\r\n\f\0]+(\d+)~', substr($data, 0, $first), $pairs, PREG_SET_ORDER);
        $out = [];
        foreach ($pairs as $i => $pair) {
            $start = $first + (int) $pair[2];
            $next = isset($pairs[$i + 1]) ? $first + (int) $pairs[$i + 1][2] : strlen($data);
            if ($start <= strlen($data) && $next >= $start) {
                $out[(int) $pair[1]] = substr($data, $start, $next - $start);
            }
        }

        return $out;
    }

    /** Offset just past the ">>" that closes the dictionary at the start of $s, or null. */
    private static function dictEnd(string $s): ?int
    {
        $depth = 0;
        $len = min(strlen($s), 65536);
        for ($i = 0; $i < $len - 1; $i++) {
            $pair = $s[$i].$s[$i + 1];
            if ($pair === '<<') {
                $depth++;
                $i++;
            } elseif ($pair === '>>') {
                $depth--;
                $i++;
                if ($depth === 0) {
                    return $i + 1;
                }
            }
        }

        return null;
    }

    /** Names with #xx escapes decoded in place, so /Open#41ction reads as /OpenAction. */
    private static function decodeNames(string $s): string
    {
        return (string) preg_replace_callback('~/([^\s()<>\[\]{}/%]*)~', static fn (array $m): string => '/'.PdfSyntax::decodeName($m[1]), $s);
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
