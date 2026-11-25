<?php

use App\Http\Controllers\Dashboard\ActivityController;
use App\Http\Controllers\Dashboard\AuthController;
use App\Http\Controllers\Dashboard\CalculatorController;
use App\Http\Controllers\Dashboard\FilesController;
use App\Http\Controllers\Dashboard\IndicatorsController;
use App\Http\Controllers\Dashboard\LocaleController;
use App\Http\Controllers\Dashboard\OverviewController;
use App\Support\Seller\SellerUnits;
use Illuminate\Support\Facades\Route;

/*
| The core serves the API (routes/api.php) and the staff dashboard below. Public pages live in ../web.
| Every dashboard write is recorded in activity_log (handover 2.3).
*/

Route::get('/', fn () => redirect()->route('dashboard.home'));

Route::prefix('dashboard')->name('dashboard.')->group(function (): void {
    Route::post('locale', [LocaleController::class, 'update'])->name('locale');

    Route::middleware('guest:admin')->group(function (): void {
        Route::get('login', [AuthController::class, 'create'])->name('login');
        Route::post('login', [AuthController::class, 'store'])->name('login.store');
    });

    Route::middleware(['auth:admin', 'admin.active'])->group(function (): void {
        Route::get('/', OverviewController::class)->name('home');
        Route::post('logout', [AuthController::class, 'destroy'])->name('logout');
        Route::get('activity', ActivityController::class)->middleware('can:view-activity')->name('activity');

        // Calculator settings and the reports page's indicators (Phase 6): admin only, like every setting.
        Route::middleware('can:manage-settings')->group(function (): void {
            Route::get('calculator', [CalculatorController::class, 'index'])->name('calculator');
            Route::put('calculator/display', [CalculatorController::class, 'updateDisplay'])->name('calculator.display');
            Route::prefix('calculator/values/{key}')->where(['key' => '[a-z_]{1,48}', 'source' => '[0-9]+'])->group(function (): void {
                Route::put('/', [CalculatorController::class, 'updateValue'])->name('calculator.value');
                Route::post('sources', [CalculatorController::class, 'storeSource'])->name('calculator.sources.store');
                Route::put('sources/{source}', [CalculatorController::class, 'updateSource'])->name('calculator.sources.update');
                Route::delete('sources/{source}', [CalculatorController::class, 'destroySource'])->name('calculator.sources.destroy');
            });
            Route::post('calculator/policies', [CalculatorController::class, 'storePolicy'])->name('calculator.policies.store');
            Route::put('calculator/policies/{policy}', [CalculatorController::class, 'updatePolicy'])->whereNumber('policy')->name('calculator.policies.update');
            Route::delete('calculator/policies/{policy}', [CalculatorController::class, 'destroyPolicy'])->whereNumber('policy')->name('calculator.policies.destroy');

            Route::get('indicators', [IndicatorsController::class, 'index'])->name('indicators');
            Route::post('indicators', [IndicatorsController::class, 'store'])->name('indicators.store');
            Route::put('indicators/{indicator}', [IndicatorsController::class, 'update'])->whereNumber('indicator')->name('indicators.update');
            Route::delete('indicators/{indicator}', [IndicatorsController::class, 'destroy'])->whereNumber('indicator')->name('indicators.destroy');
        });
        Route::middleware('can:view-documents')->group(function (): void {
            Route::get('files/documents/{document}', [FilesController::class, 'document'])->where('document', SellerUnits::ID)->name('files.document');
            Route::get('files/photos/{photo}', [FilesController::class, 'photo'])->where('photo', SellerUnits::ID)->name('files.photo');
        });
    });
});
