import { Link, router, usePage } from '@inertiajs/react';

import { ReasonDialog } from '@/Components/review/ReasonDialog';
import { DataTable, type Column } from '@/Components/table/DataTable';
import { Alert } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Num } from '@/Components/ui/bidi';
import { Button } from '@/Components/ui/button';
import { Select } from '@/Components/ui/input';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useT } from '@/lib/i18n';
import type { TablePayload } from '@/types/global';

interface UnitRef {
    id: number;
    label: string;
    state: string;
    openable: boolean;
}

interface Attempt {
    id: number;
    at: string;
    kind: 'contract' | 'unit' | 'file_sha256';
    stage: 'step1' | 'submit' | 'upload';
    blocked: boolean;
    user: string | null;
    unit: UnitRef | null;
    matched: UnitRef | null;
    overridable: boolean;
    override: { at: string; by: string | null; reason: string } | null;
}

/** An admin's override of one block (Q23): the result cell shows it, or the button when the admin may give one. */
function ResultCell({ row, canOverride }: { row: Attempt; canOverride: boolean }) {
    const t = useT();
    const { errors } = usePage().props as { errors?: Record<string, string> };

    return (
        <span className="flex flex-col items-start gap-1">
            <Badge variant={row.blocked ? 'destructive' : 'warning'}>{row.blocked ? t('duplicates.blocked') : t('duplicates.warning')}</Badge>
            {row.override ? (
                <span className="flex flex-col gap-0.5 text-xs">
                    <Badge variant="outline">{t('duplicates.overridden_badge')}</Badge>
                    <span className="text-muted-foreground">
                        <Num className="font-mono">{row.override.at}</Num> · <bdi>{row.override.by ?? '—'}</bdi>
                    </span>
                    <bdi className="max-w-[16rem] break-words">{row.override.reason}</bdi>
                </span>
            ) : null}
            {canOverride && row.overridable ? (
                <ReasonDialog
                    title={t('duplicates.override')}
                    consequence={t('duplicates.override_consequence')}
                    confirmLabel={t('duplicates.override')}
                    hint={t('duplicates.override_hint')}
                    error={errors?.reason ?? errors?.attempt ?? null}
                    trigger={
                        <Button type="button" size="sm" variant="outline">
                            {t('duplicates.override')}
                        </Button>
                    }
                    onConfirm={(reason) => router.post(`/dashboard/duplicates/${row.id}/override`, { reason }, { preserveScroll: true })}
                />
            ) : null}
        </span>
    );
}

function UnitLink({ unit }: { unit: UnitRef | null }) {
    const t = useT();
    if (unit === null) {
        return <span className="text-muted-foreground">—</span>;
    }
    const label = (
        <span className="font-mono" dir="ltr">
            {unit.label}
        </span>
    );

    return (
        <span className="flex flex-col gap-0.5">
            {unit.openable ? (
                <Link href={`/dashboard/units/${unit.id}`} className="underline underline-offset-4">
                    {label}
                </Link>
            ) : (
                label
            )}
            <span className="text-xs text-muted-foreground">{unit.openable ? unit.state : t('duplicates.not_submitted')}</span>
        </span>
    );
}

/** Duplicate attempts (PLAN 2.4, 3.4; part 7.2): both roles see them; an admin may override a block (Q23). */
export default function DuplicatesIndex({ table }: { table: TablePayload<Attempt> }) {
    const t = useT();
    const { abilities } = usePage().props;
    const canOverride = abilities['override-duplicates'] === true;
    const kinds: Attempt['kind'][] = ['contract', 'unit', 'file_sha256'];
    const columns: Column<Attempt>[] = [
        { key: 'created_at', header: t('duplicates.col_at'), className: 'whitespace-nowrap', cell: (row) => <Num className="font-mono text-xs">{row.at}</Num> },
        { key: 'kind', header: t('duplicates.col_kind'), sortable: false, cell: (row) => t(`duplicates.kinds.${row.kind}`, row.kind) },
        { key: 'stage', header: t('duplicates.col_stage'), sortable: false, hideBelow: 'lg', cell: (row) => t(`duplicates.stages.${row.stage}`, row.stage) },
        { key: 'unit', header: t('duplicates.col_unit'), sortable: false, cell: (row) => <UnitLink unit={row.unit} /> },
        { key: 'matched', header: t('duplicates.col_matched'), sortable: false, cell: (row) => <UnitLink unit={row.matched} /> },
        { key: 'user', header: t('duplicates.col_user'), sortable: false, hideBelow: 'xl', cell: (row) => <span className="break-all text-sm">{row.user ?? '—'}</span> },
        {
            key: 'blocked',
            header: t('duplicates.col_result'),
            sortable: false,
            cell: (row) => <ResultCell row={row} canOverride={canOverride} />,
        },
    ];

    return (
        <DashboardLayout title={t('duplicates.title')} crumbs={[{ label: t('duplicates.title') }]}>
            <Alert tone="info">{t('duplicates.intro')}</Alert>
            <DataTable
                table={table}
                columns={columns}
                rowId={(row) => row.id}
                filters={(setFilter, current) => (
                    <>
                        <Select aria-label={t('duplicates.filter_kind')} value={current.key_kind ?? ''} onChange={(event) => setFilter('key_kind', event.target.value || null)} className="w-auto min-w-[10rem]">
                            <option value="">{t('duplicates.all')}</option>
                            {kinds.map((k) => (
                                <option key={k} value={k}>
                                    {t(`duplicates.kinds.${k}`, k)}
                                </option>
                            ))}
                        </Select>
                        <Select aria-label={t('duplicates.filter_result')} value={current.blocked ?? ''} onChange={(event) => setFilter('blocked', event.target.value || null)} className="w-auto min-w-[9rem]">
                            <option value="">{t('duplicates.all')}</option>
                            <option value="1">{t('duplicates.blocked')}</option>
                            <option value="0">{t('duplicates.warning')}</option>
                        </Select>
                    </>
                )}
            />
        </DashboardLayout>
    );
}
