<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SiteText;
use App\Support\Cache\VersionedCache;
use App\Support\Settings\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public settings (definitions marked 'public') and site texts by group, in the request's language.
 * A text still marked as a placeholder is served like any other: the page works until the client's text arrives,
 * and the dashboard lists what is still waiting.
 */
class ContentController extends Controller
{
    public function settings(Settings $settings): JsonResponse
    {
        return response()->json(['data' => $settings->publicValues()]);
    }

    public function siteTexts(Request $request): JsonResponse
    {
        $request->validate([
            'group' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_.-]+$/'],
        ]);
        $group = $request->string('group')->toString();
        $locale = app()->getLocale();

        return response()->json(['data' => VersionedCache::remember('site_texts', "{$group}:{$locale}", static function () use ($group, $locale): array {
            $out = [];
            foreach (SiteText::query()->where('group', $group)->orderBy('key')->get() as $text) {
                $out[$text->key] = $text->textFor($locale);
            }

            return $out;
        })]);
    }
}
