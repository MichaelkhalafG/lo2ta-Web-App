import { Link, router, usePage } from '@inertiajs/react';

import { ReasonDialog } from '@/Components/review/ReasonDialog';
import { ConfirmAction } from '@/Components/shell/ConfirmAction';
import { Alert } from '@/Components/ui/alert';
import { Num } from '@/Components/ui/bidi';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useT } from '@/lib/i18n';

interface Pending {
    id: number;
    unit_id: number;
    unit: string;
    at: string;
    fields: { label: string; from: string | null; to: string | null }[];
}

interface Edit {
    id: number;
    action: string;
    label: string;
    at: string;
    description_before: string | null;
    description_after: string | null;
    fields: string[];
}

interface Recheck {
    unit_id: number;
    unit: string;
    at: string | null;
    edits: Edit[];
    description: string | null;
}

interface NewDocument {
    id: number;
    unit_id: number;
    unit: string;
    type: string;
    at: string | null;
}

interface Props {
    pending: Pending[];
    recheck: Recheck[];
    documents: NewDocument[];
}

const opts = { preserveScroll: true } as const;

function Block({ title, hint, count, children }: { title: string; hint: string; count: number; children: React.ReactNode }) {
    return (
        <Card className="space-y-4 p-4 sm:p-5">
            <div className="space-y-1 border-b pb-3">
                <h2 className="text-base font-semibold">
                    {title} <Num className="font-mono text-sm text-muted-foreground">({count})</Num>
                </h2>
                <p className="text-sm text-muted-foreground">{hint}</p>
            </div>
            {children}
        </Card>
    );
}

function UnitTitle({ id, label, at }: { id: number; label: string; at: string | null }) {
    const t = useT();

    return (
        <div className="flex flex-wrap items-baseline justify-between gap-2">
            <Link href={`/dashboard/units/${id}`} className="font-medium underline-offset-4 hover:underline">
                {label || `#${id}`}
            </Link>
            {at ? <span className="text-xs text-muted-foreground">{t('changes.sent', { at: `⁦${at}⁩` })}</span> : null}
        </div>
    );
}

/** After publishing (PLAN 2.2.7, part 7.3; both roles): figure changes, free edits to check, documents added later. */
export default function ChangesIndex({ pending, recheck, documents }: Props) {
    const t = useT();
    const { errors } = usePage().props as { errors?: Record<string, string> };
    const errorList = Object.values(errors ?? {});

    return (
        <DashboardLayout title={t('changes.title')} crumbs={[{ label: t('changes.title') }]}>
            <Alert tone="info">{t('changes.intro')}</Alert>
            {errorList.length > 0 ? (
                <Alert tone="error">
                    {errorList.map((m) => (
                        <p key={m}>{m}</p>
                    ))}
                </Alert>
            ) : null}

            <Block title={t('changes.pending')} hint={t('changes.pending_hint')} count={pending.length}>
                {pending.length === 0 ? <p className="text-sm text-muted-foreground">{t('changes.pending_empty')}</p> : null}
                <ul className="divide-y">
                    {pending.map((c) => (
                        <li key={c.id} className="space-y-3 py-4 first:pt-0">
                            <UnitTitle id={c.unit_id} label={c.unit} at={c.at} />
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-xs text-muted-foreground">
                                            <th scope="col" className="py-1.5 pe-3 text-start font-medium" />
                                            <th scope="col" className="py-1.5 pe-3 text-start font-medium">
                                                {t('changes.before')}
                                            </th>
                                            <th scope="col" className="py-1.5 text-start font-medium">
                                                {t('changes.after')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {c.fields.map((f) => (
                                            <tr key={f.label} className="border-b last:border-b-0">
                                                <th scope="row" className="py-1.5 pe-3 text-start font-normal text-muted-foreground">
                                                    {f.label}
                                                </th>
                                                <td className="py-1.5 pe-3">
                                                    <Num>{f.from ?? '—'}</Num>
                                                </td>
                                                <td className="py-1.5 font-medium">
                                                    <Num>{f.to ?? '—'}</Num>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <ConfirmAction
                                    title={t('changes.approve')}
                                    consequence={t('changes.approve_consequence')}
                                    confirmLabel={t('changes.approve')}
                                    tone="default"
                                    trigger={
                                        <Button type="button" size="sm">
                                            {t('changes.approve')}
                                        </Button>
                                    }
                                    onConfirm={() => router.post(`/dashboard/changes/${c.id}/approve`, {}, opts)}
                                />
                                <ReasonDialog
                                    title={t('changes.reject')}
                                    consequence={t('changes.reject_consequence')}
                                    confirmLabel={t('changes.reject')}
                                    trigger={
                                        <Button type="button" size="sm" variant="outline">
                                            {t('changes.reject')}
                                        </Button>
                                    }
                                    onConfirm={(reason) => router.post(`/dashboard/changes/${c.id}/reject`, { reason }, opts)}
                                />
                            </div>
                        </li>
                    ))}
                </ul>
            </Block>

            <Block title={t('changes.recheck')} hint={t('changes.recheck_hint')} count={recheck.length}>
                {recheck.length === 0 ? <p className="text-sm text-muted-foreground">{t('changes.recheck_empty')}</p> : null}
                <ul className="divide-y">
                    {recheck.map((r) => (
                        <li key={r.unit_id} className="space-y-3 py-4 first:pt-0">
                            <UnitTitle id={r.unit_id} label={r.unit} at={r.at} />
                            <ul className="space-y-2 text-sm">
                                {r.edits.map((e) => (
                                    <li key={e.id} className="space-y-1 rounded-sm border p-3">
                                        <p className="flex flex-wrap items-baseline justify-between gap-2">
                                            <span className="font-medium">
                                                {e.label}
                                                {e.fields.length > 0 ? `: ${e.fields.join(' · ')}` : ''}
                                            </span>
                                            <Num className="font-mono text-xs text-muted-foreground">{e.at}</Num>
                                        </p>
                                        {e.description_before !== null ? (
                                            <div className="grid gap-2 sm:grid-cols-2">
                                                <div>
                                                    <p className="text-xs text-muted-foreground">{t('changes.before')}</p>
                                                    <p className="whitespace-pre-line break-words">{e.description_before}</p>
                                                </div>
                                                <div>
                                                    <p className="text-xs text-muted-foreground">{t('changes.after')}</p>
                                                    <p className="whitespace-pre-line break-words">{e.description_after ?? '—'}</p>
                                                </div>
                                            </div>
                                        ) : null}
                                        {e.description_before !== null ? (
                                            <ReasonDialog
                                                title={t('changes.restore')}
                                                consequence={t('changes.restore_consequence')}
                                                confirmLabel={t('changes.restore')}
                                                tone="default"
                                                trigger={
                                                    <Button type="button" size="sm" variant="ghost">
                                                        {t('changes.restore')}
                                                    </Button>
                                                }
                                                onConfirm={(reason) => router.post(`/dashboard/changes/units/${r.unit_id}/restore-description`, { log: e.id, reason }, opts)}
                                            />
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                            <div className="flex flex-wrap gap-2">
                                <Button type="button" size="sm" onClick={() => router.post(`/dashboard/changes/units/${r.unit_id}/checked`, {}, opts)}>
                                    {t('changes.checked')}
                                </Button>
                                <Link href={`/dashboard/units/${r.unit_id}`} className="inline-flex h-8 items-center px-2 text-sm underline underline-offset-4">
                                    {t('changes.open_unit')}
                                </Link>
                            </div>
                        </li>
                    ))}
                </ul>
            </Block>

            <Block title={t('changes.documents')} hint={t('changes.documents_hint')} count={documents.length}>
                {documents.length === 0 ? <p className="text-sm text-muted-foreground">{t('changes.documents_empty')}</p> : null}
                <ul className="divide-y text-sm">
                    {documents.map((d) => (
                        <li key={d.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                            <span>
                                <Link href={`/dashboard/units/${d.unit_id}`} className="font-medium underline-offset-4 hover:underline">
                                    {d.unit || `#${d.unit_id}`}
                                </Link>{' '}
                                · {d.type}
                            </span>
                            {d.at ? <Num className="font-mono text-xs text-muted-foreground">{d.at}</Num> : null}
                        </li>
                    ))}
                </ul>
            </Block>
        </DashboardLayout>
    );
}
