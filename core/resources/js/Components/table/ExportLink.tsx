import { usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';

import { Button } from '@/Components/ui/button';
import { useT } from '@/lib/i18n';

export function ExportLink({ count, label: labelProp }: { count?: number; label?: string }) {
    const t = useT();
    const label = labelProp ?? t('common.export_csv', 'تصدير CSV');

    const { abilities } = usePage().props;
    if (abilities['export-data'] !== true) {
        return null;
    }

    const href = (() => {
        if (typeof window === 'undefined') {
            return '';
        }
        const url = new URL(window.location.href);
        url.searchParams.set('export', 'csv');
        return `${url.pathname}${url.search}`;
    })();

    return (
        <Button asChild variant="outline" size="sm" className="gap-1.5">
            <a href={href} download title={count === undefined ? label : t('table.download_count', 'تنزيل :count سجل', { count })}>
                <Download className="h-3.5 w-3.5" />
                {label}
            </a>
        </Button>
    );
}
