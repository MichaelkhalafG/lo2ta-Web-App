<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Compound;
use App\Models\Developer;
use App\Models\Feature;
use App\Support\Cache\VersionedCache;
use Illuminate\Http\JsonResponse;

/**
 * The managed lists the public site and the seller form read: areas (two levels), developers, compounds and
 * features. Active rows only, in the request's language, from the versioned file cache.
 */
class ReferenceController extends Controller
{
    public function areas(): JsonResponse
    {
        $locale = app()->getLocale();

        return response()->json(['data' => VersionedCache::remember('places', "areas:{$locale}", static function () use ($locale): array {
            $governorates = Area::query()->active()->whereNull('parent_id')->orderBy('sort')->orderBy('id')
                ->with(['children' => static fn ($q) => $q->where('is_active', true)->orderBy('sort')->orderBy('id')])
                ->get();

            $out = [];
            foreach ($governorates as $gov) {
                $areas = [];
                foreach ($gov->children as $area) {
                    $areas[] = ['slug' => $area->slug, 'code' => $area->code, 'name' => $area->nameFor($locale)];
                }
                $out[] = ['slug' => $gov->slug, 'name' => $gov->nameFor($locale), 'areas' => $areas];
            }

            return $out;
        })]);
    }

    public function developers(): JsonResponse
    {
        $locale = app()->getLocale();

        return response()->json(['data' => VersionedCache::remember('places', "developers:{$locale}", static function () use ($locale): array {
            return Developer::query()->active()->orderBy($locale === 'en' ? 'name_en' : 'name_ar')->get()
                ->map(static fn (Developer $d): array => ['slug' => $d->slug, 'name' => $d->nameFor($locale)])
                ->values()->all();
        })]);
    }

    public function compounds(): JsonResponse
    {
        $locale = app()->getLocale();

        return response()->json(['data' => VersionedCache::remember('places', "compounds:{$locale}", static function () use ($locale): array {
            return Compound::query()->active()->with(['developer', 'area'])
                ->orderBy($locale === 'en' ? 'name_en' : 'name_ar')->get()
                ->map(static fn (Compound $c): array => [
                    'slug' => $c->slug,
                    'name' => $c->nameFor($locale),
                    'developer' => $c->developer?->slug,
                    'area' => $c->area?->slug,
                ])->values()->all();
        })]);
    }

    public function features(): JsonResponse
    {
        $locale = app()->getLocale();

        return response()->json(['data' => VersionedCache::remember('places', "features:{$locale}", static function () use ($locale): array {
            return Feature::query()->active()->orderBy('sort')->orderBy('id')->get()
                ->map(static fn (Feature $f): array => [
                    'code' => $f->code,
                    'group' => $f->group->value,
                    'name' => $f->nameFor($locale),
                    'applies_to' => $f->applies_to,
                ])->values()->all();
        })]);
    }
}
