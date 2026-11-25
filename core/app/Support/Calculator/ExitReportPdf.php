<?php

namespace App\Support\Calculator;

use App\Models\ExitReport;
use App\Models\MarketValue;
use App\Models\SiteText;
use App\Models\Unit;
use App\Support\Uploads\UploadService;
use Illuminate\Support\Facades\Storage;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use RuntimeException;

/**
 * The exit report PDF (PLAN 4.4, 2.2.14; Q8, Q17): generated at submit from the declared figures ("as declared") and
 * again after review ("after review"), in Arabic and English. mPDF shapes Arabic and embeds Alexandria with a
 * Unicode map, so the text stays selectable. Every heading is a site text; while any of them is still a placeholder
 * or any rate is provisional, each page carries the "Draft: texts and rates not final" band.
 * The file is private (documents disk) and reaches only the seller and the team.
 */
final class ExitReportPdf
{
    public function __construct(private readonly ExitCalculator $calculator) {}

    /**
     * Generate and store one report; null while the unit's figures are incomplete.
     *
     * @param  'declared'|'verified'  $kind
     */
    public function generate(Unit $unit, string $kind, string $locale): ?ExitReport
    {
        $results = $this->calculator->forUnit($unit);
        if ($results === null) {
            return null;
        }
        $texts = SiteText::query()->where('group', 'exit_report')->get()->keyBy('key');
        $label = static function (string $key) use ($texts, $locale): string {
            $text = $texts->get('exit_report.'.$key);
            $value = $text instanceof SiteText ? ($locale === 'en' ? $text->en : $text->ar) : null;

            return is_string($value) && $value !== '' ? $value : $key;
        };
        $band = self::band($results['provisional'] !== [], $texts->contains(fn (SiteText $t): bool => $t->is_placeholder) || $texts->isEmpty());
        $draft = $band !== null;

        $html = view('pdf.exit-report', [
            'unit' => $unit,
            'r' => $results,
            'settings' => $results['settings'],
            // The month each market value was last checked (J10: the date, never the sources).
            'checked' => MarketValue::query()->get()->mapWithKeys(fn (MarketValue $v): array => [$v->key => $v->checked_on->format('Y-m')])->all(),
            'kind' => $kind,
            'locale' => $locale,
            'label' => $label,
            'draft' => $draft,
            'reference' => $unit->serial !== null ? sprintf('#%04d', $unit->serial) : '',
            'date' => now()->format('Y-m-d'),
        ])->render();

        $path = UploadService::randomName('exit-reports', 'pdf');
        Storage::disk(UploadService::PRIVATE_DISK)->put($path, self::render($html, $locale, $band !== null ? $label($band) : null));

        return $unit->exitReports()->create([
            'kind' => $kind,
            'locale' => $locale,
            'inputs' => $results['inputs'],
            'settings_snapshot' => $results['settings'],
            'results' => $results,
            'pdf_path' => $path,
            'generated_at' => now(),
        ]);
    }

    /**
     * The band names what is not final (Michael 2026-11-25): "texts and rates" while any value the figures used is
     * provisional, "texts" while only a placeholder text remains, none when both are final.
     */
    public static function band(bool $ratesProvisional, bool $textsPlaceholder): ?string
    {
        return $ratesProvisional ? 'draft_band' : ($textsPlaceholder ? 'draft_band_texts' : null);
    }

    private static function render(string $html, string $locale, ?string $band): string
    {
        $tmp = storage_path('app/mpdf');
        if (! is_dir($tmp)) {
            mkdir($tmp, 0755, true);
        }
        $config = (new ConfigVariables)->getDefaults();
        $fonts = (new FontVariables)->getDefaults();
        $fontDirs = is_array($config) ? ($config['fontDir'] ?? []) : [];
        $fontData = is_array($fonts) ? ($fonts['fontdata'] ?? []) : [];

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tmp,
            'fontDir' => [...(is_array($fontDirs) ? $fontDirs : []), resource_path('fonts')],
            'fontdata' => [
                ...(is_array($fontData) ? $fontData : []),
                'alexandria' => ['R' => 'Alexandria-Regular.ttf', 'B' => 'Alexandria-Bold.ttf', 'useOTL' => 0xFF, 'useKashida' => 75],
            ],
            'default_font' => 'alexandria',
            'directionality' => $locale === 'en' ? 'ltr' : 'rtl',
            'margin_top' => $band === null ? 16 : 24,
            'margin_bottom' => 16,
        ]);
        $mpdf->SetTitle('Lokta exit report');
        $mpdf->SetAuthor('Lokta');
        $mpdf->SetCreator('Lokta');
        if ($band !== null) {
            $mpdf->SetHTMLHeader('<div style="background:#7A5818;color:#FFFFFF;text-align:center;padding:6px;font-size:10pt;font-weight:bold;">'.e($band).'</div>');
        }
        $mpdf->WriteHTML($html);

        $bytes = $mpdf->OutputBinaryData();
        if (! is_string($bytes) || ! str_starts_with($bytes, '%PDF-')) {
            throw new RuntimeException('mPDF did not return a PDF.');
        }

        return $bytes;
    }
}
