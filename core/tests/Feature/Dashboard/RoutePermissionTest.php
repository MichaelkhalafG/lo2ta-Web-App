<?php

use App\Models\AdminUser;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;

/*
| Security first on every dashboard route (PLAN 2.7): a signed-in route needs the admin guard and the active-account
| check, and either a `can:` ability or a place on the short list below of routes open to both roles on purpose.
| A new route that is neither fails this test, so nothing reaches the dashboard unchecked.
*/

/** Signed-out routes: the sign-in page, the language switch and the staff set-password link (G13). */
const DASHBOARD_GUEST_ROUTES = ['dashboard.locale', 'dashboard.login', 'dashboard.login.store', 'dashboard.password.edit', 'dashboard.password.update'];

/** Signed-in routes open to both roles: the overview, sign-out, and each person's own account. */
const DASHBOARD_BOTH_ROLES = ['dashboard.home', 'dashboard.logout', 'dashboard.account', 'dashboard.account.password'];

/**
 * @return list<Route>
 */
function dashboardRoutes(): array
{
    return array_values(array_filter(
        Router::getRoutes()->getRoutes(),
        static fn (Route $r): bool => str_starts_with((string) $r->getName(), 'dashboard.'),
    ));
}

it('puts every signed-in dashboard route behind the admin guard and the active-account check', function () {
    foreach (dashboardRoutes() as $route) {
        if (in_array($route->getName(), DASHBOARD_GUEST_ROUTES, true)) {
            continue;
        }
        $middleware = $route->gatherMiddleware();
        expect(in_array('auth:admin', $middleware, true))->toBeTrue("{$route->getName()} lacks auth:admin")
            ->and(in_array('admin.active', $middleware, true))->toBeTrue("{$route->getName()} lacks admin.active");
    }
});

it('gives every signed-in route an ability check, except the few open to both roles on purpose', function () {
    $open = [];
    foreach (dashboardRoutes() as $route) {
        $name = (string) $route->getName();
        if (in_array($name, DASHBOARD_GUEST_ROUTES, true)) {
            continue;
        }
        $abilities = array_filter($route->gatherMiddleware(), static fn ($m): bool => is_string($m) && str_starts_with($m, 'can:'));
        if ($abilities === []) {
            $open[] = $name;
        }
    }
    sort($open);
    $expected = DASHBOARD_BOTH_ROLES;
    sort($expected);

    expect($open)->toBe($expected);
});

it('refuses a reviewer every admin-only screen', function () {
    $reviewer = AdminUser::factory()->create();
    $checked = 0;
    foreach (dashboardRoutes() as $route) {
        $abilities = array_filter($route->gatherMiddleware(), static fn ($m): bool => is_string($m) && str_starts_with($m, 'can:') && $m !== 'can:view-documents');
        if ($abilities === [] || ! in_array('GET', $route->methods(), true) || str_contains($route->uri(), '{')) {
            continue;
        }
        $this->actingAs($reviewer, 'admin')->get('/'.$route->uri())->assertForbidden();
        $checked++;
    }

    expect($checked)->toBeGreaterThanOrEqual(4);
});
