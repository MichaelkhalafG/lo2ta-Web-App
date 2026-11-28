<?php

use App\Http\Controllers\Dashboard\AccountController;
use App\Http\Controllers\Dashboard\ActivityController;
use App\Http\Controllers\Dashboard\AuthController;
use App\Http\Controllers\Dashboard\CalculatorController;
use App\Http\Controllers\Dashboard\FilesController;
use App\Http\Controllers\Dashboard\IndicatorsController;
use App\Http\Controllers\Dashboard\LocaleController;
use App\Http\Controllers\Dashboard\OverviewController;
use App\Http\Controllers\Dashboard\PasswordLinkController;
use App\Http\Controllers\Dashboard\StaffController;
use App\Support\Seller\SellerUnits;
use Illuminate\Support\Facades\Route;

/*
| The core serves the API (routes/api.php) and the staff dashboard below. Public pages live in ../web.
| Every dashboard write is recorded in activity_log (handover 2.3). Every signed-in route checks the staff role on
| the server: a route without a `can:` ability is open to both roles on purpose, and RoutePermissionTest lists
| them, so a new route cannot slip in unchecked (PLAN 2.7).
*/

Route::get('/', fn () => redirect()->route('dashboard.home'));

Route::prefix('dashboard')->name('dashboard.')->group(function (): void {
    Route::post('locale', [LocaleController::class, 'update'])->name('locale');

    Route::middleware('guest:admin')->group(function (): void {
        Route::get('login', [AuthController::class, 'create'])->name('login');
        Route::post('login', [AuthController::class, 'store'])->name('login.store');
    });

    // A staff member's set-password link (G13, G14): one use, 60 minutes, signed out.
    Route::get('password/{token}', [PasswordLinkController::class, 'edit'])->where('token', '[a-f0-9]{64}')->name('password.edit');
    Route::post('password/{token}', [PasswordLinkController::class, 'update'])->where('token', '[a-f0-9]{64}')->name('password.update');

    Route::middleware(['auth:admin', 'admin.active'])->group(function (): void {
        Route::get('/', OverviewController::class)->name('home');
        Route::post('logout', [AuthController::class, 'destroy'])->name('logout');
        Route::get('activity', ActivityController::class)->middleware('can:view-activity')->name('activity');

        // Every staff member's own account and password (G13).
        Route::get('account', [AccountController::class, 'show'])->name('account');
        Route::put('account/password', [AccountController::class, 'updatePassword'])->name('account.password');

        // Staff management (admin only, G13, G14).
        Route::middleware('can:manage-admins')->group(function (): void {
            Route::get('staff', [StaffController::class, 'index'])->name('staff');
            Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
            Route::put('staff/{staff}', [StaffController::class, 'update'])->whereNumber('staff')->name('staff.update');
            Route::post('staff/{staff}/password-link', [StaffController::class, 'sendLink'])->whereNumber('staff')->name('staff.link');
        });

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
