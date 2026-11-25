<?php

use App\Http\Controllers\Api\V1\Account\AuthController;
use App\Http\Controllers\Api\V1\Account\BudgetController;
use App\Http\Controllers\Api\V1\Account\FavoritesController;
use App\Http\Controllers\Api\V1\Account\MeController;
use App\Http\Controllers\Api\V1\Account\RequestsController;
use App\Http\Controllers\Api\V1\ChoicesController;
use App\Http\Controllers\Api\V1\CompoundsController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\FormsController;
use App\Http\Controllers\Api\V1\ReferenceController;
use App\Http\Controllers\Api\V1\Seller\DocumentsController as SellerDocumentsController;
use App\Http\Controllers\Api\V1\Seller\PhotosController as SellerPhotosController;
use App\Http\Controllers\Api\V1\Seller\UnitsController as SellerUnitsController;
use App\Http\Controllers\Api\V1\ToolsController;
use App\Http\Controllers\Api\V1\UnitsController;
use App\Support\Seller\SellerUnits;
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

        Route::get('compound-pages', [CompoundsController::class, 'index']);
        Route::get('compound-pages/{slug}', [CompoundsController::class, 'show'])->where('slug', '[a-z0-9-]{1,96}');

        // Public forms (PLAN 2.6): honeypot and hourly limits per IP; nothing is sent back to the sender.
        Route::post('contact', [FormsController::class, 'contact']);
        Route::post('partnerships', [FormsController::class, 'partnership']);

        // Tools (PLAN 4, Phase 6): the calculator stores nothing (J12) and is limited per address and hour.
        Route::get('market-values', [ToolsController::class, 'marketValues']);
        Route::get('indicators', [ToolsController::class, 'indicators']);
        Route::post('calculator/seller', [ToolsController::class, 'seller']);
        Route::post('calculator/buyer', [ToolsController::class, 'buyer']);

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
            Route::put('me', [MeController::class, 'update'])->middleware('account.write');
            Route::get('me/favorites', [FavoritesController::class, 'index']);
            Route::post('me/favorites', [FavoritesController::class, 'store'])->middleware('account.write');
            Route::delete('me/favorites/{code}', [FavoritesController::class, 'destroy'])->where('code', '[A-Za-z0-9-]{3,16}')->middleware('account.write');
            Route::get('me/budget', [BudgetController::class, 'show']);
            Route::put('me/budget', [BudgetController::class, 'update'])->middleware('account.write');
            Route::get('me/requests', [RequestsController::class, 'index']);
            Route::post('me/requests', [RequestsController::class, 'store']);

            // The seller wizard (PLAN 2.2, 2.2.15). {unit}, {document} and {photo} are public IDs, looked up together
            // with the owner (SellerUnits): another seller's ID answers 404.
            Route::get('me/units', [SellerUnitsController::class, 'index']);
            Route::post('me/units', [SellerUnitsController::class, 'store']);
            Route::prefix('me/units/{unit}')->where([
                'unit' => SellerUnits::ID, 'document' => SellerUnits::ID, 'photo' => SellerUnits::ID,
            ])->group(function (): void {
                Route::get('/', [SellerUnitsController::class, 'show']);
                Route::delete('/', [SellerUnitsController::class, 'destroy']);
                Route::put('steps/{step}', [SellerUnitsController::class, 'saveStep'])->whereNumber('step');
                Route::post('submit', [SellerUnitsController::class, 'submit']);
                Route::get('exit-report', [SellerUnitsController::class, 'exitReport']);
                Route::put('live', [SellerUnitsController::class, 'liveEdit']);
                Route::post('withdraw', [SellerUnitsController::class, 'withdraw']);
                Route::post('documents', [SellerDocumentsController::class, 'store']);
                Route::get('documents/{document}', [SellerDocumentsController::class, 'show']);
                Route::delete('documents/{document}', [SellerDocumentsController::class, 'destroy']);
                Route::post('photos', [SellerPhotosController::class, 'store']);
                Route::put('photos', [SellerPhotosController::class, 'update']);
                Route::get('photos/{photo}', [SellerPhotosController::class, 'show']);
                Route::delete('photos/{photo}', [SellerPhotosController::class, 'destroy']);
            });
        });
    });
});
