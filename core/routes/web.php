<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
| The core serves the API and the admin dashboard. Public pages live in the Next.js app (../web).
| The setup page below exists only locally, to prove the Inertia + React + RTL wiring; the real
| dashboard routes (behind admin auth) replace it in phase 1.
*/

Route::get('/', fn () => redirect('/dashboard'));

if (app()->isLocal()) {
    Route::get('/dashboard', fn () => Inertia::render('Setup', [
        'stack' => [
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
        ],
    ]))->name('dashboard.setup');
}
