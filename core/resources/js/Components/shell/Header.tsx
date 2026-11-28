import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, Menu, UserRound } from 'lucide-react';

import { Badge } from '@/Components/ui/badge';
import { Ltr } from '@/Components/ui/bidi';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Crumb } from '@/types/global';

/**
 * The language switch: both languages side by side, each named in itself. It posts to /dashboard/locale, the one
 * place that stores the choice; a full visit, because the language changes the direction of the whole shell.
 */
function LocaleSwitch() {
    const t = useT();
    const { locale } = usePage().props;
    const languages = [
        { value: 'ar', label: 'العربية' },
        { value: 'en', label: 'English' },
    ] as const;

    return (
        <div className="hidden items-center border border-input p-0.5 sm:flex" role="group" aria-label={t('shell.language', 'لغة اللوحة')}>
            {languages.map((language) => (
                <button
                    key={language.value}
                    type="button"
                    lang={language.value}
                    aria-pressed={locale === language.value}
                    onClick={() => {
                        if (locale !== language.value) {
                            router.post('/dashboard/locale', { locale: language.value });
                        }
                    }}
                    className={cn(
                        'px-2.5 py-1 text-xs transition-colors',
                        locale === language.value ? 'bg-accent font-medium text-foreground' : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    {language.label}
                </button>
            ))}
        </div>
    );
}

function UserMenu() {
    const t = useT();
    const { admin } = usePage().props;
    if (admin === null) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="flex items-center gap-2 py-1 ps-3 pe-1 text-sm transition-colors hover:bg-accent"
                    aria-label={t('shell.user_account', 'حساب المستخدم')}
                >
                    <span className="hidden max-w-[12rem] truncate font-medium sm:block">{admin.name}</span>
                    <span aria-hidden="true" className="flex h-8 w-8 items-center justify-center rounded-full bg-accent text-xs font-semibold text-foreground">
                        {admin.initials}
                    </span>
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-64">
                <DropdownMenuLabel>
                    <span className="block truncate">{admin.name}</span>
                    <span className="mt-0.5 block truncate text-xs font-normal text-muted-foreground">
                        <Ltr>{admin.email}</Ltr>
                    </span>
                    <span className="mt-2 flex flex-wrap gap-1">
                        <Badge variant="neutral">{admin.roleLabel}</Badge>
                    </span>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem className="gap-2" onSelect={() => router.visit('/dashboard/account')}>
                    <UserRound className="h-4 w-4" />
                    {t('shell.my_account')}
                </DropdownMenuItem>
                <DropdownMenuItem destructive className="gap-2" onSelect={() => router.post('/dashboard/logout')}>
                    <LogOut className="h-4 w-4" />
                    {t('nav.logout', 'تسجيل الخروج')}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/** The trail. The last crumb is the current page and is not a link. */
function Breadcrumbs({ crumbs }: { crumbs: Crumb[] }) {
    const t = useT();
    if (crumbs.length === 0) {
        return null;
    }

    return (
        <nav aria-label={t('shell.breadcrumb', 'مسار التنقل')} className="min-w-0">
            <ol className="flex items-center gap-1.5 text-xs text-muted-foreground">
                {crumbs.map((crumb, index) => {
                    const last = index === crumbs.length - 1;

                    return (
                        <li key={`${crumb.label}-${index}`} className="flex min-w-0 items-center gap-1.5">
                            {crumb.href !== undefined && !last ? (
                                <Link href={crumb.href} className="truncate hover:text-foreground hover:underline">
                                    {crumb.label}
                                </Link>
                            ) : (
                                <span className={last ? 'truncate font-medium text-foreground' : 'truncate'} aria-current={last ? 'page' : undefined}>
                                    {crumb.label}
                                </span>
                            )}
                            {last ? null : <span aria-hidden="true">/</span>}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}

export function Header({ crumbs, onOpenNav }: { crumbs: Crumb[]; onOpenNav: () => void }) {
    const t = useT();

    return (
        <header className="sticky top-0 z-30 flex h-header items-center gap-3 border-b bg-background/95 px-4 backdrop-blur">
            <Button type="button" variant="outline" size="icon" className="lg:hidden" onClick={onOpenNav} aria-label={t('shell.open_menu', 'فتح القائمة')}>
                <Menu className="h-[18px] w-[18px]" />
            </Button>

            <Breadcrumbs crumbs={crumbs} />

            <div className="ms-auto flex items-center gap-2">
                <LocaleSwitch />
                <UserMenu />
            </div>
        </header>
    );
}
