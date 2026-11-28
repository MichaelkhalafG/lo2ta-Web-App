import { Link } from '@inertiajs/react';

import { StateBadge } from '@/Components/review/StateBadge';
import { DataTable, type Column } from '@/Components/table/DataTable';
import { Alert } from '@/Components/ui/alert';
import { Num } from '@/Components/ui/bidi';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useT } from '@/lib/i18n';
import type { BudgetView } from '@/Pages/People/Show';
import type { TablePayload } from '@/types/global';

type Row = BudgetView & { name: string | null; suspended: boolean };

/** Buyer budgets (PLAN 2.3, 2.4; part 7.4; both roles). */
export default function BudgetsIndex({ table }: { table: TablePayload<Row> }) {
    const t = useT();
    const columns: Column<Row>[] = [
        {
            key: 'buyer',
            header: t('budgets.col_buyer'),
            sortable: false,
            cell: (row) => (
                <span className="flex flex-col gap-0.5">
                    <Link href={`/dashboard/people/${row.user_id}`} className="font-medium hover:underline">
                        {row.name ?? `#${row.user_id}`}
                    </Link>
                    {row.suspended ? <StateBadge tone="stopped">{t('people.suspended')}</StateBadge> : null}
                </span>
            ),
        },
        { key: 'cash_available', header: t('budgets.col_cash'), cell: (row) => <Num>{row.cash}</Num> },
        {
            key: 'max_installment',
            header: t('budgets.col_installment'),
            cell: (row) => (
                <span className="flex flex-col gap-0.5">
                    <Num>{row.installment}</Num>
                    <span className="text-xs text-muted-foreground">
                        {row.frequency} · {t('budgets.per_month', { amount: row.monthly })}
                    </span>
                </span>
            ),
        },
        {
            key: 'wants',
            header: t('budgets.col_wants'),
            sortable: false,
            hideBelow: 'lg',
            cell: (row) => (
                <span className="text-sm">
                    {[row.types.join(' · ') || t('budgets.any'), row.areas.slice(0, 3).join(' · ')].filter(Boolean).join(' — ')}
                    {row.areas.length > 3 ? ' …' : ''}
                </span>
            ),
        },
        { key: 'updated_at', header: t('budgets.col_updated'), hideOnMobile: true, cell: (row) => <Num className="font-mono text-xs">{row.updated_at ?? ''}</Num> },
    ];

    return (
        <DashboardLayout title={t('budgets.title')} crumbs={[{ label: t('budgets.title') }]}>
            <Alert tone="info">{t('budgets.intro')}</Alert>
            <DataTable
                table={table}
                columns={columns}
                rowId={(row) => row.id}
                searchPlaceholder={t('budgets.search')}
                rowActions={(row) => (
                    <Link href={`/dashboard/matching?buyer=${row.user_id}`} className="text-sm whitespace-nowrap underline underline-offset-4">
                        {t('budgets.match')}
                    </Link>
                )}
            />
        </DashboardLayout>
    );
}
