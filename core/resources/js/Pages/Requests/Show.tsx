import { Link, useForm, usePage } from '@inertiajs/react';

import { SelectField, TextareaField } from '@/Components/form/TextField';
import { ReasonDialog } from '@/Components/review/ReasonDialog';
import { RevisionBlock } from '@/Components/review/RevisionBlock';
import { StateBadge } from '@/Components/review/StateBadge';
import { Alert } from '@/Components/ui/alert';
import { Num } from '@/Components/ui/bidi';
import { Button } from '@/Components/ui/button';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useT } from '@/lib/i18n';
import { Facts, Section } from '@/Pages/People/Show';
import { requestTone } from '@/Pages/Requests/Index';

interface Props {
    request: {
        id: number;
        reference: string;
        state: string;
        state_label: string;
        open: boolean;
        readiness: string;
        question: string | null;
        fee: string;
        fee_at: string;
        closed_reason: string | null;
        assigned_to: number | null;
        staff_note: string | null;
        created_at: string | null;
    };
    unit: { id: number; title: string; state: string } | null;
    buyer: { id: number; name: string; email: string; phone: string } | null;
    staff: { value: string; label: string }[];
    history: { at: string; event: string; by: string; note: string | null }[];
}

/** One unit request (part 7.4): what the buyer sent, the unit, and the team's follow-up. */
export default function RequestShow({ request, unit, buyer, staff, history }: Props) {
    const t = useT();
    const { errors } = usePage().props as { errors?: Record<string, string> };
    const form = useForm({ action: 'save', assigned_to: request.assigned_to !== null ? String(request.assigned_to) : '', staff_note: request.staff_note ?? '', reason: '' });
    const send = (action: 'save' | 'contacted' | 'close', reason = '') => {
        form.transform((data) => ({ ...data, action, reason, assigned_to: data.assigned_to === '' ? null : Number(data.assigned_to) }));
        form.put(`/dashboard/requests/${request.id}`, { preserveScroll: true });
    };

    return (
        <DashboardLayout title={request.reference} crumbs={[{ label: t('requests.title'), href: '/dashboard/requests' }, { label: request.reference }]}>
            <div className="flex flex-wrap items-center gap-2">
                <StateBadge tone={requestTone(request.state)}>{request.state_label}</StateBadge>
                <Num className="font-mono text-sm text-muted-foreground">{request.created_at ?? ''}</Num>
            </div>
            {Object.values(errors ?? {}).length > 0 ? <Alert tone="error">{Object.values(errors ?? {}).join(' ')}</Alert> : null}

            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div className="order-2 min-w-0 space-y-6 lg:order-1">
                    <Section title={t('requests.sections.request')}>
                        <Facts
                            rows={[
                                { label: t('requests.readiness'), value: request.readiness },
                                { label: t('requests.fee'), value: <Num>{request.fee}</Num> },
                                { label: t('requests.fee_at'), value: <Num>{request.fee_at}</Num> },
                                ...(request.closed_reason ? [{ label: t('requests.closed_reason'), value: request.closed_reason }] : []),
                            ]}
                        />
                        <div className="space-y-1">
                            <p className="text-xs text-muted-foreground">{t('requests.question')}</p>
                            <p className="whitespace-pre-line break-words text-sm">{request.question ?? t('requests.no_question')}</p>
                        </div>
                    </Section>
                    {buyer ? (
                        <Section
                            title={t('requests.sections.buyer')}
                            actions={
                                <span className="flex flex-wrap gap-3 text-sm">
                                    <Link href={`/dashboard/people/${buyer.id}`} className="underline underline-offset-4">
                                        {t('requests.buyer_profile')}
                                    </Link>
                                    <Link href={`/dashboard/matching?buyer=${buyer.id}`} className="underline underline-offset-4">
                                        {t('requests.match')}
                                    </Link>
                                </span>
                            }
                        >
                            <Facts
                                rows={[
                                    { label: t('staff.name'), value: buyer.name },
                                    { label: t('units.email'), value: <span dir="ltr" className="break-all">{buyer.email}</span> },
                                    { label: t('units.phone'), value: <Num>{buyer.phone}</Num> },
                                ]}
                            />
                        </Section>
                    ) : null}
                    {unit ? (
                        <Section
                            title={t('requests.sections.unit')}
                            actions={
                                <Link href={`/dashboard/units/${unit.id}`} className="text-sm underline underline-offset-4">
                                    {t('requests.open_unit')}
                                </Link>
                            }
                        >
                            <p className="text-sm">
                                {unit.title} · {unit.state}
                            </p>
                        </Section>
                    ) : null}
                    <Section title={t('requests.sections.history')}>
                        <RevisionBlock rows={history} />
                    </Section>
                </div>
                <div className="order-1 space-y-4 lg:sticky lg:top-20 lg:order-2">
                    <Section title={t('requests.sections.work')}>
                        <SelectField label={t('requests.assigned_to')} value={form.data.assigned_to} placeholder={t('requests.unassigned')} options={staff} onChange={(v) => form.setData('assigned_to', v)} error={form.errors.assigned_to ?? null} />
                        <TextareaField label={t('requests.staff_note')} rows={4} value={form.data.staff_note} onChange={(v) => form.setData('staff_note', v.slice(0, 2000))} error={form.errors.staff_note ?? null} />
                        <div className="flex flex-col gap-2">
                            <Button type="button" disabled={form.processing} onClick={() => send('save')}>
                                {t('requests.save')}
                            </Button>
                            {request.open && request.state === 'new' ? (
                                <Button type="button" variant="outline" disabled={form.processing} onClick={() => send('contacted')}>
                                    {t('requests.contacted')}
                                </Button>
                            ) : null}
                            {request.open ? (
                                <ReasonDialog
                                    title={t('requests.close')}
                                    consequence={t('requests.close_consequence')}
                                    confirmLabel={t('requests.close')}
                                    trigger={
                                        <Button type="button" variant="ghost" className="w-full text-error">
                                            {t('requests.close')}
                                        </Button>
                                    }
                                    onConfirm={(reason) => send('close', reason)}
                                />
                            ) : null}
                        </div>
                    </Section>
                </div>
            </div>
        </DashboardLayout>
    );
}
