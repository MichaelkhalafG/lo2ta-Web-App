<?php

use App\Http\Controllers\Api\V1\Account\AuthController;
use App\Http\Controllers\Api\V1\Account\BudgetController;
use App\Http\Controllers\Api\V1\Account\FavoritesController;
use App\Http\Controllers\Api\V1\Account\MeController;
use App\Http\Controllers\Api\V1\ChoicesController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\ReferenceController;
use App\Http\Controllers\Api\V1\UnitsController;
use Illuminate\Support\Facades\Route;

/*
| Public API for the Next.js site, versioned in the URL. Language: ?locale=ar|en (SetApiLocale).
| Everything except /health needs the server key (X-Api-Key). Account routes under /me also need the user's token
| (Authorization: Bearer, Sanctum; RUN D10); the Next server holds it in an httpOnly cookie.
| Before deploying, every route here must also be on the host's .htaccess allow-list, or Apache answers 404 before
| Laravel sees it (handover 2.4).
*/

Route::prefix('v1')->group(function (): void {
    Route::get('health', fn () => response()->json([
        'ok' => true,
        'locales' => array_keys((array) config('locales.supported')),
    ]));

    Route::middleware('server.key')->group(function (): void {
        Route::get('settings', [ContentController::class, 'settings']);
        Route::get('site-texts', [ContentController::class, 'siteTexts']);
        Route::get('choices', ChoicesController::class);

        Route::get('areas', [ReferenceController::class, 'areas']);
        Route::get('developers', [ReferenceController::class, 'developers']);
        Route::get('compounds', [ReferenceController::class, 'compounds']);
        Route::get('features', [ReferenceController::class, 'features']);

        Route::get('units', [UnitsController::class, 'index']);
        Route::get('units/{code}', [UnitsController::class, 'show'])->where('code', '[A-Za-z0-9-]{3,16}');

        // Accounts (PLAN 2.1): e-mail and password only.
        Route::prefix('auth')->group(function (): void {
            Route::post('register', [AuthController::class, 'register']);
            Route::post('login', [AuthController::class, 'login']);
            Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
            Route::post('reset-password', [AuthController::class, 'resetPassword']);
            Route::post('verify-email', [AuthController::class, 'verifyEmail']);
        });

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('auth/logout', [AuthController::class, 'logout']);
            Route::post('auth/resend-verification', [AuthController::class, 'resendVerification']);

            Route::get('me', [MeController::class, 'show']);
            Route::put('me', [MeController::class, 'update']);
            Route::get('me/favorites', [FavoritesController::class, 'index']);
            Route::post('me/favorites', [FavoritesController::class, 'store']);
            Route::delete('me/favorites/{code}', [FavoritesController::class, 'destroy'])->where('code', '[A-Za-z0-9-]{3,16}');
            Route::get('me/budget', [BudgetController::class, 'show']);
            Route::put('me/budget', [BudgetController::class, 'update']);
        });
    });
});
