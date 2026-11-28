import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export interface Revision {
    at: string | null;
    event: string;
    by: string;
    note?: string | null;
}

/**
 * A record's history set as a drawing's revision block (PLAN 2.7): one row per event, newest first, numbered in the
 * order the events happened. Used for staff, units and deals, so every history in the dashboard reads the same way.
 */
export function RevisionBlock({ rows, className }: { rows: Revision[]; className?: string }) {
    const t = useT();

    if (rows.length === 0) {
        return <p className={cn('text-sm text-muted-foreground', className)}>{t('revision.empty')}</p>;
    }

    return (
        <div className={cn('overflow-x-auto border-t-2 border-foreground', className)}>
            <table className="w-full border-collapse text-sm">
                <thead>
                    <tr className="border-b text-xs text-muted-foreground">
                        <th scope="col" className="w-12 py-2 pe-3 text-start font-medium">
                            {t('revision.no')}
                        </th>
                        <th scope="col" className="w-36 py-2 pe-3 text-start font-medium">
                            {t('revision.date')}
                        </th>
                        <th scope="col" className="py-2 pe-3 text-start font-medium">
                            {t('revision.event')}
                        </th>
                        <th scope="col" className="py-2 pe-3 text-start font-medium">
                            {t('revision.by')}
                        </th>
                        <th scope="col" className="py-2 text-start font-medium">
                            {t('revision.note')}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, i) => (
                        <tr key={`${row.at}-${i}`} className="border-b align-top last:border-b-0">
                            <td className="py-2 pe-3 font-mono tabular-nums text-muted-foreground">
                                <span dir="ltr">{String(rows.length - i).padStart(2, '0')}</span>
                            </td>
                            <td className="py-2 pe-3 font-mono text-xs tabular-nums whitespace-nowrap">
                                <span dir="ltr">{row.at ?? '—'}</span>
                            </td>
                            <td className="py-2 pe-3">{row.event}</td>
                            <td className="py-2 pe-3 text-muted-foreground">{row.by}</td>
                            <td className="py-2 text-muted-foreground">{row.note ?? ''}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
