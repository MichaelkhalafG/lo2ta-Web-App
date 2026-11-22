<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MainType;
use App\Enums\UnitState;
use App\Http\Controllers\Controller;
use App\Http\Resources\UnitCardResource;
use App\Http\Resources\UnitResource;
use App\Models\Area;
use App\Models\Unit;
use App\Support\Listing\UnitFacts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Published units for the opportunities page (PLAN 2.5 filters, 2.6) and one unit by code. Every filter value is
 * validated first; unknown values answer 422 rather than being ignored or reaching SQL.
 */
class UnitsController extends Controller
{
    public const PER_PAGE = 24;

    /** Sorts (PLAN D22): the default of 2.2.6, then the three a buyer asks for most. */
    public const SORTS = ['default', 'owner_low', 'installment_low', 'newest'];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', Rule::enum(MainType::class)],
            'area' => ['nullable', 'string', 'max:96', Rule::exists('areas', 'slug')->where('is_active', true)],
            'compound' => ['nullable', 'string', 'max:96', Rule::exists('compounds', 'slug')->where('is_active', true)],
            'owner_max' => ['nullable', 'integer', 'between:1,1000000000'],
            'installment_max' => ['nullable', 'integer', 'between:1,100000000'],
            'verified' => ['nullable', 'boolean'],
            'deal' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'page' => ['nullable', 'integer', 'between:1,1000'],
            'per_page' => ['nullable', 'integer', 'between:1,'.self::PER_PAGE],
        ]);

        $query = Unit::query()->where('state', UnitState::Published->value)->with(UnitCardResource::WITH);
        if ($request->filled('type')) {
            $query->where('main_type', $request->string('type')->toString());
        }
        if ($request->filled('area')) {
            $query->whereIn('area_id', self::areaAndBelow($request->string('area')->toString()));
        }
        if ($request->filled('compound')) {
            $slug = $request->string('compound')->toString();
            $query->whereHas('compound', fn (Builder $q) => $q->where('slug', $slug));
        }
        if ($request->filled('owner_max')) {
            $query->whereRaw('('.UnitFacts::OWNER_FROM_SQL.') <= ?', [$request->integer('owner_max')]);
        }
        if ($request->filled('installment_max')) {
            // A fully paid unit has no installment and always fits.
            $max = $request->integer('installment_max');
            $query->where(fn (Builder $q) => $q->whereNull('installment_amount')->orWhere('installment_amount', '<=', $max));
        }
        if ($request->boolean('verified')) {
            $query->whereNotNull('verified_at');
        }
        if ($request->boolean('deal')) {
            $query->where('deal_tag', true);
        }

        match ($request->filled('sort') ? $request->string('sort')->toString() : 'default') {
            'owner_low' => $query->orderByRaw(UnitFacts::OWNER_FROM_SQL.' asc')->orderByDesc('id'),
            'installment_low' => $query->orderByRaw('installment_amount IS NULL, installment_amount asc')->orderByDesc('id'),
            'newest' => $query->orderByDesc('published_at')->orderByDesc('id'),
            default => $query->defaultOrder(),
        };

        $perPage = $request->filled('per_page') ? $request->integer('per_page') : self::PER_PAGE;
        $page = $query->paginate($perPage);

        return response()->json([
            'data' => UnitCardResource::collection($page->getCollection())->resolve($request),
            'meta' => [
                'page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(string $code): UnitResource
    {
        $unit = Unit::query()
            ->where('code', strtoupper($code))
            ->public()
            ->with(UnitResource::WITH)
            ->firstOrFail();

        return new UnitResource($unit);
    }

    /**
     * The area and every level under it: a governorate takes its areas and their districts, an area its districts.
     *
     * @return list<int>
     */
    public static function areaAndBelow(string $slug): array
    {
        $area = Area::query()->where('slug', $slug)->first(['id']);
        if (! $area instanceof Area) {
            return [];
        }
        $ids = [$area->id];
        $level = [$area->id];
        for ($depth = 0; $depth < 2 && $level !== []; $depth++) {
            $level = [];
            foreach (Area::query()->whereIn('parent_id', $ids)->whereNotIn('id', $ids)->get(['id']) as $child) {
                $level[] = $child->id;
            }
            $ids = [...$ids, ...$level];
        }

        return $ids;
    }
}
