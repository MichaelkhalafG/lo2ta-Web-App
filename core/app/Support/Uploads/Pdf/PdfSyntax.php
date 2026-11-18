<?php

namespace App\Support\Uploads\Pdf;

/**
 * The few pieces of PDF syntax the inspector needs (ISO 32000-1 section 7.2 and 7.3). It is a lexer, not a renderer:
 * it walks the file the way a reader does, so that a name hidden inside a string, a comment or a stream body is not
 * mistaken for a real one, and a "stream" keyword typed inside a string cannot hide what follows it.
 */
final class PdfSyntax
{
    /** Whitespace and delimiters end a name (7.2.2); every other byte, high bytes included, belongs to it. */
    private const NAME_END = " \t\r\n\f\0()<>[]{}/%";

    private const WHITESPACE = " \t\r\n\f\0";

    /** "N G obj" headers; the look-behind stops "12 0 obj" matching inside "112 0 obj". */
    public const OBJECT_HEADER = '/(?<![0-9])(\d{1,10})[ \t\r\n\f\0]+(\d{1,5})[ \t\r\n\f\0]+obj(?![A-Za-z0-9])/';

    /**
     * Every name in $s (decoded, e.g. /J#61vaScript becomes JavaScript) and, when $streams is true, every stream with
     * the dictionary in front of it and the object it belongs to.
     *
     * @return array{names: array<string, true>, streams: list<array{dict: string, start: int, end: int, num: int|null, gen: int|null}>}
     */
    public static function lex(string $s, bool $streams = true): array
    {
        $len = strlen($s);
        $names = [];
        $found = [];
        $headers = $streams ? self::headers($s) : [];
        $lastDictEnd = -1;
        $i = 0;

        while ($i < $len) {
            $i += strcspn($s, '(%/<>s', $i);
            if ($i >= $len) {
                break;
            }
            $c = $s[$i];

            if ($c === '(') {
                $i = self::skipLiteral($s, $i);

                continue;
            }
            if ($c === '%') {
                $i += strcspn($s, "\r\n", $i);

                continue;
            }
            if ($c === '/') {
                $n = strcspn($s, self::NAME_END, $i + 1);
                $names[self::decodeName(substr($s, $i + 1, $n))] = true;
                $i += 1 + $n;

                continue;
            }
            if ($c === '<') {
                if (($s[$i + 1] ?? '') === '<') {
                    $i += 2;

                    continue;
                }
                $end = strpos($s, '>', $i + 1);
                $i = $end === false ? $len : $end + 1;

                continue;
            }
            if ($c === '>') {
                if (($s[$i + 1] ?? '') === '>') {
                    $i += 2;
                    $lastDictEnd = $i;

                    continue;
                }
                $i++;

                continue;
            }

            // 's': only the keyword "stream" right after a dictionary, followed by an end of line, opens a stream.
            if ($streams && $lastDictEnd >= 0 && substr_compare($s, 'stream', $i, 6) === 0
                && strspn($s, self::WHITESPACE, $lastDictEnd, $i - $lastDictEnd) === $i - $lastDictEnd
                && in_array($s[$i + 6] ?? '', ["\r", "\n"], true)) {
                $start = $i + 6;
                $start += (($s[$start] ?? '') === "\r" && ($s[$start + 1] ?? '') === "\n") ? 2 : 1;
                $endKeyword = strpos($s, 'endstream', $start);
                $endKeyword = $endKeyword === false ? $len : $endKeyword;
                [$num, $gen, $headerEnd] = self::headerBefore($headers, $i);
                $dict = substr($s, $headerEnd, $lastDictEnd - $headerEnd);

                $found[] = [
                    'dict' => $dict,
                    'start' => $start,
                    'end' => self::streamEnd($s, $dict, $start, $endKeyword),
                    'num' => $num,
                    'gen' => $gen,
                ];
                $i = $endKeyword + 9;
                $lastDictEnd = -1;

                continue;
            }
            $i++;
        }

        return ['names' => $names, 'streams' => $found];
    }

    public static function decodeName(string $raw): string
    {
        return (string) preg_replace_callback('/#([0-9A-Fa-f]{2})/', static fn (array $m): string => chr((int) hexdec($m[1])), $raw);
    }

    /**
     * The decoded names of a small piece of text (a dictionary), without stream handling.
     *
     * @return array<string, true>
     */
    public static function names(string $s): array
    {
        return self::lex($s, false)['names'];
    }

    /**
     * The text of object "num gen obj ... endobj"; the last copy wins, as with an incremental update.
     */
    public static function objectText(string $s, int $num, int $gen): ?string
    {
        $text = null;
        foreach (self::headers($s) as [$n, $g, , $end]) {
            if ($n === $num && $g === $gen) {
                $close = strpos($s, 'endobj', $end);
                $text = substr($s, $end, ($close === false ? strlen($s) : $close) - $end);
            }
        }

        return $text;
    }

    /**
     * A string value of a key in a dictionary: a literal (...) or hex <...> string, decoded.
     */
    public static function stringValue(string $dict, string $key): ?string
    {
        if (preg_match('/\/'.preg_quote($key, '/').'(?=[\s(<])[ \t\r\n\f\0]*([(<])/', $dict, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }
        $at = $m[1][1];
        if ($m[1][0] === '<') {
            if (($dict[$at + 1] ?? '') === '<') {
                return null;
            }
            $end = strpos($dict, '>', $at);
            if ($end === false) {
                return null;
            }
            $hex = (string) preg_replace('/[^0-9A-Fa-f]/', '', substr($dict, $at + 1, $end - $at - 1));
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }
            $bin = hex2bin($hex);

            return $bin === false ? null : $bin;
        }

        return self::literal($dict, $at);
    }

    public static function intValue(string $dict, string $key): ?int
    {
        return preg_match('/\/'.preg_quote($key, '/').'[ \t\r\n\f\0]+(-?\d{1,12})(?![\d.])(?![ \t\r\n\f\0]+\d+[ \t\r\n\f\0]+R)/', $dict, $m) === 1
            ? (int) $m[1] : null;
    }

    /** The dictionary with every nested dictionary removed, so /Length of the outer one is not read from an inner. */
    public static function outerOnly(string $dict): string
    {
        $out = $dict;
        $start = strpos($out, '<<');
        if ($start !== false) {
            $out = substr($out, $start + 2);
        }
        do {
            $before = $out;
            $out = (string) preg_replace('/<<(?:(?!<<|>>).)*>>/s', ' ', $out);
        } while ($out !== $before);

        return $out;
    }

    /**
     * The stream's filter names, decoded; [] for none; null for something we will not interpret (an indirect filter).
     *
     * @return list<string>|null
     */
    public static function filters(string $dict): ?array
    {
        $normal = (string) preg_replace_callback('~/([^\s()<>\[\]{}/%]*)~', static fn (array $m): string => '/'.self::decodeName($m[1]), $dict);
        if (preg_match('/\/Filter[ \t\r\n\f\0]*(\/[^\s\/\[\]<>()]+|\[[^\]]*\]|\d+[ \t\r\n\f\0]+\d+[ \t\r\n\f\0]+R)/', $normal, $m) !== 1) {
            return str_contains($normal, '/Filter') ? null : [];
        }
        if (! str_contains($m[1], '/')) {
            return null;
        }
        preg_match_all('/\/([^\s\/\[\]<>()]+)/', $m[1], $all);

        return $all[1];
    }

    /**
     * Every "N G obj" header: [num, gen, start offset, end offset].
     *
     * @return list<array{0: int, 1: int, 2: int, 3: int}>
     */
    public static function headers(string $s): array
    {
        if (preg_match_all(self::OBJECT_HEADER, $s, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
            return [];
        }
        $out = [];
        foreach ($m as $match) {
            $out[] = [(int) $match[1][0], (int) $match[2][0], $match[0][1], $match[0][1] + strlen($match[0][0])];
        }

        return $out;
    }

    /**
     * @param  list<array{0: int, 1: int, 2: int, 3: int}>  $headers
     * @return array{0: int|null, 1: int|null, 2: int}
     */
    private static function headerBefore(array $headers, int $offset): array
    {
        $lo = 0;
        $hi = count($headers) - 1;
        $best = null;
        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);
            if ($headers[$mid][3] <= $offset) {
                $best = $headers[$mid];
                $lo = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }

        return $best === null ? [null, null, max(0, $offset - 4096)] : [$best[0], $best[1], $best[3]];
    }

    /** The stream data ends at /Length when it is a direct number that lands just before "endstream". */
    private static function streamEnd(string $s, string $dict, int $start, int $endKeyword): int
    {
        $length = self::intValue(self::outerOnly($dict), 'Length');
        if ($length !== null && $length >= 0 && $start + $length <= $endKeyword) {
            $gap = $endKeyword - ($start + $length);
            if (strspn($s, self::WHITESPACE, $start + $length, $gap) === $gap) {
                return $start + $length;
            }
        }
        $end = $endKeyword;
        if ($end > $start && $s[$end - 1] === "\n") {
            $end--;
        }
        if ($end > $start && $s[$end - 1] === "\r") {
            $end--;
        }

        return $end;
    }

    private static function skipLiteral(string $s, int $i): int
    {
        $len = strlen($s);
        $depth = 1;
        $j = $i + 1;
        while ($j < $len) {
            $j += strcspn($s, '()\\', $j);
            if ($j >= $len) {
                break;
            }
            if ($s[$j] === '\\') {
                $j += 2;

                continue;
            }
            $depth += $s[$j] === '(' ? 1 : -1;
            $j++;
            if ($depth === 0) {
                return $j;
            }
        }

        return $len;
    }

    /** A literal string at $i ("("), with its escapes decoded (7.3.4.2). */
    private static function literal(string $s, int $i): string
    {
        $len = strlen($s);
        $out = '';
        $depth = 1;
        $j = $i + 1;
        while ($j < $len) {
            $c = $s[$j];
            if ($c === '\\') {
                $next = $s[$j + 1] ?? '';
                $map = ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\f", '(' => '(', ')' => ')', '\\' => '\\'];
                if (isset($map[$next])) {
                    $out .= $map[$next];
                    $j += 2;
                } elseif ($next !== '' && $next >= '0' && $next <= '7') {
                    $oct = $next;
                    $k = $j + 2;
                    while (strlen($oct) < 3 && isset($s[$k]) && $s[$k] >= '0' && $s[$k] <= '7') {
                        $oct .= $s[$k];
                        $k++;
                    }
                    $out .= chr(((int) octdec($oct)) & 0xFF);
                    $j = $k;
                } elseif ($next === "\r") {
                    $j += (($s[$j + 2] ?? '') === "\n") ? 3 : 2;
                } elseif ($next === "\n") {
                    $j += 2;
                } else {
                    $out .= $next;
                    $j += 2;
                }

                continue;
            }
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            }
            if ($c === "\r") {
                $out .= "\n";
                $j += (($s[$j + 1] ?? '') === "\n") ? 2 : 1;

                continue;
            }
            $out .= $c;
            $j++;
        }

        return $out;
    }
}
