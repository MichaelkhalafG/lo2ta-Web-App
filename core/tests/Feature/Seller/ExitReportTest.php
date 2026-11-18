<?php

use App\Models\ExitReport;
use App\Models\MailOutbox;
use App\Models\SiteText;
use App\Models\User;
use App\Support\Calculator\ExitCalculator;
use App\Support\Calculator\ExitReportPdf;
use App\Support\Settings\Settings;
use App\Support\Uploads\Pdf\PdfInspector;
use App\Support\Uploads\UploadService;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/*
| The exit report PDF (PLAN 4.4, 2.2.14; Q8, Q17): generated at submit in Arabic and English, kept with its inputs,
| settings and results, private, attached to the seller's e-mail, marked as a draft while texts or rates are
| provisional.
*/

beforeEach(function () {
    Mail::fake();
    Storage::fake(UploadService::PRIVATE_DISK);
    $this->seed(ReferenceSeeder::class);
    $this->seller = User::factory()->create();
});

it('generates an Arabic and an English report at submit and attaches the seller one to the e-mail', function () {
    $unit = completeDraft($this->seller);

    $this->postJson("/api/v1/me/units/{$unit->public_id}/submit", declarations(), sellerHeaders($this->seller))->assertOk();

    $reports = ExitReport::query()->where('unit_id', $unit->id)->get();
    expect($reports->pluck('locale')->sort()->values()->all())->toBe(['ar', 'en'])
        ->and($reports->every(fn (ExitReport $r) => $r->kind === 'declared' && is_array($r->settings_snapshot) && isset($r->results['transfer']['net'])))->toBeTrue();

    $payload = MailOutbox::query()->where('template', 'seller.unit_received')->sole()->payload;
    expect($payload['exit_report_id'])->toBe($reports->firstWhere('locale', 'ar')?->id);
});

it('writes a real, passive PDF with the brand font embedded and the draft band while texts are placeholders', function () {
    $unit = completeDraft($this->seller, ['serial' => 124]);

    $report = app(ExitReportPdf::class)->generate($unit, 'declared', 'ar');
    $pdf = (string) Storage::disk(UploadService::PRIVATE_DISK)->get((string) $report?->pdf_path);

    expect($report?->pdf_path)->toMatch('/^exit-reports\/[a-f0-9]{40}\.pdf$/')
        ->and(substr($pdf, 0, 5))->toBe('%PDF-')
        ->and($pdf)->toContain('Alexandria')
        ->and($pdf)->toContain('/ToUnicode')
        ->and((new PdfInspector)->inspect($pdf))->toBe([]);

    // Once every text is final and no rate is provisional, the band goes.
    SiteText::query()->where('group', 'exit_report')->update(['is_placeholder' => false]);
    foreach (ExitCalculator::SETTINGS as $key) {
        app(Settings::class)->set($key, app(Settings::class)->get($key));
    }
    $final = app(ExitReportPdf::class)->generate($unit->refresh(), 'verified', 'en');
    expect($final?->results['provisional'])->toBe([]);
});

it('serves the report only to the unit owner', function () {
    $unit = completeDraft($this->seller);
    $this->postJson("/api/v1/me/units/{$unit->public_id}/submit", declarations(), sellerHeaders($this->seller))->assertOk();

    $own = $this->get("/api/v1/me/units/{$unit->public_id}/exit-report?locale=en", sellerHeaders($this->seller))->assertOk();
    expect($own->headers->get('Content-Disposition'))->toStartWith('attachment;');

    $other = User::factory()->create();
    $this->get("/api/v1/me/units/{$unit->public_id}/exit-report", sellerHeaders($other, '198.51.100.3'))->assertNotFound();
});
