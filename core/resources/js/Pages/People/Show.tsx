import { Link, router, usePage } from '@inertiajs/react';

import { ReasonDialog } from '@/Components/review/ReasonDialog';
import { RevisionBlock } from '@/Components/review/RevisionBlock';
import { StateBadge } from '@/Components/review/StateBadge';
import { Alert } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Num } from '@/Components/ui/bidi';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useT } from '@/lib/i18n';

export interface BudgetView {
    id: number;
    user_id: number;
    cash: string;
    installment: string;
    frequency: string;
    monthly: string;
    types: string[];
    areas: string[];
    compounds: string[];
    target: string | null;
    bedrooms: number | null;
    delivery: string;
    updated_at: string | null;
}

interface Props {
    person: {
        id: number;
        name: string;
        email: string;
        phone: string;
        locale: string;
        verified: boolean;
        created_at: string | null;
        suspended_at: string | null;
        suspended_reason: string | null;
    };
    units: { id: number; label: string; state: string; held: boolean }[];
    drafts: number;
    requests: { id: number; reference: string; unit: string | null; state: string; at: string | null }[];
    budget: BudgetView | null;
    history: { at: string; event: string; by: string; note: string | null }[];
    canSuspend: boolean;
}

export function Section({ title, actions, children }: { title: string; actions?: React.ReactNode; children: React.ReactNode }) {
    return (
        <Card className="space-y-4 p-4 sm:p-5">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b pb-3">
                <h2 className="text-base font-semibold">{title}</h2>
                {actions}
            </div>
            {children}
        </Card>
    );
}

export function Facts({ rows }: { rows: { label: string; value: React.ReactNode }[] }) {
    return (
        <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
            {rows.map((row) => (
                <div key={row.label} className="min-w-0">
                    <dt className="text-xs text-muted-foreground">{row.label}</dt>
                    <dd className="mt-0.5 break-words">{row.value ?? '—'}</dd>
                </div>
            ))}
        </dl>
    );
}

export function BudgetFacts({ budget }: { budget: BudgetView }) {
    const t = useT();

    return (
        <Facts
            rows={[
                { label: t('budgets.cash'), value: <Num>{budget.cash}</Num> },
                {
                    label: t('budgets.installment'),
                    value: (
                        <>
                            <Num>{budget.installment}</Num> · {budget.frequency} · {t('budgets.per_month', { amount: budget.monthly })}
                        </>
                    ),
                },
                { label: t('budgets.types'), value: budget.types.length > 0 ? budget.types.join(' · ') : t('budgets.any') },
                { label: t('budgets.areas'), value: budget.areas.length > 0 ? budget.areas.join(' · ') : t('budgets.any') },
                { label: t('budgets.compounds'), value: budget.compounds.length > 0 ? budget.compounds.join(' · ') : t('budgets.any') },
                { label: t('budgets.target'), value: budget.target ? <Num>{budget.target}</Num> : null },
                { label: t('budgets.bedrooms'), value: budget.bedrooms },
                { label: t('budgets.delivery'), value: budget.delivery },
            ]}
        />
    );
}

/** One seller or buyer (part 7.4; G4): their account, units, budget, requests and history; suspension for an admin. */
export default function PeopleShow({ person, units, drafts, requests, budget, history, canSuspend }: Props) {
    const t = useT();
    const { errors } = usePage().props as { errors?: Record<string, string> };
    const opts = { preserveScroll: true } as const;

    return (
        <DashboardLayout title={person.name} crumbs={[{ label: t('people.title'), href: '/dashboard/people' }, { label: person.name }]}>
            <div className="flex flex-wrap items-center gap-2">
                {person.suspended_at ? <StateBadge tone="stopped">{t('people.suspended')}</StateBadge> : null}
                <Badge variant={person.verified ? 'success' : 'warning'}>{person.verified ? t('people.verified') : t('people.unverified')}</Badge>
            </div>
            {Object.values(errors ?? {}).length > 0 ? <Alert tone="error">{Object.values(errors ?? {}).join(' ')}</Alert> : null}

            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div className="order-2 min-w-0 space-y-6 lg:order-1">
                    <Section title={t('people.sections.profile')}>
                        <Facts
                            rows={[
                                { label: t('units.email'), value: <span dir="ltr" className="break-all">{person.email}</span> },
                                { label: t('units.phone'), value: <Num>{person.phone}</Num> },
                                { label: t('people.locale'), value: person.locale === 'en' ? t('staff.locale_en') : t('staff.locale_ar') },
                                { label: t('people.joined'), value: <Num>{person.created_at ?? ''}</Num> },
                            ]}
                        />
                    </Section>
                    <Section title={t('people.sections.units')}>
                        {units.length === 0 ? <p className="text-sm text-muted-foreground">{t('people.no_units')}</p> : null}
                        <ul className="divide-y text-sm">
                            {units.map((u) => (
                                <li key={u.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                                    <Link href={`/dashboard/units/${u.id}`} className="underline-offset-4 hover:underline">
                                        {u.label}
                                    </Link>
                                    <span className="flex items-center gap-1.5">
                                        <Badge variant="neutral">{u.state}</Badge>
                                        {u.held ? <Badge variant="destructive">{t('people.held')}</Badge> : null}
                                    </span>
                                </li>
                            ))}
                        </ul>
                        {drafts > 0 ? <p className="text-xs text-muted-foreground">{t('people.drafts', { count: drafts })}</p> : null}
                    </Section>
                    <Section
                        title={t('people.sections.budget')}
                        actions={
                            budget ? (
                                <Link href={`/dashboard/matching?buyer=${person.id}`} className="text-sm underline underline-offset-4">
                                    {t('people.find_units')}
                                </Link>
                            ) : null
                        }
                    >
                        {budget ? <BudgetFacts budget={budget} /> : <p className="text-sm text-muted-foreground">{t('people.no_budget')}</p>}
                    </Section>
                    <Section title={t('people.sections.requests')}>
                        {requests.length === 0 ? <p className="text-sm text-muted-foreground">{t('people.no_requests')}</p> : null}
                        <ul className="divide-y text-sm">
                            {requests.map((r) => (
                                <li key={r.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                                    <Link href={`/dashboard/requests/${r.id}`} className="font-mono underline-offset-4 hover:underline" dir="ltr">
                                        {r.reference}
                                        {r.unit ? ` · ${r.unit}` : ''}
                                    </Link>
                                    <span className="flex items-center gap-2">
                                        <Badge variant="neutral">{r.state}</Badge>
                                        <Num className="font-mono text-xs text-muted-foreground">{r.at ?? ''}</Num>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </Section>
                    <Section title={t('people.sections.history')}>
                        <RevisionBlock rows={history} />
                    </Section>
                </div>
                <div className="order-1 space-y-6 lg:sticky lg:top-20 lg:order-2">
                    <Section title={t('people.sections.suspension')}>
                        {person.suspended_at ? (
                            <div className="space-y-1 text-sm">
                                <p>{t('people.suspended_since', { at: `⁦${person.suspended_at}⁩` })}</p>
                                {person.suspended_reason ? <p className="text-muted-foreground">“{person.suspended_reason}”</p> : null}
                            </div>
                        ) : null}
                        {canSuspend ? (
                            person.suspended_at ? (
                                <ReasonDialog
                                    title={t('people.lift')}
                                    consequence={t('people.lift_consequence')}
                                    confirmLabel={t('people.lift')}
                                    tone="default"
                                    trigger={
                                        <Button type="button" variant="outline" className="w-full">
                                            {t('people.lift')}
                                        </Button>
                                    }
                                    onConfirm={(reason) => router.post(`/dashboard/people/${person.id}/lift`, { reason }, opts)}
                                />
                            ) : (
                                <ReasonDialog
                                    title={t('people.suspend')}
                                    consequence={t('people.suspend_consequence')}
                                    confirmLabel={t('people.suspend')}
                                    trigger={
                                        <Button type="button" variant="outline" className="w-full text-error">
                                            {t('people.suspend')}
                                        </Button>
                                    }
                                    onConfirm={(reason) => router.post(`/dashboard/people/${person.id}/suspend`, { reason }, opts)}
                                />
                            )
                        ) : (
                            <p className="text-sm text-muted-foreground">{t('people.admin_only')}</p>
                        )}
                    </Section>
                </div>
            </div>
        </DashboardLayout>
    );
}
