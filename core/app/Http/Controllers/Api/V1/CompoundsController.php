<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UnitState;
use App\Http\Controllers\Controller;
use App\Http\Resources\UnitCardResource;
use App\Models\Area;
use App\Models\Compound;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Compound pages (PLAN 1.2, 2.5, 5.1 point 4): the index with each compound's published units, and one compound with
 * its developer, place and intro and its unit cards. Indexed from one published unit (Q14; the web page decides from
 * `units`). Names and places only: no address, no map.
 */
class CompoundsController extends Controller
{
    public function index(): JsonResponse
    {
        $locale = app()->getLocale();
        $counts = Unit::query()->where('state', UnitState::Published->value)->whereNotNull('compound_id')
            ->selectRaw('compound_id, count(*) as n')->groupBy('compound_id')->pluck('n', 'compound_id')
            ->map(fn (mixed $n): int => is_numeric($n) ? (int) $n : 0);

        $rows = Compound::query()->active()->with(['developer', 'area.parent.parent'])
            ->orderBy($locale === 'en' ? 'name_en' : 'name_ar')->get()
            ->map(fn (Compound $c): array => [
                'slug' => $c->slug,
                'name' => $c->nameFor($locale),
                'developer' => $c->developer ? ['slug' => $c->developer->slug, 'name' => $c->developer->nameFor($locale)] : null,
                'place' => self::place($c->area, $locale),
                'units' => $counts->get($c->id, 0),
            ])->values()->all();

        return response()->json(['data' => $rows]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $locale = app()->getLocale();
        $compound = Compound::query()->active()->where('slug', $slug)->with(['developer', 'area.parent.parent'])->first();
        if (! $compound instanceof Compound) {
            throw new NotFoundHttpException;
        }
        $units = Unit::query()->where('state', UnitState::Published->value)->where('compound_id', $compound->id)
            ->with(UnitCardResource::WITH)->defaultOrder()->limit(60)->get();
        $intro = $locale === 'en' ? $compound->intro_en : $compound->intro_ar;

        return response()->json(['data' => [
            'slug' => $compound->slug,
            'name' => $compound->nameFor($locale),
            'developer' => $compound->developer ? ['slug' => $compound->developer->slug, 'name' => $compound->developer->nameFor($locale)] : null,
            'place' => self::place($compound->area, $locale),
            'intro' => is_string($intro) && trim($intro) !== '' ? $intro : null,
            'intro_is_placeholder' => (bool) $compound->intro_is_placeholder,
            'units' => UnitCardResource::collection($units)->resolve($request),
        ]]);
    }

    /**
     * Governorate, area and district names, broadest first.
     *
     * @return list<string>
     */
    private static function place(?Area $area, string $locale): array
    {
        $chain = [];
        for ($node = $area; $node instanceof Area && count($chain) < 3; $node = $node->parent) {
            array_unshift($chain, $node->nameFor($locale));
        }

        return $chain;
    }
}
