<?php

use App\Support\Uploads\Pdf\PdfInspector;
use App\Support\Uploads\UploadRefused;

/*
| Michael's PDF rule (2026-11-18, PLAN 2.2.15): real PDFs only; active content refused wherever it hides; encrypted
| files that open without a password accepted and flagged; password-locked files refused. The fixtures come from
| pypdf (scripts/fixtures/make-pdf-fixtures.py), not from our own code.
*/

function pdfReason(string $bytes): ?string
{
    try {
        (new PdfInspector)->inspect($bytes);

        return null;
    } catch (UploadRefused $e) {
        return $e->reason;
    }
}

/** A minimal hand-written PDF around one object body, with a valid header, xref and trailer. */
function tinyPdf(string $objectBody, string $extra = ''): string
{
    return "%PDF-1.7\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
        ."3 0 obj\n{$objectBody}\nendobj\n{$extra}xref\n0 1\n0000000000 65535 f \ntrailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n0\n%%EOF\n";
}

it('accepts a plain PDF and a clean object stream without flags', function () {
    expect((new PdfInspector)->inspect(pdfFixture('clean.pdf')))->toBe([])
        ->and((new PdfInspector)->inspect(pdfFixture('objstm-clean.pdf')))->toBe([]);
});

it('refuses JavaScript, embedded files and JavaScript inside a compressed object stream', function () {
    expect(pdfReason(pdfFixture('js-openaction.pdf')))->toBe('pdf_active')
        ->and(pdfReason(pdfFixture('embedded-file.pdf')))->toBe('pdf_active')
        ->and(pdfReason(pdfFixture('objstm-js.pdf')))->toBe('pdf_active');
});

it('decrypts every standard scheme with the empty password and flags the file', function (string $scheme) {
    expect((new PdfInspector)->inspect(pdfFixture("enc-{$scheme}-clean.pdf")))->toBe(['encrypted']);
})->with(['rc4-40', 'rc4-128', 'aes-128', 'aes-256-r5', 'aes-256']);

it('finds JavaScript inside the encrypted object stream of every scheme', function (string $scheme) {
    expect(pdfReason(pdfFixture("enc-{$scheme}-js.pdf")))->toBe('pdf_active');
})->with(['rc4-40', 'rc4-128', 'aes-128', 'aes-256-r5', 'aes-256']);

it('refuses a PDF that needs a password to open, in every scheme', function (string $scheme) {
    expect(pdfReason(pdfFixture("enc-{$scheme}-password.pdf")))->toBe('pdf_password');
})->with(['rc4-40', 'rc4-128', 'aes-128', 'aes-256-r5', 'aes-256']);

it('decodes escaped names, so /J#61vaScript is JavaScript', function () {
    expect(pdfReason(tinyPdf('<< /S /J#61vaScript /J#53 (app.alert(1)) >>')))->toBe('pdf_active')
        ->and(pdfReason(tinyPdf('<< /Type /Annot /Subtype /Fil#65Attachment >>')))->toBe('pdf_active');
});

it('is not fooled by names inside strings or comments, nor by a fake stream keyword in a string', function () {
    // A real reader sees these names only as text, so they are harmless and must not cause a refusal.
    expect(pdfReason(tinyPdf("<< /Title (see /JavaScript in the manual) >>\n% /JS in a comment")))->toBeNull();

    // "stream" typed inside a string must not let the following real action slip past as stream data.
    $sneaky = "<< /Title (>> stream\n) /OpenAction << /S /JavaScript /JS (x) >> >>";
    expect(pdfReason(tinyPdf($sneaky)))->toBe('pdf_active');
});

it('finds an object hidden in another stream for readers that rebuild a broken file', function () {
    $hidden = "9 0 obj\n<< /S /Launch /F (cmd.exe) >>\nendobj\n";
    $stream = "4 0 obj\n<< /Length ".strlen($hidden)." >>\nstream\n{$hidden}\nendstream\nendobj\n";

    expect(pdfReason(tinyPdf('<< /Type /Note >>', $stream)))->toBe('pdf_active');
});

it('refuses object streams it cannot read with certainty', function () {
    $hex = bin2hex('900 0 << /S /JavaScript >>');
    $objStm = "4 0 obj\n<< /Type /ObjStm /N 1 /First 6 /Filter /ASCIIHexDecode /Length ".strlen($hex)." >>\nstream\n{$hex}\nendstream\nendobj\n";
    $badFlate = "5 0 obj\n<< /N 1 /First 6 /Filter /FlateDecode /Length 8 >>\nstream\nnotzlib!\nendstream\nendobj\n";

    expect(pdfReason(tinyPdf('<< /Type /Note >>', $objStm)))->toBe('pdf_unsupported')
        ->and(pdfReason(tinyPdf('<< /Type /Note >>', $badFlate)))->toBe('pdf_invalid');
});

it('refuses a decompression bomb inside an object stream', function () {
    // Built in pieces, so the test itself never holds the inflated size in memory.
    $deflate = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 9]);
    $bomb = deflate_add($deflate, '900 0 ', ZLIB_NO_FLUSH);
    $chunk = str_repeat(' ', 1024 * 1024);
    // A 1 MB cap proves the mechanism without holding the real 16 MB in the test process.
    for ($i = 0; $i <= 2; $i++) {
        $bomb .= deflate_add($deflate, $chunk, ZLIB_NO_FLUSH);
    }
    $bomb .= deflate_add($deflate, '', ZLIB_FINISH);
    expect(strlen($bomb))->toBeLessThan(100_000);
    $objStm = "4 0 obj\n<< /N 1 /First 6 /Filter /FlateDecode /Length ".strlen($bomb)." >>\nstream\n{$bomb}\nendstream\nendobj\n";

    try {
        (new PdfInspector(streamCap: 1024 * 1024))->inspect(tinyPdf('<< /Type /Note >>', $objStm));
        $reason = null;
    } catch (UploadRefused $e) {
        $reason = $e->reason;
    }
    expect($reason)->toBe('pdf_too_complex')->and(PdfInspector::STREAM_CAP)->toBe(16 * 1024 * 1024);
});

it('refuses what is not a PDF from the first byte', function () {
    expect(pdfReason('<?php system($_GET["c"]); ?>'))->toBe('pdf_invalid')
        ->and(pdfReason("junk before\n".pdfFixture('clean.pdf')))->toBe('pdf_invalid')
        ->and(pdfReason('%PDF-1.7 truncated'))->toBe('pdf_invalid');
});
