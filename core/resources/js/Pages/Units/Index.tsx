import { Link } from '@inertiajs/react';

import { StateBadge, type StateTone } from '@/Components/review/StateBadge';
import { DataTable, type Column } from '@/Components/table/DataTable';
import { Alert } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Num } from '@/Components/ui/bidi';
import { Select } from '@/Components/ui/input';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useT } from '@/lib/i18n';
import type { TablePayload } from '@/types/global';

export interface UnitRow {
    id: number;
    reference: string | null;
    code: string | null;
    title: string;
    area: string | null;
    seller: string | null;
    state: string;
    state_label: string;
    submitted_at: string | null;
    verified: boolean;
    deal_tag: boolean;
    withdraw_requested: boolean;
    has_other: boolean;
}

interface Props {
    table: TablePayload<UnitRow>;
    states: { value: string; label: string; count: number }[];
    withdrawRequests: number;
}

/** One meaning per tone (StateBadge): waiting for the team, with the seller, live, closed. */
export function unitTone(state: string): StateTone {
    switch (state) {
        case 'submitted':
            return 'waiting';
        case 'changes_requested':
        case 'reserved':
            return 'progress';
        case 'published':
        case 'sold':
            return 'done';
        case 'rejected':
        case 'withdrawn':
            return 'stopped';
        default:
            return 'info';
    }
}

/** Unit review queue (PLAN 2.4, part 7.2): every sent unit, waiting ones first by filter; both roles. */
export default function UnitsIndex({ table, states, withdrawRequests }: Props) {
    const t = useT();
    const waiting = states.find((s) => s.value === 'submitted')?.count ?? 0;

    const columns: Column<UnitRow>[] = [
        {
            key: 'serial',
            header: t('units.col_unit'),
            cell: (row) => (
                <Link href={`/dashboard/units/${row.id}`} className="flex flex-col gap-0.5 hover:underline">
                    <span className="font-medium">{row.title || '—'}</span>
                    <span className="font-mono text-xs text-muted-foreground" dir="ltr">
                        {[row.code, row.reference].filter(Boolean).join(' · ')}
                    </span>
                </Link>
            ),
        },
        { key: 'area', header: t('units.col_area'), sortable: false, hideBelow: 'lg', cell: (row) => row.area ?? '—' },
        { key: 'seller', header: t('units.col_seller'), sortable: false, hideBelow: 'xl', cell: (row) => row.seller ?? '—' },
        {
            key: 'submitted_at',
            header: t('units.col_submitted'),
            hideOnMobile: true,
            className: 'whitespace-nowrap',
            cell: (row) => <Num className="font-mono text-xs">{row.submitted_at ?? '—'}</Num>,
        },
        {
            key: 'state',
            header: t('units.col_state'),
            sortable: false,
            cell: (row) => (
                <div className="flex flex-wrap items-center gap-1.5">
                    <StateBadge tone={unitTone(row.state)}>{row.state_label}</StateBadge>
                    {row.verified ? <Badge variant="verified">{t('units.badge_verified')}</Badge> : null}
                    {row.deal_tag ? <Badge variant="outline">{t('units.badge_deal')}</Badge> : null}
                    {row.withdraw_requested ? <Badge variant="warning">{t('units.badge_withdraw')}</Badge> : null}
                    {row.has_other && row.state === 'submitted' ? <Badge variant="neutral">{t('units.badge_other')}</Badge> : null}
                </div>
            ),
        },
    ];

    return (
        <DashboardLayout title={t('units.title')} crumbs={[{ label: t('units.title') }]}>
            <Alert tone="info">
                <p>{t('units.intro')}</p>
                <p className="mt-1 text-muted-foreground">
                    {t('units.waiting', { count: waiting })}
                    {withdrawRequests > 0 ? <> · {t('units.withdraw_waiting', { count: withdrawRequests })}</> : null}
                </p>
            </Alert>
            <DataTable
                table={table}
                columns={columns}
                rowId={(row) => row.id}
                searchPlaceholder={t('units.search')}
                emptyTitle={t('units.empty')}
                filters={(setFilter, current) => (
                    <>
                        <Select aria-label={t('units.filter_state')} value={current.state ?? ''} onChange={(event) => setFilter('state', event.target.value || null)} className="w-auto min-w-[11rem]">
                            <option value="">{t('units.all_states')}</option>
                            {states.map((s) => (
                                <option key={s.value} value={s.value}>
                                    {s.label} ({s.count})
                                </option>
                            ))}
                        </Select>
                        <Select aria-label={t('units.filter_flag')} value={current.flag ?? ''} onChange={(event) => setFilter('flag', event.target.value || null)} className="w-auto min-w-[11rem]">
                            <option value="">{t('units.all_units')}</option>
                            <option value="withdraw_request">{t('units.flags.withdraw_request')}</option>
                            <option value="verified">{t('units.flags.verified')}</option>
                            <option value="deal_tag">{t('units.flags.deal_tag')}</option>
                        </Select>
                    </>
                )}
            />
        </DashboardLayout>
    );
}
