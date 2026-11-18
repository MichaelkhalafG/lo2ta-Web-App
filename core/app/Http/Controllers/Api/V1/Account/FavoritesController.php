<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Favorites (PLAN 2.3): a heart on cards and unit pages; "My favorites" lists them and marks the ones no longer
 * available. A unit can be saved only while it is public; a saved unit that leaves the market keeps its row so the
 * buyer sees it went, with its code only.
 */
class FavoritesController extends Controller
{
    private const WITH = UnitResource::WITH;

    public function index(Request $request): JsonResponse
    {
        $user = MeController::user($request);
        $units = $user->favorites()->with(self::WITH)->orderByPivot('created_at', 'desc')->get();

        $data = [];
        foreach ($units as $unit) {
            $data[] = $unit->state->isPublic()
                ? ['available' => true, 'unit' => (new UnitResource($unit))->resolve($request)]
                : ['available' => false, 'unit' => ['code' => $unit->code]];
        }

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = MeController::user($request);
        $request->validate(['code' => ['required', 'string', 'max:16']]);
        $unit = Unit::query()->public()->where('code', strtoupper($request->string('code')->toString()))->first();
        if (! $unit instanceof Unit) {
            throw ValidationException::withMessages(['code' => __('accounts.unit_unavailable')]);
        }
        $user->favorites()->syncWithoutDetaching([$unit->id => ['created_at' => now()]]);

        return response()->json(['saved' => true, 'code' => $unit->code], 201);
    }

    public function destroy(Request $request, string $code): Response
    {
        $user = MeController::user($request);
        $unit = Unit::query()->where('code', strtoupper($code))->first();
        if ($unit instanceof Unit) {
            $user->favorites()->detach($unit->id);
        }

        return response()->noContent();
    }
}
