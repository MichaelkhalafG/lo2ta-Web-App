<?php

use App\Http\Controllers\Dashboard\ActivityController;
use App\Http\Controllers\Dashboard\AuthController;
use App\Http\Controllers\Dashboard\LocaleController;
use App\Http\Controllers\Dashboard\OverviewController;
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
    });
});
