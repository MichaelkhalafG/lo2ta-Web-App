import type { PageProps as InertiaPageProps } from '@inertiajs/core';

export type Locale = 'ar' | 'en';

export interface SharedProps {
    locale: Locale;
    dir: 'rtl' | 'ltr';
    locales: Locale[];
}

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: SharedProps & InertiaPageProps;
    }
}

interface ImportMetaEnv {
    readonly VITE_APP_NAME?: string;
}
