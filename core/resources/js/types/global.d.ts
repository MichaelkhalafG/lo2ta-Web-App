import type { PageProps as InertiaPageProps } from '@inertiajs/core';

export type Locale = 'ar' | 'en';

/** Nested dashboard strings from lang/{locale}/dashboard.php. */
export interface Strings {
    [key: string]: string | Strings;
}

export interface SharedAdmin {
    name: string;
    email: string;
    role: 'admin' | 'reviewer';
    roleLabel: string;
}

export interface SharedProps {
    locale: Locale;
    dir: 'rtl' | 'ltr';
    locales: Locale[];
    siteName: string;
    admin: SharedAdmin | null;
    t: Strings;
    flash: { status: string | null };
}

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: SharedProps & InertiaPageProps;
    }
}
