import { Head, Link, usePage } from '@inertiajs/react';

import { Wordmark } from '@/Components/shell/Wordmark';
import { Button } from '@/Components/ui/button';
import { useT } from '@/lib/i18n';

/**
 * The dashboard's error page (403, 404, 419, 500, 503), rendered by the exception handler for dashboard routes.
 * It stands alone (no sidebar): the session may be gone, and the shell must not depend on what just failed.
 */
export default function Error({ status }: { status: number }) {
    const t = useT();
    const { dir, siteName } = usePage().props;
    const known = [403, 404, 419, 500, 503].includes(status) ? status : 500;

    return (
        <div dir={dir} className="flex min-h-screen flex-col items-center justify-center gap-6 bg-background px-6 text-center">
            <Head title={`${t(`errors.${known}.title`)} | ${siteName}`} />
            <Wordmark />
            <p className="font-mono text-sm text-muted-foreground" dir="ltr">
                {known}
            </p>
            <h1 className="text-2xl font-semibold">{t(`errors.${known}.title`)}</h1>
            <p className="max-w-md text-sm text-muted-foreground">{t(`errors.${known}.body`)}</p>
            <Button asChild variant="outline">
                <Link href="/dashboard">{t('errors.home')}</Link>
            </Button>
        </div>
    );
}
