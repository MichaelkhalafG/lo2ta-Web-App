import type { PageProps as InertiaPageProps } from '@inertiajs/core';

export type Locale = 'ar' | 'en';

/** Nested dashboard strings from lang/{locale}/dashboard.php. */
export interface Strings {
    [key: string]: string | Strings;
}

export interface SharedAdmin {
    name: string;
    email: string;
    initials: string;
    role: 'admin' | 'reviewer';
    roleLabel: string;
}

/** One sidebar entry, built on the server (App\Support\Dashboard\Navigation). `href` null = a later phase. */
export interface NavItem {
    key: string;
    label: string;
    icon: string;
    href: string | null;
    active: boolean;
    badge: number | null;
}

export interface NavGroup {
    key: string;
    label: string;
    items: NavItem[];
}

export interface Crumb {
    label: string;
    href?: string;
}

export interface SharedProps {
    locale: Locale;
    dir: 'rtl' | 'ltr';
    locales: Locale[];
    siteName: string;
    admin: SharedAdmin | null;
    nav: NavGroup[];
    abilities: Record<string, boolean>;
    t: Strings;
    flash: { status: string | null; warning: string | null; error: string | null };
}

/** The table contract of App\Support\Table\TableQuery. */
export interface TableMeta {
    page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number | null;
    to: number | null;
    sort: string | null;
    direction: 'asc' | 'desc';
    search: string | null;
    filters: Record<string, string | null>;
    sortable: string[];
    filterable: string[];
    virtual: string[];
    exportable: boolean;
}

export interface TablePayload<Row> {
    data: Row[];
    meta: TableMeta;
}

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: SharedProps & InertiaPageProps;
    }
}
