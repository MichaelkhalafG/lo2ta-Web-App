import { Link, router } from '@inertiajs/react';

import { Alert } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Num } from '@/Components/ui/bidi';
import { Card } from '@/Components/ui/card';
import { Select } from '@/Components/ui/input';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useT } from '@/lib/i18n';

interface Row {
    id: number;
    title: string;
    subtitle: string | null;
    score: number;
    ranked: string[];
    facts: { cash: string; monthly: string };
}

interface Props {
    mode: 'unit' | 'buyer' | null;
    subject: { id: number; title: string; cash_needed: string; monthly: string } | null;
    rows: Row[];
    pick: { units: { id: number; label: string }[]; buyers: { id: number; label: string }[] } | null;
}

/** Matching (PLAN 2.4; part 7.4; G9, G10; both roles): buyers for a unit, or units for a buyer, best first. */
export default function MatchingIndex({ mode, subject, rows, pick }: Props) {
    const t = useT();

    return (
        <DashboardLayout title={t('matching.title')} crumbs={[{ label: t('matching.title'), href: '/dashboard/matching' }, ...(subject ? [{ label: subject.title }] : [])]}>
            <Alert tone="info">{t('matching.intro')}</Alert>

            {pick ? (
                <Card className="grid gap-4 p-4 sm:grid-cols-2 sm:p-5">
                    <label className="space-y-1.5 text-sm">
                        <span className="font-medium">{t('matching.pick_unit')}</span>
                        <Select value="" onChange={(event) => event.target.value && router.get('/dashboard/matching', { unit: event.target.value })}>
                            <option value="">{t('form.choose')}</option>
                            {pick.units.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.label}
                                </option>
                            ))}
                        </Select>
                    </label>
                    <label className="space-y-1.5 text-sm">
                        <span className="font-medium">{t('matching.pick_buyer')}</span>
                        <Select value="" onChange={(event) => event.target.value && router.get('/dashboard/matching', { buyer: event.target.value })}>
                            <option value="">{t('form.choose')}</option>
                            {pick.buyers.map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.label}
                                </option>
                            ))}
                        </Select>
                    </label>
                </Card>
            ) : null}

            {subject ? (
                <Card className="space-y-4 p-4 sm:p-5">
                    <div className="space-y-1 border-b pb-3">
                        <h2 className="text-base font-semibold">{mode === 'unit' ? t('matching.for_unit', { unit: subject.title }) : t('matching.for_buyer', { buyer: subject.title })}</h2>
                        <p className="text-sm text-muted-foreground">
                            {mode === 'unit' ? t('matching.cash_needed', { amount: subject.cash_needed }) : t('matching.cash_available', { amount: subject.cash_needed })} ·{' '}
                            {t('matching.monthly', { amount: subject.monthly })}
                        </p>
                    </div>
                    {rows.length === 0 ? <p className="text-sm text-muted-foreground">{t('matching.none')}</p> : null}
                    <ol className="divide-y">
                        {rows.map((row) => (
                            <li key={row.id} className="flex flex-wrap items-start justify-between gap-3 py-3">
                                <div className="min-w-0 space-y-1">
                                    <Link href={mode === 'unit' ? `/dashboard/people/${row.id}` : `/dashboard/units/${row.id}`} className="font-medium underline-offset-4 hover:underline">
                                        {row.title}
                                    </Link>
                                    {row.subtitle ? (
                                        <p className="break-all text-xs text-muted-foreground" dir="auto">
                                            {row.subtitle}
                                        </p>
                                    ) : null}
                                    <div className="flex flex-wrap gap-1">
                                        {row.ranked.map((r) => (
                                            <Badge key={r} variant="success">
                                                {t(`matching.ranked.${r}`, r)}
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                                <div className="space-y-0.5 text-end text-sm">
                                    <p>
                                        {t('matching.cash')}: <Num>{row.facts.cash}</Num>
                                    </p>
                                    <p>
                                        {t('matching.per_month')}: <Num>{row.facts.monthly}</Num>
                                    </p>
                                    <p className="text-xs text-muted-foreground">{t('matching.score', { score: row.score })}</p>
                                </div>
                            </li>
                        ))}
                    </ol>
                </Card>
            ) : null}
        </DashboardLayout>
    );
}
