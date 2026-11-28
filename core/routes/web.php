<?php

use App\Http\Controllers\Dashboard\AccountController;
use App\Http\Controllers\Dashboard\ActivityController;
use App\Http\Controllers\Dashboard\AuthController;
use App\Http\Controllers\Dashboard\CalculatorController;
use App\Http\Controllers\Dashboard\ChangesController;
use App\Http\Controllers\Dashboard\CommissionsController;
use App\Http\Controllers\Dashboard\DealsController;
use App\Http\Controllers\Dashboard\DuplicatesController;
use App\Http\Controllers\Dashboard\FilesController;
use App\Http\Controllers\Dashboard\IndicatorsController;
use App\Http\Controllers\Dashboard\LocaleController;
use App\Http\Controllers\Dashboard\MatchingController;
use App\Http\Controllers\Dashboard\OverviewController;
use App\Http\Controllers\Dashboard\PasswordLinkController;
use App\Http\Controllers\Dashboard\PeopleController;
use App\Http\Controllers\Dashboard\RequestsController;
use App\Http\Controllers\Dashboard\StaffController;
use App\Http\Controllers\Dashboard\UnitsController;
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
        // Units review (part 7.2, G1-G3, G19): both roles; publishing as verified and the badge are admin only (G1).
        Route::middleware('can:review-units')->prefix('units')->name('units')->group(function (): void {
            Route::get('/', [UnitsController::class, 'index']);
            Route::prefix('{unit}')->whereNumber(['unit', 'document', 'photo'])->group(function (): void {
                Route::get('/', [UnitsController::class, 'show'])->name('.show');
                Route::post('request-changes', [UnitsController::class, 'requestChanges'])->name('.request_changes');
                Route::post('reject', [UnitsController::class, 'reject'])->name('.reject');
                Route::post('publish', [UnitsController::class, 'publish'])->name('.publish');
                Route::put('deal-tag', [UnitsController::class, 'dealTag'])->name('.deal_tag');
                Route::put('fields', [UnitsController::class, 'fields'])->name('.fields');
                Route::put('places', [UnitsController::class, 'places'])->name('.places');
                Route::put('documents/{document}', [UnitsController::class, 'document'])->name('.document');
                Route::put('photos/{photo}', [UnitsController::class, 'photo'])->name('.photo');
                Route::post('withdrawal', [UnitsController::class, 'withdrawal'])->name('.withdrawal');
                Route::middleware('can:verify-units')->group(function (): void {
                    Route::post('publish-verified', [UnitsController::class, 'publishVerified'])->name('.publish_verified');
                    Route::put('verified', [UnitsController::class, 'verified'])->name('.verified');
                });
            });
        });
        Route::get('duplicates', DuplicatesController::class)->middleware('can:review-units')->name('duplicates');

        // Sellers and buyers, budgets, matching and the request inbox (part 7.4; G4, G9, G10).
        Route::middleware('can:work-clients')->group(function (): void {
            Route::get('people', [PeopleController::class, 'index'])->name('people');
            Route::get('people/{user}', [PeopleController::class, 'show'])->whereNumber('user')->name('people.show');
            Route::middleware('can:suspend-users')->whereNumber('user')->group(function (): void {
                Route::post('people/{user}/suspend', [PeopleController::class, 'suspend'])->name('people.suspend');
                Route::post('people/{user}/lift', [PeopleController::class, 'lift'])->name('people.lift');
            });
            Route::get('budgets', [MatchingController::class, 'budgets'])->name('budgets');
            Route::get('matching', [MatchingController::class, 'index'])->name('matching');
            Route::get('requests', [RequestsController::class, 'index'])->name('requests');
            Route::get('requests/{unitRequest}', [RequestsController::class, 'show'])->whereNumber('unitRequest')->name('requests.show');
            Route::put('requests/{unitRequest}', [RequestsController::class, 'update'])->whereNumber('unitRequest')->name('requests.update');

            // Deals (part 7.5; G7, G11, G18).
            Route::get('deals', [DealsController::class, 'index'])->name('deals');
            Route::post('deals', [DealsController::class, 'store'])->name('deals.store');
            Route::prefix('deals/{deal}')->whereNumber('deal')->name('deals.')->group(function (): void {
                Route::get('/', [DealsController::class, 'show'])->name('show');
                Route::post('forward', [DealsController::class, 'forward'])->name('forward');
                Route::post('back', [DealsController::class, 'back'])->name('back');
                Route::post('cancel', [DealsController::class, 'cancel'])->name('cancel');
            });
        });

        // Commissions (part 7.5; G7, G8): admin only.
        Route::middleware('can:manage-commissions')->group(function (): void {
            Route::get('commissions', [CommissionsController::class, 'index'])->name('commissions');
            Route::put('commissions/{commission}', [CommissionsController::class, 'update'])->whereNumber('commission')->name('commissions.update');
        });

        // After publishing (part 7.3, G15, G17): figure changes, free edits to check, documents added later.
        Route::middleware('can:review-units')->prefix('changes')->name('changes')->whereNumber(['change', 'unit'])->group(function (): void {
            Route::get('/', [ChangesController::class, 'index']);
            Route::post('{change}/approve', [ChangesController::class, 'approve'])->name('.approve');
            Route::post('{change}/reject', [ChangesController::class, 'reject'])->name('.reject');
            Route::post('units/{unit}/checked', [ChangesController::class, 'checked'])->name('.checked');
            Route::post('units/{unit}/restore-description', [ChangesController::class, 'restore'])->name('.restore');
        });

        Route::middleware('can:view-documents')->group(function (): void {
            Route::get('files/documents/{document}', [FilesController::class, 'document'])->where('document', SellerUnits::ID)->name('files.document');
            Route::get('files/photos/{photo}', [FilesController::class, 'photo'])->where('photo', SellerUnits::ID)->name('files.photo');
        });
    });
});
