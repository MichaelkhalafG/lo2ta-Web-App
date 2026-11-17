<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UnitState;
use App\Http\Controllers\Controller;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Phase 1 skeleton: published units in the default order, and one unit by code. Filters, sort options and the
 * cached card answers come in phase 5 (PLAN 7).
 */
class UnitsController extends Controller
{
    private const PER_PAGE = 24;

    private const WITH = ['developer', 'compound', 'area', 'features', 'photos'];

    public function index(): AnonymousResourceCollection
    {
        $units = Unit::query()
            ->where('state', UnitState::Published->value)
            ->with(self::WITH)
            ->defaultOrder()
            ->paginate(self::PER_PAGE);

        return UnitResource::collection($units);
    }

    public function show(string $code): UnitResource
    {
        $unit = Unit::query()
            ->where('code', strtoupper($code))
            ->public()
            ->with(self::WITH)
            ->firstOrFail();

        return new UnitResource($unit);
    }
}
