<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\ReportIndicator;
use App\Support\Dashboard\Text;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The reports page's indicators (PLAN 1.3, PLAN 6 row 14, J9; admin only): figures the client supplies, each with
 * its date and an internal source (J10). None is invented: the block stays hidden on the site until one is
 * published. Every write is logged.
 */
class IndicatorsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Indicators/Index', [
            'indicators' => ReportIndicator::query()->orderBy('sort')->orderBy('id')->get()->map(static fn (ReportIndicator $i): array => [
                'id' => $i->id,
                'title_ar' => $i->title_ar,
                'title_en' => $i->title_en,
                'value' => $i->value,
                'unit' => $i->unit,
                'note_ar' => $i->note_ar,
                'note_en' => $i->note_en,
                'as_of' => $i->as_of->format('Y-m-d'),
                'source' => $i->source,
                'sort' => $i->sort,
                'is_published' => $i->is_published,
            ])->values()->all(),
            'units' => array_map(static fn (string $u): array => ['value' => $u, 'label' => Text::get("indicators.units.{$u}")], ReportIndicator::UNITS),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ReportIndicator::query()->create(self::data($request));

        return back()->with('status', Text::get('indicators.saved'));
    }

    public function update(Request $request, ReportIndicator $indicator): RedirectResponse
    {
        $indicator->fill(self::data($request))->save();

        return back()->with('status', Text::get('indicators.saved'));
    }

    public function destroy(ReportIndicator $indicator): RedirectResponse
    {
        $indicator->delete();

        return back()->with('status', Text::get('indicators.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private static function data(Request $request): array
    {
        $request->validate([
            'title_ar' => ['required', 'string', 'max:160'],
            'title_en' => ['required', 'string', 'max:160'],
            'value' => ['required', 'numeric', 'between:-9999999999999999,9999999999999999', 'decimal:0,2'],
            'unit' => ['required', Rule::in(ReportIndicator::UNITS)],
            'note_ar' => ['nullable', 'string', 'max:300'],
            'note_en' => ['nullable', 'string', 'max:300'],
            'as_of' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'source' => ['required', 'string', 'max:2000'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_published' => ['required', 'boolean'],
        ]);
        $admin = $request->user('admin');
        $optional = static function (string $key) use ($request): ?string {
            $text = $request->string($key)->trim()->toString();

            return $text === '' ? null : $text;
        };

        return [
            'title_ar' => $request->string('title_ar')->trim()->toString(),
            'title_en' => $request->string('title_en')->trim()->toString(),
            'value' => $request->string('value')->trim()->toString(),
            'unit' => $request->string('unit')->toString(),
            'note_ar' => $optional('note_ar'),
            'note_en' => $optional('note_en'),
            'as_of' => $request->string('as_of')->toString(),
            'source' => $request->string('source')->trim()->toString(),
            'sort' => $request->integer('sort'),
            'is_published' => $request->boolean('is_published'),
            'updated_by' => $admin instanceof AdminUser ? $admin->id : null,
        ];
    }
}
