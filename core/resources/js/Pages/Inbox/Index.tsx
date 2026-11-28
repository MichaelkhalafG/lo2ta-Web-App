import { router } from '@inertiajs/react';
import { useState } from 'react';

import { TextareaField } from '@/Components/form/TextField';
import { StateBadge } from '@/Components/review/StateBadge';
import { DataTable, type Column } from '@/Components/table/DataTable';
import { Alert } from '@/Components/ui/alert';
import { Num } from '@/Components/ui/bidi';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent } from '@/Components/ui/dialog';
import { Select } from '@/Components/ui/input';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useT } from '@/lib/i18n';
import type { TablePayload } from '@/types/global';

interface Row {
    id: number;
    name: string;
    company: string | null;
    job_title: string | null;
    projects: number | null;
    email: string;
    phone: string | null;
    message: string;
    state: 'new' | 'handled';
    staff_note: string | null;
    handled_by: string | null;
    created_at: string | null;
}

/** Contact messages and partnership requests (PLAN 2.6; part 7.6; G5; both roles). */
export default function InboxIndex({ kind, table, retentionMonths }: { kind: 'contact' | 'partnership'; table: TablePayload<Row>; retentionMonths: number }) {
    const t = useT();
    const [open, setOpen] = useState<Row | null>(null);
    const [note, setNote] = useState('');
    const title = kind === 'contact' ? t('inbox.contact') : t('inbox.partnership');
    const save = (handled: boolean) => open && router.put(`/dashboard/inbox/${kind}/${open.id}`, { handled, staff_note: note }, { preserveScroll: true, onSuccess: () => setOpen(null) });

    const columns: Column<Row>[] = [
        {
            key: 'from',
            header: t('inbox.col_from'),
            sortable: false,
            cell: (row) => (
                <span className="flex flex-col gap-0.5">
                    <span className="font-medium">{row.company ? `${row.company} · ${row.name}` : row.name}</span>
                    <span className="break-all text-xs text-muted-foreground" dir="ltr">
                        {[row.email, row.phone].filter(Boolean).join(' · ')}
                    </span>
                </span>
            ),
        },
        { key: 'message', header: t('inbox.col_message'), sortable: false, hideBelow: 'lg', cell: (row) => <span className="line-clamp-2 text-sm">{row.message}</span> },
        { key: 'state', header: t('inbox.col_state'), sortable: false, cell: (row) => <StateBadge tone={row.state === 'new' ? 'waiting' : 'done'}>{t(`inbox.states.${row.state}`, row.state)}</StateBadge> },
        { key: 'created_at', header: t('inbox.col_at'), hideOnMobile: true, cell: (row) => <Num className="font-mono text-xs">{row.created_at ?? ''}</Num> },
    ];

    return (
        <DashboardLayout title={title} crumbs={[{ label: title }]}>
            <Alert tone="info">{t('inbox.intro', { months: retentionMonths })}</Alert>
            <DataTable
                table={table}
                columns={columns}
                rowId={(row) => row.id}
                searchPlaceholder={t('inbox.search')}
                filters={(setFilter, current) => (
                    <Select aria-label={t('inbox.filter')} value={current.state ?? ''} onChange={(event) => setFilter('state', event.target.value || null)} className="w-auto min-w-[9rem]">
                        <option value="">{t('inbox.all')}</option>
                        <option value="new">{t('inbox.states.new')}</option>
                        <option value="handled">{t('inbox.states.handled')}</option>
                    </Select>
                )}
                rowActions={(row) => (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => {
                            setOpen(row);
                            setNote(row.staff_note ?? '');
                        }}
                    >
                        {t('inbox.open')}
                    </Button>
                )}
            />
            <Dialog open={open !== null} onOpenChange={(next) => (next ? null : setOpen(null))}>
                {open ? (
                    <DialogContent title={open.company ?? open.name} className="max-h-[90dvh] space-y-4 overflow-y-auto sm:max-w-xl">
                        <div className="space-y-1 text-sm">
                            <p>{open.name}{open.job_title ? ` · ${open.job_title}` : ''}</p>
                            <p className="break-all text-muted-foreground" dir="ltr">
                                {[open.email, open.phone].filter(Boolean).join(' · ')}
                            </p>
                            {open.projects !== null ? <p className="text-muted-foreground">{t('inbox.projects', { count: open.projects })}</p> : null}
                            <p className="text-xs text-muted-foreground">
                                <Num>{open.created_at ?? ''}</Num>
                                {open.handled_by ? ` · ${t('inbox.handled_by', { name: open.handled_by })}` : ''}
                            </p>
                        </div>
                        <p className="whitespace-pre-line break-words rounded-sm border p-3 text-sm leading-7">{open.message}</p>
                        <TextareaField label={t('inbox.note')} rows={3} value={note} onChange={(v) => setNote(v.slice(0, 2000))} />
                        <div className="flex flex-wrap justify-end gap-2">
                            {open.state === 'handled' ? (
                                <Button type="button" variant="outline" onClick={() => save(false)}>
                                    {t('inbox.reopen')}
                                </Button>
                            ) : (
                                <Button type="button" variant="outline" onClick={() => save(false)}>
                                    {t('inbox.save_note')}
                                </Button>
                            )}
                            <Button type="button" onClick={() => save(true)}>
                                {t('inbox.mark_handled')}
                            </Button>
                        </div>
                    </DialogContent>
                ) : null}
            </Dialog>
        </DashboardLayout>
    );
}
