import { Link } from '@inertiajs/react';

import { StateBadge, type StateTone } from '@/Components/review/StateBadge';
import { DataTable, type Column } from '@/Components/table/DataTable';
import { Alert } from '@/Components/ui/alert';
import { Num } from '@/Components/ui/bidi';
import { Select } from '@/Components/ui/input';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useT } from '@/lib/i18n';
import type { TablePayload } from '@/types/global';

interface Row {
    id: number;
    reference: string;
    unit: string | null;
    buyer: string | null;
    seller: string | null;
    stage: string;
    stage_label: string;
    opened_at: string;
    updated_at: string | null;
}

export function dealTone(stage: string): StateTone {
    return stage === 'completed' ? 'done' : stage === 'cancelled' ? 'stopped' : stage === 'opened' ? 'waiting' : 'progress';
}

/** Deals (PLAN 2.4; part 7.5; both roles). */
export default function DealsIndex({ table, stages }: { table: TablePayload<Row>; stages: { value: string; label: string }[] }) {
    const t = useT();
    const columns: Column<Row>[] = [
        {
            key: 'opened_at',
            header: t('deals.col_deal'),
            cell: (row) => (
                <Link href={`/dashboard/deals/${row.id}`} className="flex flex-col gap-0.5 hover:underline">
                    <span className="font-mono font-medium" dir="ltr">
                        {row.reference}
                        {row.unit ? ` · ${row.unit}` : ''}
                    </span>
                    <Num className="font-mono text-xs text-muted-foreground">{row.opened_at}</Num>
                </Link>
            ),
        },
        { key: 'parties', header: t('deals.col_parties'), sortable: false, hideBelow: 'lg', cell: (row) => [row.buyer, row.seller].filter(Boolean).join(' · ') },
        { key: 'stage', header: t('deals.col_stage'), sortable: false, cell: (row) => <StateBadge tone={dealTone(row.stage)}>{row.stage_label}</StateBadge> },
        { key: 'updated_at', header: t('deals.col_updated'), hideOnMobile: true, cell: (row) => <Num className="font-mono text-xs">{row.updated_at ?? ''}</Num> },
    ];

    return (
        <DashboardLayout title={t('deals.title')} crumbs={[{ label: t('deals.title') }]}>
            <Alert tone="info">{t('deals.intro')}</Alert>
            <DataTable
                table={table}
                columns={columns}
                rowId={(row) => row.id}
                filters={(setFilter, current) => (
                    <>
                        <Select aria-label={t('deals.filter_stage')} value={current.stage ?? ''} onChange={(event) => setFilter('stage', event.target.value || null)} className="w-auto min-w-[11rem]">
                            <option value="">{t('deals.all_stages')}</option>
                            {stages.map((s) => (
                                <option key={s.value} value={s.value}>
                                    {s.label}
                                </option>
                            ))}
                        </Select>
                        <Select aria-label={t('deals.filter_open')} value={current.open ?? ''} onChange={(event) => setFilter('open', event.target.value || null)} className="w-auto min-w-[9rem]">
                            <option value="">{t('deals.all')}</option>
                            <option value="1">{t('deals.open_only')}</option>
                            <option value="0">{t('deals.closed_only')}</option>
                        </Select>
                    </>
                )}
            />
        </DashboardLayout>
    );
}
