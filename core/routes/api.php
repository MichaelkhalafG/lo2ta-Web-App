<?php

use Illuminate\Support\Facades\Route;

/*
| Public API for the Next.js site. Versioned in the URL.
*/

Route::prefix('v1')->group(function (): void {
    Route::get('health', fn () => response()->json([
        'ok' => true,
        'locales' => array_keys((array) config('locales.supported')),
    ]));
});
