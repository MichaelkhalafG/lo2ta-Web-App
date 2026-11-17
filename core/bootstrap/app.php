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
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

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
        // API guests get a JSON 401 (null = no redirect), never the dashboard login page.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : route('dashboard.login'));
        $middleware->redirectUsersTo(fn () => route('dashboard.home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || ($request->expectsJson() && ! $request->hasHeader('X-Inertia')),
        );

        // Dashboard errors render the dashboard's own Error page in the reader's language. 500 and 503 keep
        // Laravel's debug page while debugging, so a developer still sees the trace.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();
            $dashboard = $request->is('dashboard', 'dashboard/*');
            $shown = in_array($status, [403, 404, 419], true) || (in_array($status, [500, 503], true) && ! config('app.debug'));

            if ($dashboard && $shown && ! $request->is('api/*')) {
                return Inertia::render('Error', ['status' => $status])->toResponse($request)->setStatusCode($status);
            }

            return $response;
        });
    })->create();
