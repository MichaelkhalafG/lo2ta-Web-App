import { Head, usePage } from '@inertiajs/react';
import { type PropsWithChildren, type ReactNode, useCallback, useEffect, useState } from 'react';

import { Header } from '@/Components/shell/Header';
import { Sidebar } from '@/Components/shell/Sidebar';
import { Alert } from '@/Components/ui/alert';
import { Dialog, Sheet } from '@/Components/ui/dialog';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Crumb } from '@/types/global';

const RAIL_KEY = 'dashboard.sidebar';

/**
 * The shell every dashboard screen lives in: a fixed rail on the inline-start edge, a sticky header and a scrolling
 * content column. `dir` comes from the server and the layout uses logical utilities, so the browser mirrors it.
 * Below lg the rail becomes a drawer from the same edge.
 */
export default function DashboardLayout({
    title,
    crumbs = [],
    actions,
    children,
}: PropsWithChildren<{ title?: string; crumbs?: Crumb[]; actions?: ReactNode }>) {
    const { dir, locale, siteName, flash } = usePage().props;
    const t = useT();
    const [collapsed, setCollapsed] = useState(() => {
        try {
            return typeof window !== 'undefined' && window.localStorage.getItem(RAIL_KEY) === 'collapsed';
        } catch {
            return false;
        }
    });
    const [navOpen, setNavOpen] = useState(false);

    // Inertia swaps pages without a reload, so keep <html lang dir> in step with the language.
    useEffect(() => {
        document.documentElement.setAttribute('dir', dir);
        document.documentElement.setAttribute('lang', locale);
    }, [dir, locale]);

    const toggle = useCallback(() => {
        setCollapsed((current) => {
            const next = !current;
            try {
                window.localStorage.setItem(RAIL_KEY, next ? 'collapsed' : 'expanded');
            } catch {
                // A private window or blocked storage is not a reason to fail a click.
            }

            return next;
        });
    }, []);

    return (
        <div dir={dir} className="min-h-screen bg-background">
            <Head title={title ? `${title} | ${siteName}` : siteName} />

            <Sidebar collapsed={collapsed} onToggle={toggle} />

            <Dialog open={navOpen} onOpenChange={setNavOpen}>
                <Sheet title={t('shell.main_menu', 'القائمة الرئيسية')} className="lg:hidden">
                    <Sidebar collapsed={false} inSheet />
                </Sheet>
            </Dialog>

            <div className={cn('flex min-h-screen flex-col transition-[padding] duration-200', collapsed ? 'lg:ps-sidebar-rail' : 'lg:ps-sidebar')}>
                <Header crumbs={crumbs} onOpenNav={() => setNavOpen(true)} />

                <main className="flex-1 px-4 py-6 sm:px-6">
                    <div className="mx-auto w-full max-w-7xl space-y-6">
                        {flash.status ? <Alert tone="success">{flash.status}</Alert> : null}
                        {flash.warning ? <Alert tone="warning">{flash.warning}</Alert> : null}
                        {flash.error ? <Alert tone="error">{flash.error}</Alert> : null}

                        {title ? (
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <h1 className="text-xl font-semibold sm:text-2xl">{title}</h1>
                                {actions ? <div className="flex items-center gap-2">{actions}</div> : null}
                            </div>
                        ) : null}

                        {children}
                    </div>
                </main>

                <footer className="border-t px-4 py-4 sm:px-6">
                    <div className="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                        <span>
                            {siteName} · {t('title')}
                        </span>
                    </div>
                </footer>
            </div>
        </div>
    );
}
