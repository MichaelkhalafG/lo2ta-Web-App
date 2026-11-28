import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { TextField } from '@/Components/form/TextField';
import { ReasonDialog } from '@/Components/review/ReasonDialog';
import { StateBadge, type StateTone } from '@/Components/review/StateBadge';
import { DataTable, type Column } from '@/Components/table/DataTable';
import { Alert } from '@/Components/ui/alert';
import { Num } from '@/Components/ui/bidi';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Dialog, DialogContent } from '@/Components/ui/dialog';
import { Select } from '@/Components/ui/input';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useT } from '@/lib/i18n';
import type { TablePayload } from '@/types/global';

interface Row {
    id: number;
    deal_id: number;
    deal: string | null;
    unit: string | null;
    amount: string;
    base: string;
    rate: string;
    source: string;
    status: string;
    status_label: string;
    moves: string[];
    invoiced_on: string | null;
    collected_on: string | null;
    reference: string | null;
    waived_reason: string | null;
    notes: string | null;
}

const TONE: Record<string, StateTone> = { expected: 'info', due: 'waiting', invoiced: 'progress', collected: 'done', waived: 'stopped' };

function MarkDialog({ row, status, label, onClose }: { row: Row; status: 'invoiced' | 'collected'; label: string; onClose: () => void }) {
    const t = useT();
    const [on, setOn] = useState(new Date().toISOString().slice(0, 10));
    const [reference, setReference] = useState(row.reference ?? '');

    return (
        <div className="space-y-4">
            <TextField label={t('commissions.on')} type="date" dir="ltr" value={on} onChange={setOn} required />
            <TextField label={t('commissions.reference')} dir="ltr" value={reference} onChange={(v) => setReference(v.slice(0, 120))} required />
            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={onClose}>
                    {t('common.cancel')}
                </Button>
                <Button
                    type="button"
                    disabled={on === '' || reference.trim() === ''}
                    onClick={() => router.put(`/dashboard/commissions/${row.id}`, { status, on, reference }, { preserveScroll: true, onSuccess: onClose })}
                >
                    {label}
                </Button>
            </div>
        </div>
    );
}

/** Commissions (PLAN 2.4; part 7.5; admin only; G7, G8). */
export default function CommissionsIndex({ table, totals }: { table: TablePayload<Row>; totals: { status: string; label: string; amount: string }[] }) {
    const t = useT();
    const [marking, setMarking] = useState<{ row: Row; status: 'invoiced' | 'collected' } | null>(null);
    const columns: Column<Row>[] = [
        {
            key: 'deal',
            header: t('commissions.col_deal'),
            sortable: false,
            cell: (row) => (
                <Link href={`/dashboard/deals/${row.deal_id}`} className="font-mono hover:underline" dir="ltr">
                    {row.deal}
                    {row.unit ? ` · ${row.unit}` : ''}
                </Link>
            ),
        },
        { key: 'amount', header: t('commissions.col_amount'), cell: (row) => <Num className="font-medium">{row.amount}</Num> },
        {
            key: 'basis',
            header: t('commissions.col_basis'),
            sortable: false,
            hideBelow: 'lg',
            cell: (row) => (
                <span className="text-xs text-muted-foreground">
                    {row.rate} × <Num>{row.base}</Num> · {row.source}
                </span>
            ),
        },
        {
            key: 'status',
            header: t('commissions.col_status'),
            sortable: false,
            cell: (row) => (
                <span className="flex flex-col gap-0.5">
                    <StateBadge tone={TONE[row.status] ?? 'info'}>{row.status_label}</StateBadge>
                    {row.waived_reason ? <span className="text-xs text-muted-foreground">{row.waived_reason}</span> : null}
                </span>
            ),
        },
        {
            key: 'reference',
            header: t('commissions.col_reference'),
            sortable: false,
            hideBelow: 'xl',
            cell: (row) => (
                <span className="text-xs" dir="ltr">
                    {[row.reference, row.invoiced_on, row.collected_on].filter(Boolean).join(' · ')}
                </span>
            ),
        },
    ];

    return (
        <DashboardLayout title={t('commissions.title')} crumbs={[{ label: t('commissions.title') }]}>
            <Alert tone="info">{t('commissions.intro')}</Alert>
            <Card className="flex flex-wrap gap-x-6 gap-y-2 p-4 text-sm">
                <span className="font-medium">{t('commissions.totals')}</span>
                {totals.map((x) => (
                    <span key={x.status}>
                        {x.label}: <Num>{x.amount}</Num>
                    </span>
                ))}
            </Card>
            <DataTable
                table={table}
                columns={columns}
                rowId={(row) => row.id}
                filters={(setFilter, current) => (
                    <Select aria-label={t('commissions.filter_status')} value={current.status ?? ''} onChange={(event) => setFilter('status', event.target.value || null)} className="w-auto min-w-[10rem]">
                        <option value="">{t('commissions.all')}</option>
                        {totals.map((x) => (
                            <option key={x.status} value={x.status}>
                                {x.label}
                            </option>
                        ))}
                    </Select>
                )}
                rowActions={(row) => (
                    <div className="flex flex-wrap justify-end gap-1">
                        {row.moves.includes('invoiced') ? (
                            <Button type="button" size="sm" variant="ghost" onClick={() => setMarking({ row, status: 'invoiced' })}>
                                {t('commissions.mark', { status: totals.find((x) => x.status === 'invoiced')?.label ?? '' })}
                            </Button>
                        ) : null}
                        {row.moves.includes('collected') ? (
                            <Button type="button" size="sm" variant="ghost" onClick={() => setMarking({ row, status: 'collected' })}>
                                {t('commissions.mark', { status: totals.find((x) => x.status === 'collected')?.label ?? '' })}
                            </Button>
                        ) : null}
                        {row.moves.includes('waived') ? (
                            <ReasonDialog
                                title={t('commissions.waive')}
                                consequence={t('commissions.waive_consequence')}
                                confirmLabel={t('commissions.waive')}
                                trigger={
                                    <Button type="button" size="sm" variant="ghost" className="text-error">
                                        {t('commissions.waive')}
                                    </Button>
                                }
                                onConfirm={(reason) => router.put(`/dashboard/commissions/${row.id}`, { status: 'waived', reason }, { preserveScroll: true })}
                            />
                        ) : null}
                    </div>
                )}
            />
            <Dialog open={marking !== null} onOpenChange={(next) => (next ? null : setMarking(null))}>
                {marking ? (
                    <DialogContent title={t('commissions.mark', { status: totals.find((x) => x.status === marking.status)?.label ?? '' })} className="sm:max-w-md">
                        <MarkDialog row={marking.row} status={marking.status} label={t('commissions.mark', { status: totals.find((x) => x.status === marking.status)?.label ?? '' })} onClose={() => setMarking(null)} />
                    </DialogContent>
                ) : null}
            </Dialog>
        </DashboardLayout>
    );
}
