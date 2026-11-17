import { DataTable, type Column } from '@/Components/table/DataTable';
import { DateRangeFilter } from '@/Components/table/DateRangeFilter';
import { Badge } from '@/Components/ui/badge';
import { Num } from '@/Components/ui/bidi';
import { Select } from '@/Components/ui/input';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useT } from '@/lib/i18n';
import type { TablePayload } from '@/types/global';

interface Change {
    field: string;
    from: string | null;
    to: string | null;
}

interface Entry {
    id: number;
    time: string | null;
    actor_type: 'admin' | 'user' | 'system';
    actor: string | null;
    action: string;
    subject: string | null;
    changes: Change[];
    reason: string | null;
}

interface Props {
    table: TablePayload<Entry>;
    actions: string[];
    actorTypes: Entry['actor_type'][];
}

function tone(action: string): 'destructive' | 'success' | 'warning' | 'neutral' {
    if (action.endsWith('.deleted')) {
        return 'destructive';
    }
    if (action.endsWith('.created')) {
        return 'success';
    }
    if (action.endsWith('login_failed')) {
        return 'warning';
    }

    return 'neutral';
}

function Value({ value }: { value: string | null }) {
    const t = useT();

    return value === null ? (
        <span className="text-muted-foreground">{t('activity.empty_value', '—')}</span>
    ) : (
        <bdi dir="auto" className="break-all">
            {value}
        </bdi>
    );
}

/** The activity log (PLAN 1.3, admin only): every write with who, what, before and after. */
export default function ActivityIndex({ table, actions, actorTypes }: Props) {
    const t = useT();

    const columns: Column<Entry>[] = [
        {
            key: 'created_at',
            header: t('activity.time', 'الوقت'),
            cell: (row) => <Num className="font-mono text-xs">{row.time ?? ''}</Num>,
            className: 'whitespace-nowrap',
        },
        {
            key: 'actor',
            header: t('activity.actor', 'المنفّذ'),
            sortable: false,
            cell: (row) => (
                <span className="flex flex-col gap-0.5">
                    <span>{row.actor ?? t(`activity.actor_types.${row.actor_type}`, row.actor_type)}</span>
                    <span className="text-xs text-muted-foreground">{t(`activity.actor_types.${row.actor_type}`, row.actor_type)}</span>
                </span>
            ),
        },
        {
            key: 'action',
            header: t('activity.action', 'الإجراء'),
            cell: (row) => (
                <Badge variant={tone(row.action)} className="font-mono">
                    {row.action}
                </Badge>
            ),
        },
        {
            key: 'subject',
            header: t('activity.subject', 'العنصر'),
            sortable: false,
            hideBelow: 'lg',
            cell: (row) => (row.subject === null ? null : <span className="font-mono text-xs" dir="ltr">{row.subject}</span>),
        },
        {
            key: 'changes',
            header: t('activity.changes', 'التغييرات'),
            sortable: false,
            cell: (row) => (
                <div className="flex flex-col gap-1 text-xs">
                    {row.changes.slice(0, 6).map((change) => (
                        <div key={change.field} className="grid grid-cols-[minmax(6rem,auto)_1fr] gap-2">
                            <span className="font-mono text-muted-foreground" dir="ltr">
                                {change.field}
                            </span>
                            <span>
                                <Value value={change.from} /> → <Value value={change.to} />
                            </span>
                        </div>
                    ))}
                    {row.reason ? (
                        <span className="text-muted-foreground">
                            {t('activity.reason', 'السبب')}: {row.reason}
                        </span>
                    ) : null}
                </div>
            ),
        },
    ];

    return (
        <DashboardLayout title={t('activity.title', 'سجل النشاط')} crumbs={[{ label: t('nav_items.activity', 'سجل النشاط') }]}>
            <DataTable
                table={table}
                columns={columns}
                rowId={(row) => row.id}
                cellAlign="top"
                searchPlaceholder={t('activity.search', 'ابحث في الإجراء أو العنصر…')}
                filters={(setFilter, current, setFilters) => (
                    <>
                        <Select
                            className="h-9 w-auto"
                            aria-label={t('activity.actor', 'المنفّذ')}
                            value={current.actor_type ?? ''}
                            onChange={(event) => setFilter('actor_type', event.target.value || null)}
                        >
                            <option value="">{t('activity.all_actors', 'كل المنفذين')}</option>
                            {actorTypes.map((type) => (
                                <option key={type} value={type}>
                                    {t(`activity.actor_types.${type}`, type)}
                                </option>
                            ))}
                        </Select>
                        <Select
                            className="h-9 w-auto font-mono"
                            aria-label={t('activity.action', 'الإجراء')}
                            value={current.action ?? ''}
                            onChange={(event) => setFilter('action', event.target.value || null)}
                        >
                            <option value="">{t('activity.all_actions', 'كل الإجراءات')}</option>
                            {actions.map((action) => (
                                <option key={action} value={action}>
                                    {action}
                                </option>
                            ))}
                        </Select>
                        <DateRangeFilter
                            from={current.from ?? null}
                            to={current.to ?? null}
                            onChange={(from, to) => setFilters({ from, to })}
                        />
                    </>
                )}
            />
        </DashboardLayout>
    );
}
