import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';

import { useT } from '@/lib/i18n';

interface Props {
    title: string;
    children: ReactNode;
}

/**
 * Shell for every dashboard page: document language and direction, the title, the language switch and the
 * signed-in admin. Deliberately unstyled until the design tokens arrive (phase 2).
 */
export default function DashboardLayout({ title, children }: Props) {
    const { locale, dir, siteName, admin } = usePage().props;
    const t = useT();

    // Inertia swaps pages without a reload, so keep <html lang dir> in step with the language.
    useEffect(() => {
        document.documentElement.lang = locale;
        document.documentElement.dir = dir;
    }, [locale, dir]);

    const otherLocale = locale === 'ar' ? 'en' : 'ar';

    return (
        <>
            <Head title={`${title} | ${siteName}`} />
            <header>
                <p>
                    {siteName} · {t('title')}
                </p>
                <nav aria-label={t('title')}>
                    <button type="button" lang={otherLocale} onClick={() => router.post('/dashboard/locale', { locale: otherLocale })}>
                        {t('switch_language')}
                    </button>
                    {admin && (
                        <>
                            <span>
                                {admin.name} ({admin.roleLabel})
                            </span>
                            <button type="button" onClick={() => router.post('/dashboard/logout')}>
                                {t('nav.logout')}
                            </button>
                        </>
                    )}
                </nav>
            </header>
            <main>{children}</main>
        </>
    );
}
