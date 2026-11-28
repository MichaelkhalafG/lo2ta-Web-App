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
    unit_id: number;
    buyer: string | null;
    readiness: string;
    state: string;
    state_label: string;
    assigned: string | null;
    created_at: string | null;
}

export function requestTone(state: string): StateTone {
    return state === 'new' ? 'waiting' : state === 'contacted' ? 'progress' : state === 'matched' ? 'done' : 'stopped';
}

/** The unit request inbox (PLAN 2.3, 2.4; part 7.4; both roles). */
export default function RequestsIndex({ table, states }: { table: TablePayload<Row>; states: { value: string; label: string }[] }) {
    const t = useT();
    const columns: Column<Row>[] = [
        {
            key: 'created_at',
            header: t('requests.col_request'),
            cell: (row) => (
                <Link href={`/dashboard/requests/${row.id}`} className="flex flex-col gap-0.5 hover:underline">
                    <span className="font-mono font-medium" dir="ltr">
                        {row.reference}
                        {row.unit ? ` · ${row.unit}` : ''}
                    </span>
                    <Num className="font-mono text-xs text-muted-foreground">{row.created_at ?? ''}</Num>
                </Link>
            ),
        },
        { key: 'buyer', header: t('requests.col_buyer'), sortable: false, cell: (row) => row.buyer ?? '—' },
        { key: 'readiness', header: t('requests.col_readiness'), sortable: false, hideBelow: 'lg', cell: (row) => row.readiness },
        { key: 'assigned', header: t('requests.col_assigned'), sortable: false, hideBelow: 'xl', cell: (row) => row.assigned ?? t('requests.unassigned') },
        { key: 'state', header: t('requests.col_state'), sortable: false, cell: (row) => <StateBadge tone={requestTone(row.state)}>{row.state_label}</StateBadge> },
    ];

    return (
        <DashboardLayout title={t('requests.title')} crumbs={[{ label: t('requests.title') }]}>
            <Alert tone="info">{t('requests.intro')}</Alert>
            <DataTable
                table={table}
                columns={columns}
                rowId={(row) => row.id}
                searchPlaceholder={t('requests.search')}
                filters={(setFilter, current) => (
                    <>
                        <Select aria-label={t('requests.filter_state')} value={current.state ?? ''} onChange={(event) => setFilter('state', event.target.value || null)} className="w-auto min-w-[10rem]">
                            <option value="">{t('requests.all_states')}</option>
                            {states.map((s) => (
                                <option key={s.value} value={s.value}>
                                    {s.label}
                                </option>
                            ))}
                        </Select>
                        <Select aria-label={t('requests.mine')} value={current.mine ?? ''} onChange={(event) => setFilter('mine', event.target.value || null)} className="w-auto min-w-[10rem]">
                            <option value="">{t('requests.all')}</option>
                            <option value="1">{t('requests.mine')}</option>
                        </Select>
                    </>
                )}
            />
        </DashboardLayout>
    );
}
