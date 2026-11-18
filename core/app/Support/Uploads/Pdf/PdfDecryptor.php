<?php

namespace App\Support\Uploads\Pdf;

use App\Support\Uploads\UploadRefused;

/**
 * Opens an encrypted PDF with the EMPTY user password, the way a reader does for a PDF that "opens without a
 * password" (Michael 2026-11-18): only so the inspector can look inside its encrypted object streams. Nothing is
 * written back; the stored file stays as uploaded and is flagged "encrypted".
 *
 * Standard security handler only (ISO 32000-2, 7.6.4): V1/R2 RC4-40, V2/R3 RC4-40..128, V4/R4 RC4-128 or AES-128,
 * V5/R5 and V5/R6 AES-256. A password-protected file, a public-key handler or any unknown variant is refused.
 */
final class PdfDecryptor
{
    private const PAD = "\x28\xBF\x4E\x5E\x4E\x75\x8A\x41\x64\x00\x4E\x56\xFF\xFA\x01\x08"
        ."\x2E\x2E\x00\xB6\xD0\x68\x3E\x80\x2F\x0C\xA9\xFE\x64\x53\x69\x7A";

    /**
     * @param  'rc4'|'aes128'|'aes256'|'identity'  $method
     */
    private function __construct(
        private readonly string $key,
        private readonly string $method,
        private readonly bool $encryptMetadata,
    ) {}

    public static function open(string $pdf): self
    {
        if (preg_match_all('/\/Encrypt[ \t\r\n\f\0]+(\d+)[ \t\r\n\f\0]+(\d+)[ \t\r\n\f\0]+R/', $pdf, $refs, PREG_SET_ORDER) < 1) {
            throw new UploadRefused('pdf_unsupported');
        }
        $targets = array_unique(array_map(static fn (array $r): string => $r[1].' '.$r[2], $refs));
        if (count($targets) !== 1) {
            throw new UploadRefused('pdf_unsupported');
        }
        [$num, $gen] = array_map('intval', explode(' ', (string) reset($targets)));
        $dict = PdfSyntax::objectText($pdf, $num, $gen);
        if ($dict === null) {
            throw new UploadRefused('pdf_unsupported');
        }

        $names = PdfSyntax::names($dict);
        $outer = PdfSyntax::outerOnly($dict);
        $v = PdfSyntax::intValue($outer, 'V');
        $r = PdfSyntax::intValue($outer, 'R');
        $p = PdfSyntax::intValue($outer, 'P');
        $o = PdfSyntax::stringValue($dict, 'O');
        $u = PdfSyntax::stringValue($dict, 'U');
        if (! isset($names['Standard']) || $v === null || $r === null || $p === null || $o === null || $u === null) {
            throw new UploadRefused('pdf_unsupported');
        }
        $encryptMetadata = preg_match('/\/EncryptMetadata[ \t\r\n\f\0]+false/', $dict) !== 1;

        // Which cipher protects the streams.
        if ($v >= 4) {
            if (preg_match('/\/StmF[ \t\r\n\f\0]*\/Identity/', $dict) === 1) {
                $method = 'identity';
            } elseif (isset($names['AESV3']) && $v === 5) {
                $method = 'aes256';
            } elseif (isset($names['AESV2']) && $v === 4) {
                $method = 'aes128';
            } elseif (isset($names['V2']) && $v === 4) {
                $method = 'rc4';
            } else {
                throw new UploadRefused('pdf_unsupported');
            }
        } elseif ($v === 1 || $v === 2) {
            $method = 'rc4';
        } else {
            throw new UploadRefused('pdf_unsupported');
        }

        if ($v === 5) {
            if (! in_array($r, [5, 6], true) || strlen($u) < 48) {
                throw new UploadRefused('pdf_unsupported');
            }
            $ue = PdfSyntax::stringValue($dict, 'UE');
            if ($ue === null || strlen($ue) < 32) {
                throw new UploadRefused('pdf_unsupported');
            }

            return new self(self::keyR56($r, $u, substr($ue, 0, 32)), $method, $encryptMetadata);
        }

        if (! in_array($r, [2, 3, 4], true)) {
            throw new UploadRefused('pdf_unsupported');
        }
        $id = self::firstId($pdf);
        if ($id === null) {
            throw new UploadRefused('pdf_unsupported');
        }
        $bits = $v === 1 ? 40 : ($v === 4 ? 128 : (PdfSyntax::intValue($outer, 'Length') ?? 40));
        if ($bits < 40 || $bits > 128 || $bits % 8 !== 0) {
            throw new UploadRefused('pdf_unsupported');
        }

        return new self(self::keyR234($r, $bits / 8, $o, $u, $p, $id, $encryptMetadata), $method, $encryptMetadata);
    }

    /**
     * The decrypted data of one stream, or null when it does not decrypt (wrong padding or length).
     *
     * @param  array<string, true>  $dictNames
     */
    public function stream(string $data, int $num, int $gen, array $dictNames): ?string
    {
        // Never encrypted: cross-reference streams; metadata when /EncryptMetadata is false (7.6.2).
        if ($this->method === 'identity' || isset($dictNames['XRef']) || (! $this->encryptMetadata && isset($dictNames['Metadata']))) {
            return $data;
        }
        if ($this->method === 'aes256') {
            return self::aes('aes-256-cbc', $this->key, $data);
        }

        // Algorithm 1: one key per object.
        $salt = $this->method === 'aes128' ? 'sAlT' : '';
        $objectKey = substr(md5($this->key.substr(pack('V', $num), 0, 3).substr(pack('V', $gen), 0, 2).$salt, true), 0, min(strlen($this->key) + 5, 16));

        return $this->method === 'aes128' ? self::aes('aes-128-cbc', $objectKey, $data) : self::rc4($objectKey, $data);
    }

    /** Algorithms 2 and 4/5 with the empty user password; refused when the user password is not empty. */
    private static function keyR234(int $r, int $length, string $o, string $u, int $p, string $id, bool $encryptMetadata): string
    {
        $input = self::PAD.substr($o, 0, 32).pack('V', $p & 0xFFFFFFFF).$id;
        if ($r >= 4 && ! $encryptMetadata) {
            $input .= "\xFF\xFF\xFF\xFF";
        }
        $hash = md5($input, true);
        if ($r >= 3) {
            for ($i = 0; $i < 50; $i++) {
                $hash = md5(substr($hash, 0, $length), true);
            }
        }
        $key = substr($hash, 0, $r === 2 ? 5 : $length);

        if ($r === 2) {
            $ok = hash_equals(substr($u, 0, 32), self::rc4($key, self::PAD));
        } else {
            $x = self::rc4($key, md5(self::PAD.$id, true));
            for ($i = 1; $i <= 19; $i++) {
                $x = self::rc4($key ^ str_repeat(chr($i), strlen($key)), $x);
            }
            $ok = hash_equals(substr($u, 0, 16), $x);
        }
        if (! $ok) {
            throw new UploadRefused('pdf_password');
        }

        return $key;
    }

    /** Algorithm 2.A with the empty user password (R5: SHA-256; R6: algorithm 2.B). */
    private static function keyR56(int $r, string $u, string $ue): string
    {
        $hash = substr($u, 0, 32);
        $validationSalt = substr($u, 32, 8);
        $keySalt = substr($u, 40, 8);

        $check = $r === 5 ? hash('sha256', $validationSalt, true) : self::hash2b('', $validationSalt);
        if (! hash_equals($hash, $check)) {
            throw new UploadRefused('pdf_password');
        }
        $intermediate = $r === 5 ? hash('sha256', $keySalt, true) : self::hash2b('', $keySalt);
        $key = openssl_decrypt($ue, 'aes-256-cbc', $intermediate, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\0", 16));
        if ($key === false || strlen($key) !== 32) {
            throw new UploadRefused('pdf_unsupported');
        }

        return $key;
    }

    /** Algorithm 2.B (ISO 32000-2, 7.6.4.3.4), for the user password: no user data. */
    private static function hash2b(string $password, string $salt): string
    {
        $k = hash('sha256', $password.$salt, true);
        $round = 0;
        while (true) {
            $k1 = str_repeat($password.$k, 64);
            $e = openssl_encrypt($k1, 'aes-128-cbc', substr($k, 0, 16), OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, substr($k, 16, 16));
            if ($e === false) {
                throw new UploadRefused('pdf_unsupported');
            }
            $sum = 0;
            for ($i = 0; $i < 16; $i++) {
                $sum += ord($e[$i]);
            }
            $k = hash(['sha256', 'sha384', 'sha512'][$sum % 3], $e, true);
            $round++;
            if ($round >= 64 && ord($e[strlen($e) - 1]) <= $round - 32) {
                break;
            }
        }

        return substr($k, 0, 32);
    }

    private static function firstId(string $pdf): ?string
    {
        if (preg_match_all('/\/ID[ \t\r\n\f\0]*\[[ \t\r\n\f\0]*(<[0-9A-Fa-f \t\r\n]*>|\((?:\\\\.|[^\\\\)])*\))/s', $pdf, $m) < 1) {
            return null;
        }

        return PdfSyntax::stringValue('/ID '.end($m[1]), 'ID');
    }

    private static function aes(string $cipher, string $key, string $data): ?string
    {
        if (strlen($data) < 32 || strlen($data) % 16 !== 0) {
            return null;
        }
        $plain = openssl_decrypt(substr($data, 16), $cipher, $key, OPENSSL_RAW_DATA, substr($data, 0, 16));

        return $plain === false ? null : $plain;
    }

    private static function rc4(string $key, string $data): string
    {
        $s = range(0, 255);
        $keyLength = strlen($key);
        $j = 0;
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $s[$i] + ord($key[$i % $keyLength])) & 0xFF;
            [$s[$i], $s[$j]] = [$s[$j], $s[$i]];
        }
        $out = '';
        $i = $j = 0;
        $n = strlen($data);
        for ($k = 0; $k < $n; $k++) {
            $i = ($i + 1) & 0xFF;
            $j = ($j + $s[$i]) & 0xFF;
            $t = $s[$i];
            $s[$i] = $s[$j];
            $s[$j] = $t;
            $out .= chr(ord($data[$k]) ^ $s[($s[$i] + $s[$j]) & 0xFF]);
        }

        return $out;
    }
}
