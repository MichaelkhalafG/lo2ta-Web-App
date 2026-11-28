<?php

namespace App\Support\Dashboard;

use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The dashboard sidebar (PLAN 1.3), built on the server so each role sees only its screens (PLAN 2.4: the reviewer
 * has no settings, site texts, staff, commissions or activity log). An item without a route yet carries a null
 * href and renders as "later"; its phase builds it.
 *
 * @phpstan-type NavItem array{key: string, label: string, icon: string, href: string|null, active: bool, badge: int|null}
 * @phpstan-type NavGroup array{key: string, label: string, items: list<NavItem>}
 */
final class Navigation
{
    /**
     * [group => [[key, icon, route name or null, ability or null], …]]
     *
     * @var array<string, list<array{0: string, 1: string, 2: string|null, 3: string|null}>>
     */
    private const GROUPS = [
        'home' => [
            ['overview', 'LayoutDashboard', 'dashboard.home', null],
        ],
        'units' => [
            ['review_queue', 'ClipboardCheck', null, null],
            ['pending_changes', 'History', null, null],
            ['duplicates', 'Copy', null, null],
        ],
        'people' => [
            ['users', 'Users', null, null],
            ['budgets', 'Wallet', null, null],
        ],
        'deals' => [
            ['matching', 'Handshake', null, null],
            ['deals', 'Handshake', null, null],
            ['commissions', 'Percent', null, 'manage-commissions'],
        ],
        'inbox' => [
            ['unit_requests', 'Inbox', null, null],
            ['partnership_requests', 'Mail', null, null],
            ['contact_messages', 'Mail', null, null],
        ],
        'lists' => [
            ['compounds', 'Building2', null, null],
            ['developers', 'Building2', null, null],
            ['areas', 'MapPin', null, null],
            ['features', 'Sparkles', null, null],
        ],
        'content' => [
            ['reports', 'FileText', 'dashboard.indicators', 'manage-settings'],
            ['calculator', 'Calculator', 'dashboard.calculator', 'manage-settings'],
            ['site_texts', 'FileText', null, 'manage-site-texts'],
            ['settings', 'Settings2', null, 'manage-settings'],
        ],
        'admin' => [
            ['staff', 'ShieldCheck', 'dashboard.staff', 'manage-admins'],
            ['activity', 'History', 'dashboard.activity', 'view-activity'],
        ],
    ];

    /**
     * @return list<NavGroup>
     */
    public static function for(AdminUser $admin, Request $request): array
    {
        $current = $request->route()?->getName();
        $groups = [];

        foreach (self::GROUPS as $group => $items) {
            $out = [];
            foreach ($items as [$key, $icon, $route, $ability]) {
                if ($ability !== null && ! Gate::forUser($admin)->allows($ability)) {
                    continue;
                }
                $out[] = [
                    'key' => $key,
                    'label' => Text::get("nav_items.{$key}"),
                    'icon' => $icon,
                    'href' => $route !== null ? route($route, [], false) : null,
                    'active' => $route !== null && $current === $route,
                    'badge' => null,
                ];
            }
            if ($out !== []) {
                $groups[] = ['key' => $group, 'label' => Text::get("nav_groups.{$group}"), 'items' => $out];
            }
        }

        return $groups;
    }
}
