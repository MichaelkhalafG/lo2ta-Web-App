import { Link } from '@inertiajs/react';

import { DataTable, type Column } from '@/Components/table/DataTable';
import { Alert } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Num } from '@/Components/ui/bidi';
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

/** Duplicate attempts (PLAN 2.4, 3.4; part 7.2): read only, both roles. */
export default function DuplicatesIndex({ table }: { table: TablePayload<Attempt> }) {
    const t = useT();
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
            cell: (row) => <Badge variant={row.blocked ? 'destructive' : 'warning'}>{row.blocked ? t('duplicates.blocked') : t('duplicates.warning')}</Badge>,
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
