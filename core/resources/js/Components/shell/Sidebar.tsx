import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    ChevronsLeft,
    ChevronsRight,
    Circle,
    ClipboardCheck,
    Copy,
    FileText,
    Handshake,
    History,
    Inbox,
    LayoutDashboard,
    Mail,
    MapPin,
    Percent,
    Settings2,
    ShieldCheck,
    Sparkles,
    Users,
    Wallet,
} from 'lucide-react';
import type { ComponentType } from 'react';

import { Wordmark } from '@/Components/shell/Wordmark';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { NavGroup, NavItem } from '@/types/global';

/**
 * Nav items name their icon as a string on the server (App\Support\Dashboard\Navigation). An explicit registry,
 * not `import * as Icons`, so only these icons reach the bundle.
 */
const ICONS: Record<string, ComponentType<{ className?: string | undefined }>> = {
    LayoutDashboard,
    ClipboardCheck,
    Copy,
    History,
    Users,
    Wallet,
    Handshake,
    Percent,
    Inbox,
    Mail,
    Building2,
    MapPin,
    Sparkles,
    FileText,
    Settings2,
    ShieldCheck,
};

function NavIcon({ name, className }: { name: string; className?: string }) {
    const Icon = ICONS[name] ?? Circle;

    return <Icon className={className} />;
}

function Item({ item, collapsed }: { item: NavItem; collapsed: boolean }) {
    const t = useT();
    const later = item.href === null;
    const shared = cn(
        'group relative flex items-center gap-3 px-3 py-2.5 text-sm transition-colors',
        collapsed && 'justify-center px-0',
        item.active
            ? 'bg-sidebar-accent text-foreground'
            : 'text-sidebar-foreground hover:bg-sidebar-accent hover:text-foreground',
        later && 'cursor-not-allowed text-sidebar-muted hover:bg-transparent hover:text-sidebar-muted',
    );

    const body = (
        <>
            {item.active ? <span aria-hidden="true" className="absolute inset-y-1.5 start-0 w-0.5 bg-foreground" /> : null}
            <NavIcon name={item.icon} className="h-[18px] w-[18px] shrink-0" />
            {collapsed ? null : <span className="truncate">{item.label}</span>}
            {!collapsed && later ? (
                <span className="ms-auto bg-sidebar-accent px-2 py-0.5 text-[11px] font-medium text-sidebar-muted">
                    {t('shell.later', 'لاحقًا')}
                </span>
            ) : null}
            {item.badge !== null && item.badge > 0 ? (
                collapsed ? (
                    <span
                        className="absolute end-1.5 top-1.5 h-2 w-2 rounded-full bg-warning"
                        aria-label={t('shell.new_count', { count: item.badge })}
                    />
                ) : (
                    <span
                        className="ms-auto min-w-[1.5rem] bg-warning-tint px-1.5 py-0.5 text-center text-[11px] font-semibold text-warning tabular-nums"
                        aria-label={t('shell.new_count', { count: item.badge })}
                    >
                        {item.badge}
                    </span>
                )
            ) : null}
        </>
    );

    if (later) {
        const note = t('shell.later_hint', 'تُبنى في مرحلة لاحقة.');

        return (
            <li>
                <span className={shared} aria-disabled="true" title={collapsed ? `${item.label} — ${note}` : note}>
                    {body}
                </span>
            </li>
        );
    }

    return (
        <li>
            <Link
                href={item.href ?? '#'}
                className={shared}
                aria-current={item.active ? 'page' : undefined}
                title={collapsed ? item.label : undefined}
            >
                {body}
            </Link>
        </li>
    );
}

function Group({ group, collapsed }: { group: NavGroup; collapsed: boolean }) {
    return (
        <div className="mb-6">
            <p className={cn('mb-2 px-3 text-[11px] font-semibold text-sidebar-muted', collapsed && 'px-0 text-center')}>
                {collapsed ? '•' : group.label}
            </p>
            <ul className="space-y-0.5">
                {group.items.map((item) => (
                    <Item key={item.key} item={item} collapsed={collapsed} />
                ))}
            </ul>
        </div>
    );
}

/** The navigation rail on the inline-start edge (right in Arabic); a drawer below lg. */
export function Sidebar({ collapsed, onToggle, inSheet = false }: { collapsed: boolean; onToggle?: () => void; inSheet?: boolean }) {
    const { nav } = usePage().props;
    const t = useT();

    return (
        <div
            className={cn(
                'flex h-full flex-col bg-sidebar text-sidebar-foreground',
                inSheet ? 'w-full' : 'fixed inset-y-0 start-0 z-40 hidden border-e border-sidebar-border lg:flex',
                !inSheet && (collapsed ? 'w-sidebar-rail' : 'w-sidebar'),
            )}
        >
            <div className={cn('flex h-header items-center gap-3 border-b border-sidebar-border px-4', collapsed && 'justify-center px-0')}>
                <Wordmark compact={collapsed} />
                {collapsed ? null : <span className="truncate text-xs text-sidebar-muted">{t('title')}</span>}
            </div>

            <nav aria-label={t('shell.main_menu', 'القائمة الرئيسية')} className="scrollbar-thin flex-1 overflow-y-auto px-3 py-5">
                {nav.map((group) => (
                    <Group key={group.key} group={group} collapsed={collapsed} />
                ))}
            </nav>

            {onToggle ? (
                <button
                    type="button"
                    onClick={onToggle}
                    className="flex h-11 items-center justify-center gap-2 border-t border-sidebar-border text-xs text-sidebar-muted transition-colors hover:bg-sidebar-accent hover:text-foreground"
                    aria-pressed={collapsed}
                >
                    {collapsed ? (
                        <ChevronsLeft className="h-4 w-4 rtl:rotate-180" />
                    ) : (
                        <>
                            <ChevronsRight className="h-4 w-4 rtl:rotate-180" />
                            <span>{t('shell.collapse_menu', 'تصغير القائمة')}</span>
                        </>
                    )}
                </button>
            ) : null}
        </div>
    );
}
