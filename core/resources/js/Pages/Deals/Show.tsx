import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { TextareaField } from '@/Components/form/TextField';
import { ReasonDialog } from '@/Components/review/ReasonDialog';
import { RevisionBlock } from '@/Components/review/RevisionBlock';
import { StateBadge } from '@/Components/review/StateBadge';
import { ConfirmAction } from '@/Components/shell/ConfirmAction';
import { Alert } from '@/Components/ui/alert';
import { Num } from '@/Components/ui/bidi';
import { Button } from '@/Components/ui/button';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { dealTone } from '@/Pages/Deals/Index';
import { Facts, Section } from '@/Pages/People/Show';

interface Party {
    id: number;
    name: string;
    email: string;
    phone: string;
}

interface Props {
    deal: { id: number; reference: string; stage: string; stage_label: string; open: boolean; next: string | null; previous: string | null; opened_at: string; cancelled_reason: string | null };
    track: { value: string; label: string }[];
    unit: { id: number; title: string; state: string } | null;
    buyer: Party | null;
    seller: Party | null;
    request: { id: number; reference: string } | null;
    commission: { amount: string; base: string; rate: string; source: string; status: string } | null;
    history: { at: string; event: string; by: string; note: string | null }[];
}

/** One deal (PLAN 2.4; part 7.5; G18): parties, the stage track, the moves, and the commission for an admin. */
export default function DealShow({ deal, track, unit, buyer, seller, request, commission, history }: Props) {
    const t = useT();
    const { errors } = usePage().props as { errors?: Record<string, string> };
    const [note, setNote] = useState('');
    const base = `/dashboard/deals/${deal.id}`;
    const opts = { preserveScroll: true, onSuccess: () => setNote('') } as const;
    const reached = deal.stage === 'cancelled' ? -1 : track.findIndex((s) => s.value === deal.stage);
    const party = (p: Party | null) =>
        p === null ? null : (
            <div className="space-y-0.5">
                <Link href={`/dashboard/people/${p.id}`} className="font-medium underline-offset-4 hover:underline">
                    {p.name}
                </Link>
                <p className="break-all text-xs text-muted-foreground" dir="ltr">
                    {p.email} · {p.phone}
                </p>
            </div>
        );

    return (
        <DashboardLayout title={deal.reference} crumbs={[{ label: t('deals.title'), href: '/dashboard/deals' }, { label: deal.reference }]}>
            <div className="flex flex-wrap items-center gap-2">
                <StateBadge tone={dealTone(deal.stage)}>{deal.stage_label}</StateBadge>
                <Num className="font-mono text-sm text-muted-foreground">{deal.opened_at}</Num>
                {request ? (
                    <Link href={`/dashboard/requests/${request.id}`} className="text-sm underline underline-offset-4">
                        {t('deals.from_request', { reference: request.reference })}
                    </Link>
                ) : null}
            </div>
            {Object.values(errors ?? {}).length > 0 ? <Alert tone="error">{Object.values(errors ?? {}).join(' ')}</Alert> : null}
            {deal.cancelled_reason ? <Alert tone="warning">{t('deals.cancelled_because', { reason: deal.cancelled_reason })}</Alert> : null}

            <Section title={t('deals.sections.track')}>
                <ol className="grid gap-2 sm:grid-cols-6">
                    {track.map((s, i) => (
                        <li
                            key={s.value}
                            aria-current={s.value === deal.stage ? 'step' : undefined}
                            className={cn('border-t-2 pt-2 text-sm', i <= reached ? 'border-foreground' : 'border-border text-muted-foreground', s.value === deal.stage && 'font-semibold')}
                        >
                            <Num className="block font-mono text-xs">{String(i + 1).padStart(2, '0')}</Num>
                            {s.label}
                        </li>
                    ))}
                </ol>
            </Section>

            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div className="order-2 min-w-0 space-y-6 lg:order-1">
                    <Section title={t('deals.sections.parties')}>
                        <Facts
                            rows={[
                                { label: t('deals.buyer'), value: party(buyer) },
                                { label: t('deals.seller'), value: party(seller) },
                            ]}
                        />
                    </Section>
                    {unit ? (
                        <Section
                            title={t('deals.sections.unit')}
                            actions={
                                <Link href={`/dashboard/units/${unit.id}`} className="text-sm underline underline-offset-4">
                                    {t('deals.open_unit')}
                                </Link>
                            }
                        >
                            <p className="text-sm">
                                {unit.title} · {unit.state}
                            </p>
                        </Section>
                    ) : null}
                    <Section title={t('deals.sections.commission')}>
                        {commission ? (
                            <Facts
                                rows={[
                                    { label: t('commissions.col_amount'), value: <Num>{commission.amount}</Num> },
                                    { label: t('commissions.col_basis'), value: <>{commission.rate} × <Num>{commission.base}</Num> · {commission.source}</> },
                                    { label: t('commissions.col_status'), value: commission.status },
                                ]}
                            />
                        ) : (
                            <p className="text-sm text-muted-foreground">{t('deals.commission_admin')}</p>
                        )}
                    </Section>
                    <Section title={t('deals.sections.history')}>
                        <RevisionBlock rows={history} />
                    </Section>
                </div>
                <div className="order-1 space-y-4 lg:sticky lg:top-20 lg:order-2">
                    <Section title={t('deals.sections.move')}>
                        {!deal.open ? <p className="text-sm text-muted-foreground">{t('deals.final')}</p> : null}
                        {deal.open && deal.next ? (
                            <>
                                <TextareaField label={t('deals.note')} rows={2} value={note} onChange={(v) => setNote(v.slice(0, 500))} />
                                <ConfirmAction
                                    title={t('deals.forward', { stage: deal.next })}
                                    consequence={t('deals.forward_consequence')}
                                    confirmLabel={t('deals.forward', { stage: deal.next })}
                                    tone="default"
                                    trigger={
                                        <Button type="button" className="w-full">
                                            {t('deals.forward', { stage: deal.next })}
                                        </Button>
                                    }
                                    onConfirm={() => router.post(`${base}/forward`, { note }, opts)}
                                />
                            </>
                        ) : null}
                        {deal.open && deal.previous ? (
                            <ReasonDialog
                                title={t('deals.back', { stage: deal.previous })}
                                consequence={t('deals.back_consequence')}
                                confirmLabel={t('deals.back', { stage: deal.previous })}
                                tone="default"
                                label={t('revision.note')}
                                trigger={
                                    <Button type="button" variant="outline" className="w-full">
                                        {t('deals.back', { stage: deal.previous })}
                                    </Button>
                                }
                                onConfirm={(text) => router.post(`${base}/back`, { note: text }, opts)}
                            />
                        ) : null}
                        {deal.open ? (
                            <ReasonDialog
                                title={t('deals.cancel')}
                                consequence={t('deals.cancel_consequence')}
                                confirmLabel={t('deals.cancel')}
                                trigger={
                                    <Button type="button" variant="ghost" className="w-full text-error">
                                        {t('deals.cancel')}
                                    </Button>
                                }
                                onConfirm={(reason) => router.post(`${base}/cancel`, { reason }, opts)}
                            />
                        ) : null}
                    </Section>
                </div>
            </div>
        </DashboardLayout>
    );
}
