<?php

use App\Http\Middleware\EnsureAdminActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireServerKey;
use App\Http\Middleware\SetApiLocale;
use App\Http\Middleware\SetDashboardLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The language is set before Inertia shares the strings of that language.
        $middleware->web(append: [
            SetDashboardLocale::class,
            HandleInertiaRequests::class,
        ]);
        $middleware->api(append: [
            SetApiLocale::class,
        ]);
        $middleware->alias([
            'server.key' => RequireServerKey::class,
            'admin.active' => EnsureAdminActive::class,
        ]);
        // Guests of the dashboard go to its login; a signed-in admin on the login page goes home.
        $middleware->redirectGuestsTo(fn () => route('dashboard.login'));
        $middleware->redirectUsersTo(fn () => route('dashboard.home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
