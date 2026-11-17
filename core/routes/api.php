<?php

use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\ReferenceController;
use App\Http\Controllers\Api\V1\UnitsController;
use Illuminate\Support\Facades\Route;

/*
| Public API for the Next.js site, versioned in the URL. Language: ?locale=ar|en (SetApiLocale).
| Everything except /health needs the server key (X-Api-Key). Before deploying, every route here must also be on
| the host's .htaccess allow-list, or Apache answers 404 before Laravel sees it (handover 2.4).
*/

Route::prefix('v1')->group(function (): void {
    Route::get('health', fn () => response()->json([
        'ok' => true,
        'locales' => array_keys((array) config('locales.supported')),
    ]));

    Route::middleware('server.key')->group(function (): void {
        Route::get('settings', [ContentController::class, 'settings']);
        Route::get('site-texts', [ContentController::class, 'siteTexts']);

        Route::get('areas', [ReferenceController::class, 'areas']);
        Route::get('developers', [ReferenceController::class, 'developers']);
        Route::get('compounds', [ReferenceController::class, 'compounds']);
        Route::get('features', [ReferenceController::class, 'features']);

        Route::get('units', [UnitsController::class, 'index']);
        Route::get('units/{code}', [UnitsController::class, 'show'])->where('code', '[A-Za-z0-9-]{3,16}');
    });
});
