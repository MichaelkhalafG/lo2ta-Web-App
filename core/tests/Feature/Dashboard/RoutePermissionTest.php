<?php

use App\Models\AdminUser;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
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

/** Abilities both roles hold (PLAN 2.4): files of a unit and the units review. Every other ability is admin only. */
const DASHBOARD_BOTH_ROLE_ABILITIES = ['can:view-documents', 'can:review-units', 'can:work-clients'];

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
        $abilities = array_filter($route->gatherMiddleware(), static fn ($m): bool => is_string($m) && str_starts_with($m, 'can:') && ! in_array($m, DASHBOARD_BOTH_ROLE_ABILITIES, true));
        if ($abilities === [] || ! in_array('GET', $route->methods(), true) || str_contains($route->uri(), '{')) {
            continue;
        }
        $this->actingAs($reviewer, 'admin')->get('/'.$route->uri())->assertForbidden();
        $checked++;
    }

    expect($checked)->toBeGreaterThanOrEqual(4);
});

it('grants a reviewer only the abilities meant for both roles', function () {
    $reviewer = AdminUser::factory()->create();
    $abilities = [];
    foreach (dashboardRoutes() as $route) {
        foreach ($route->gatherMiddleware() as $m) {
            if (is_string($m) && str_starts_with($m, 'can:')) {
                $abilities[substr($m, 4)] = true;
            }
        }
    }
    foreach (array_keys($abilities) as $ability) {
        expect(Gate::forUser($reviewer)->allows($ability))->toBe(in_array('can:'.$ability, DASHBOARD_BOTH_ROLE_ABILITIES, true), $ability);
    }
});
