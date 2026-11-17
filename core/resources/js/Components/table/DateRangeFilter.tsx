import { CalendarDays } from 'lucide-react';

import { Input } from '@/Components/ui/input';
import { useT } from '@/lib/i18n';

export function DateRangeFilter({
    from,
    to,
    onChange,
    label,
}: {
    from: string | null;
    to: string | null;
    onChange: (from: string | null, to: string | null) => void;
    label?: string;
}) {
    const t = useT();
    const heading = label ?? t('table.date_range', 'المدة');

    const day = (offset: number): string => {
        const date = new Date();
        date.setDate(date.getDate() + offset);

        return [
            date.getFullYear(),
            String(date.getMonth() + 1).padStart(2, '0'),
            String(date.getDate()).padStart(2, '0'),
        ].join('-');
    };

    const presets: Array<{ key: string; label: string; range: () => [string, string] }> = [
        { key: 'today', label: t('table.range_today', 'اليوم'), range: () => [day(0), day(0)] },
        { key: 'week', label: t('table.range_7', 'آخر 7 أيام'), range: () => [day(-6), day(0)] },
        {
            key: 'month',
            label: t('table.range_month', 'هذا الشهر'),
            range: () => {
                const now = new Date();

                return [
                    `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`,
                    day(0),
                ];
            },
        },
    ];

    return (
        <fieldset className="flex flex-wrap items-center gap-1.5 rounded-md border px-2 py-1">
            <legend className="flex items-center gap-1 px-1 text-[11px] text-muted-foreground">
                <CalendarDays className="h-3 w-3" aria-hidden="true" />
                {heading}
            </legend>

            <label className="flex items-center gap-1 text-xs text-muted-foreground">
                {t('common.from', 'من')}
                <Input
                    type="date"
                    lang="ar-EG"
                    className="h-8 w-[9.5rem] text-sm"
                    aria-label={t('common.from_date', 'من تاريخ')}
                    value={from ?? ''}
                    max={to ?? undefined}
                    onChange={(event) => onChange(event.target.value || null, to)}
                />
            </label>
            <label className="flex items-center gap-1 text-xs text-muted-foreground">
                {t('common.to', 'إلى')}
                <Input
                    type="date"
                    lang="ar-EG"
                    className="h-8 w-[9.5rem] text-sm"
                    aria-label={t('common.to_date', 'إلى تاريخ')}
                    value={to ?? ''}
                    min={from ?? undefined}
                    onChange={(event) => onChange(from, event.target.value || null)}
                />
            </label>

            <span className="flex items-center gap-1">
                {presets.map((preset) => (
                    <button
                        key={preset.key}
                        type="button"
                        className="rounded px-1.5 py-0.5 text-[11px] text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        onClick={() => {
                            const [start, end] = preset.range();
                            onChange(start, end);
                        }}
                    >
                        {preset.label}
                    </button>
                ))}
                {from === null && to === null ? null : (
                    <button
                        type="button"
                        className="rounded px-1.5 py-0.5 text-[11px] text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        onClick={() => onChange(null, null)}
                    >
                        {t('table.range_clear', 'كل المدة')}
                    </button>
                )}
            </span>
        </fieldset>
    );
}
