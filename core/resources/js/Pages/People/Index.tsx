import { Link } from '@inertiajs/react';

import { StateBadge } from '@/Components/review/StateBadge';
import { DataTable, type Column } from '@/Components/table/DataTable';
import { Alert } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Num } from '@/Components/ui/bidi';
import { Select } from '@/Components/ui/input';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useT } from '@/lib/i18n';
import type { TablePayload } from '@/types/global';

interface Person {
    id: number;
    name: string;
    email: string;
    phone: string;
    verified: boolean;
    suspended: boolean;
    units: number;
    requests: number;
    budget: boolean;
    created_at: string | null;
}

/** Sellers and buyers (PLAN 1.3; part 7.4; both roles see, an admin suspends). */
export default function PeopleIndex({ table }: { table: TablePayload<Person> }) {
    const t = useT();
    const columns: Column<Person>[] = [
        {
            key: 'name',
            header: t('people.col_person'),
            cell: (row) => (
                <Link href={`/dashboard/people/${row.id}`} className="flex flex-col gap-0.5 hover:underline">
                    <span className="font-medium">{row.name}</span>
                    <span className="break-all text-xs text-muted-foreground" dir="ltr">
                        {row.email}
                    </span>
                </Link>
            ),
        },
        { key: 'phone', header: t('people.col_phone'), sortable: false, hideBelow: 'lg', cell: (row) => <Num>{row.phone}</Num> },
        {
            key: 'activity',
            header: t('people.col_activity'),
            sortable: false,
            cell: (row) => (
                <div className="flex flex-wrap items-center gap-1.5">
                    {row.suspended ? <StateBadge tone="stopped">{t('people.suspended')}</StateBadge> : null}
                    {row.units > 0 ? <Badge variant="neutral">{t('people.units_count', { count: row.units })}</Badge> : null}
                    {row.requests > 0 ? <Badge variant="neutral">{t('people.requests_count', { count: row.requests })}</Badge> : null}
                    {row.budget ? <Badge variant="outline">{t('people.has_budget')}</Badge> : null}
                    {!row.verified ? <Badge variant="warning">{t('people.unverified')}</Badge> : null}
                </div>
            ),
        },
        { key: 'created_at', header: t('people.col_joined'), hideOnMobile: true, cell: (row) => <Num className="font-mono text-xs">{row.created_at ?? ''}</Num> },
    ];

    return (
        <DashboardLayout title={t('people.title')} crumbs={[{ label: t('people.title') }]}>
            <Alert tone="info">{t('people.intro')}</Alert>
            <DataTable
                table={table}
                columns={columns}
                rowId={(row) => row.id}
                searchPlaceholder={t('people.search')}
                filters={(setFilter, current) => (
                    <Select aria-label={t('people.filter')} value={current.kind ?? ''} onChange={(event) => setFilter('kind', event.target.value || null)} className="w-auto min-w-[10rem]">
                        <option value="">{t('people.all')}</option>
                        <option value="sellers">{t('people.kinds.sellers')}</option>
                        <option value="buyers">{t('people.kinds.buyers')}</option>
                        <option value="suspended">{t('people.kinds.suspended')}</option>
                    </Select>
                )}
            />
        </DashboardLayout>
    );
}
