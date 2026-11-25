import { router, useForm } from '@inertiajs/react';
import { ExternalLink, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

import { FormActions } from '@/Components/form/FormActions';
import { SelectField, TextareaField, TextField } from '@/Components/form/TextField';
import { SwitchField } from '@/Components/form/SwitchField';
import { ConfirmAction } from '@/Components/shell/ConfirmAction';
import { Alert } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Dialog, DialogContent } from '@/Components/ui/dialog';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useT, type Translate } from '@/lib/i18n';
import { bpToPercent, percentToBp, wholeNumber } from '@/lib/percent';

interface SettingField {
    key: string;
    label: string;
    type: 'int' | 'bool' | 'enum' | 'string';
    unit: 'pct' | 'egp' | 'years' | 'none';
    value: number | boolean | string;
    min: number | null;
    max: number | null;
    options: { value: string; label: string }[];
}

interface Source {
    id: number;
    ref: string;
    publisher: string;
    url: string | null;
    published_on: string | null;
    read_on: string;
    figure: string;
    origin: string | null;
    counted: boolean;
}

interface MarketValue {
    key: string;
    label: string;
    status: 'current' | 'recheck' | 'stale';
    checked_on: string;
    confirmed: boolean;
    confirmed_note: string | null;
    note: string | null;
    settings: SettingField[];
    sources: Source[];
}

interface Policy {
    id: number;
    developer: string | null;
    developer_name: string | null;
    penalty_basis: string | null;
    penalty_min_bp: number | null;
    penalty_max_bp: number | null;
    refund_rule: string | null;
    refund_years_fixed: number | null;
    late_fee_bp: number | null;
    transfer_fee_mode: string | null;
    transfer_fee_bp: number | null;
    transfer_fee_fixed: number | null;
    source_title: string;
    source_url: string;
    source_published_on: string | null;
    checked_on: string;
    approved: boolean;
    note: string | null;
}

interface Props {
    values: MarketValue[];
    display: { fields: SettingField[]; confirmed: boolean };
    policies: Policy[];
    developers: { value: string; label: string }[];
    market: { penalty_basis: string; refund_rule: string; transfer_fee_mode: string };
}

/**
 * Calculator settings (Phase 6, admin only). Each researched value: its settings, the date checked, an internal
 * note, the client's confirmation and its sources. Sources and notes never reach the public site (J10).
 */
export default function CalculatorIndex({ values, display, policies, developers }: Props) {
    const t = useT();

    return (
        <DashboardLayout title={t('calculator.title')} crumbs={[{ label: t('nav_items.calculator') }]}>
            <Alert tone="info">{t('calculator.intro')}</Alert>

            {values.map((value) => (
                <ValueCard key={value.key} value={value} />
            ))}

            <DisplayCard display={display} />

            <PoliciesCard policies={policies} developers={developers} />
        </DashboardLayout>
    );
}

/** Text in the field for a setting's current value: percentages for shares, plain numbers otherwise. */
function fieldText(field: SettingField, value: number | boolean | string | null): string {
    if (value === null || typeof value === 'boolean') {
        return '';
    }
    if (field.unit === 'pct' && typeof value === 'number') {
        return bpToPercent(value);
    }

    return String(value);
}

function fieldValue(field: SettingField, text: string | boolean): number | boolean | string | null {
    if (typeof text === 'boolean') {
        return text;
    }
    if (field.type === 'enum' || field.type === 'string') {
        return text;
    }

    return field.unit === 'pct' ? percentToBp(text) : wholeNumber(text);
}

function SettingInput({
    field,
    value,
    onChange,
    error,
}: {
    field: SettingField;
    value: string | boolean;
    onChange: (value: string | boolean) => void;
    error: string | undefined;
}) {
    const t = useT();
    if (field.type === 'bool') {
        return <SwitchField label={field.label} checked={value === true} onChange={onChange} error={error ?? null} />;
    }
    if (field.type === 'enum') {
        return <SelectField label={field.label} value={String(value)} onChange={onChange} options={field.options} error={error ?? null} />;
    }
    const hint =
        field.unit === 'pct'
            ? t('calculator.hint_pct', { min: bpToPercent(field.min), max: bpToPercent(field.max) })
            : field.unit === 'egp'
              ? t('calculator.hint_egp')
              : field.unit === 'years'
                ? t('calculator.hint_years', { max: field.max ?? 0 })
                : undefined;

    return <TextField label={field.label} value={String(value)} onChange={onChange} dir="ltr" inputMode="decimal" {...(hint !== undefined ? { hint } : {})} error={error ?? null} />;
}

function StatusBadges({ value, t }: { value: MarketValue; t: Translate }) {
    return (
        <div className="flex flex-wrap items-center gap-1.5">
            {value.confirmed ? <Badge variant="success">{t('calculator.badge_confirmed')}</Badge> : <Badge variant="warning">{t('calculator.badge_provisional')}</Badge>}
            {value.status === 'recheck' ? <Badge variant="warning">{t('calculator.status.recheck')}</Badge> : null}
            {value.status === 'stale' ? <Badge variant="destructive">{t('calculator.status.stale')}</Badge> : null}
        </div>
    );
}

function ValueCard({ value }: { value: MarketValue }) {
    const t = useT();
    const initial: Record<string, string | boolean> = {};
    for (const field of value.settings) {
        initial[field.key] = field.type === 'bool' ? field.value === true : fieldText(field, field.value);
    }
    const form = useForm({ settings: initial, checked_on: value.checked_on, note: value.note ?? '', confirmed: value.confirmed });
    const errors = form.errors as Record<string, string | undefined>;

    form.transform((data) => {
        const settings: Record<string, number | boolean | string | null> = {};
        for (const field of value.settings) {
            const text = data.settings[field.key];
            settings[field.key] = fieldValue(field, text ?? '');
        }

        return { ...data, settings };
    });

    return (
        <Card>
            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3 space-y-0">
                <div className="space-y-1">
                    <CardTitle>{value.label}</CardTitle>
                    <CardDescription>{t(`calculator.explain.${value.key}`)}</CardDescription>
                </div>
                <StatusBadges value={value} t={t} />
            </CardHeader>
            <CardContent className="space-y-6">
                <form
                    className="space-y-5"
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(`/dashboard/calculator/values/${value.key}`, { preserveScroll: true });
                    }}
                >
                    {errors.settings ? <Alert tone="error">{errors.settings}</Alert> : null}
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {value.settings.map((field) => (
                            <SettingInput
                                key={field.key}
                                field={field}
                                value={form.data.settings[field.key] ?? ''}
                                onChange={(next) => form.setData('settings', { ...form.data.settings, [field.key]: next })}
                                error={errors[`settings.${field.key}`]}
                            />
                        ))}
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <TextField
                            label={t('calculator.checked_on')}
                            type="date"
                            dir="ltr"
                            value={form.data.checked_on}
                            onChange={(next) => form.setData('checked_on', next)}
                            hint={t('calculator.checked_on_hint')}
                            error={errors.checked_on ?? null}
                            required
                        />
                    </div>
                    <TextareaField
                        label={t('calculator.note')}
                        value={form.data.note}
                        onChange={(next) => form.setData('note', next)}
                        rows={3}
                        hint={t('calculator.note_hint')}
                        error={errors.note ?? null}
                    />
                    <div className="space-y-1">
                        <SwitchField label={t('calculator.confirmed')} checked={form.data.confirmed} onChange={(next) => form.setData('confirmed', next)} hint={t('calculator.confirmed_hint')} />
                        {value.confirmed_note ? <p className="text-xs text-muted-foreground">{value.confirmed_note}</p> : null}
                    </div>
                    <FormActions processing={form.processing} dirty={form.isDirty} />
                </form>

                <SourcesTable value={value} />
            </CardContent>
        </Card>
    );
}

function SourcesTable({ value }: { value: MarketValue }) {
    const t = useT();
    const [editing, setEditing] = useState<Source | 'new' | null>(null);

    return (
        <section className="space-y-3 border-t pt-5" aria-labelledby={`sources-${value.key}`}>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 id={`sources-${value.key}`} className="text-sm font-semibold">
                        {t('calculator.sources')}
                    </h3>
                    <p className="text-xs text-muted-foreground">{t('calculator.sources_hint')}</p>
                </div>
                <Button type="button" variant="outline" size="sm" className="gap-1.5" onClick={() => setEditing('new')}>
                    <Plus className="h-4 w-4" aria-hidden="true" />
                    {t('calculator.add_source')}
                </Button>
            </div>
            {value.sources.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t('calculator.no_sources')}</p>
            ) : (
                <ul className="divide-y rounded-md border">
                    {value.sources.map((source) => (
                        <li key={source.id} className="space-y-1.5 p-3 text-sm">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-mono text-xs text-muted-foreground" dir="ltr">
                                        {source.ref}
                                    </span>
                                    {source.url ? (
                                        <a href={source.url} target="_blank" rel="noreferrer noopener" className="inline-flex items-center gap-1 font-medium hover:underline">
                                            {source.publisher}
                                            <ExternalLink className="h-3.5 w-3.5" aria-hidden="true" />
                                        </a>
                                    ) : (
                                        <span className="font-medium">{source.publisher}</span>
                                    )}
                                    <span className="text-xs text-muted-foreground" dir="ltr">
                                        {source.published_on ?? '—'}
                                    </span>
                                    {source.counted ? null : <Badge variant="neutral">{t('calculator.reference_only')}</Badge>}
                                </div>
                                <div className="flex items-center gap-1">
                                    <Button type="button" variant="ghost" size="sm" aria-label={t('calculator.edit_source')} onClick={() => setEditing(source)}>
                                        <Pencil className="h-4 w-4" aria-hidden="true" />
                                    </Button>
                                    <ConfirmAction
                                        title={t('calculator.delete_source')}
                                        consequence={t('calculator.delete_source_consequence', { ref: source.ref })}
                                        confirmLabel={t('calculator.delete_source')}
                                        trigger={
                                            <Button type="button" variant="ghost" size="sm" aria-label={t('calculator.delete_source')}>
                                                <Trash2 className="h-4 w-4" aria-hidden="true" />
                                            </Button>
                                        }
                                        onConfirm={() => router.delete(`/dashboard/calculator/values/${value.key}/sources/${source.id}`, { preserveScroll: true })}
                                    />
                                </div>
                            </div>
                            <p className="text-muted-foreground">{source.figure}</p>
                            {source.origin ? (
                                <p className="text-xs text-muted-foreground">
                                    {t('calculator.origin')}: <span dir="ltr">{source.origin}</span>
                                </p>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}
            <Dialog open={editing !== null} onOpenChange={(open) => (open ? null : setEditing(null))}>
                {editing !== null ? (
                    <DialogContent title={editing === 'new' ? t('calculator.add_source') : t('calculator.edit_source')} className="max-h-[90dvh] overflow-y-auto">
                        <SourceForm valueKey={value.key} source={editing === 'new' ? null : editing} onDone={() => setEditing(null)} />
                    </DialogContent>
                ) : null}
            </Dialog>
        </section>
    );
}

function SourceForm({ valueKey, source, onDone }: { valueKey: string; source: Source | null; onDone: () => void }) {
    const t = useT();
    const today = new Date().toISOString().slice(0, 10);
    const form = useForm({
        ref: source?.ref ?? '',
        publisher: source?.publisher ?? '',
        url: source?.url ?? '',
        published_on: source?.published_on ?? '',
        read_on: source?.read_on ?? today,
        figure: source?.figure ?? '',
        origin: source?.origin ?? '',
        counted: source?.counted ?? true,
    });
    const url = `/dashboard/calculator/values/${valueKey}/sources${source ? `/${source.id}` : ''}`;

    return (
        <form
            className="space-y-4"
            noValidate
            onSubmit={(event) => {
                event.preventDefault();
                const options = { preserveScroll: true, onSuccess: onDone };
                if (source) {
                    form.put(url, options);
                } else {
                    form.post(url, options);
                }
            }}
        >
            <div className="grid gap-4 sm:grid-cols-[8rem_1fr]">
                <TextField label={t('calculator.source_ref')} value={form.data.ref} onChange={(v) => form.setData('ref', v)} dir="ltr" required error={form.errors.ref ?? null} />
                <TextField label={t('calculator.source_publisher')} value={form.data.publisher} onChange={(v) => form.setData('publisher', v)} required error={form.errors.publisher ?? null} />
            </div>
            <TextField label={t('calculator.source_url')} value={form.data.url} onChange={(v) => form.setData('url', v)} type="url" dir="ltr" error={form.errors.url ?? null} />
            <div className="grid gap-4 sm:grid-cols-2">
                <TextField label={t('calculator.source_published_on')} type="date" dir="ltr" value={form.data.published_on} onChange={(v) => form.setData('published_on', v)} error={form.errors.published_on ?? null} />
                <TextField label={t('calculator.source_read_on')} type="date" dir="ltr" value={form.data.read_on} onChange={(v) => form.setData('read_on', v)} required error={form.errors.read_on ?? null} />
            </div>
            <TextareaField label={t('calculator.source_figure')} value={form.data.figure} onChange={(v) => form.setData('figure', v)} rows={4} required hint={t('calculator.source_figure_hint')} error={form.errors.figure ?? null} />
            <TextField label={t('calculator.origin')} value={form.data.origin} onChange={(v) => form.setData('origin', v)} dir="ltr" hint={t('calculator.origin_hint')} error={form.errors.origin ?? null} />
            <SwitchField label={t('calculator.counted')} checked={form.data.counted} onChange={(v) => form.setData('counted', v)} hint={t('calculator.counted_hint')} />
            <FormActions processing={form.processing} dirty={form.isDirty} onCancel={onDone} />
        </form>
    );
}

function DisplayCard({ display }: { display: Props['display'] }) {
    const t = useT();
    const line = display.fields.find((f) => f.key === 'deep_loss_ratio_bp');
    const pv = display.fields.find((f) => f.key === 'show_present_value');
    const form = useForm({
        deep_loss_ratio_bp: line ? fieldText(line, line.value) : '',
        show_present_value: pv?.value === true,
        confirmed: display.confirmed,
    });
    form.transform((data) => ({ ...data, deep_loss_ratio_bp: percentToBp(data.deep_loss_ratio_bp) }));

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('calculator.display_title')}</CardTitle>
                <CardDescription>{t('calculator.display_hint')}</CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    className="space-y-5"
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put('/dashboard/calculator/display', { preserveScroll: true });
                    }}
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            label={t('setting_labels.deep_loss_ratio_bp')}
                            value={form.data.deep_loss_ratio_bp}
                            onChange={(v) => form.setData('deep_loss_ratio_bp', v)}
                            dir="ltr"
                            inputMode="decimal"
                            hint={t('calculator.deep_loss_hint')}
                            error={form.errors.deep_loss_ratio_bp ?? null}
                        />
                    </div>
                    <SwitchField label={t('setting_labels.show_present_value')} checked={form.data.show_present_value} onChange={(v) => form.setData('show_present_value', v)} hint={t('calculator.present_value_hint')} />
                    <SwitchField label={t('calculator.confirmed')} checked={form.data.confirmed} onChange={(v) => form.setData('confirmed', v)} hint={t('calculator.display_confirmed_hint')} />
                    <FormActions processing={form.processing} dirty={form.isDirty} />
                </form>
            </CardContent>
        </Card>
    );
}

function PoliciesCard({ policies, developers }: { policies: Policy[]; developers: Props['developers'] }) {
    const t = useT();
    const [editing, setEditing] = useState<Policy | 'new' | null>(null);

    return (
        <Card>
            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3 space-y-0">
                <div className="space-y-1">
                    <CardTitle>{t('calculator.policies_title')}</CardTitle>
                    <CardDescription>{t('calculator.policies_hint')}</CardDescription>
                </div>
                <Button type="button" variant="outline" size="sm" className="gap-1.5" onClick={() => setEditing('new')} disabled={developers.length === 0}>
                    <Plus className="h-4 w-4" aria-hidden="true" />
                    {t('calculator.add_policy')}
                </Button>
            </CardHeader>
            <CardContent>
                {policies.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t('calculator.no_policies')}</p>
                ) : (
                    <ul className="divide-y rounded-md border">
                        {policies.map((policy) => (
                            <li key={policy.id} className="flex flex-wrap items-center justify-between gap-3 p-3 text-sm">
                                <div className="space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">{policy.developer_name ?? '—'}</span>
                                        {policy.approved ? <Badge variant="success">{t('calculator.policy_approved')}</Badge> : <Badge variant="warning">{t('calculator.policy_not_approved')}</Badge>}
                                    </div>
                                    <a href={policy.source_url} target="_blank" rel="noreferrer noopener" className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:underline">
                                        {policy.source_title}
                                        <ExternalLink className="h-3 w-3" aria-hidden="true" />
                                    </a>
                                </div>
                                <div className="flex items-center gap-1">
                                    <Button type="button" variant="ghost" size="sm" aria-label={t('calculator.edit_policy')} onClick={() => setEditing(policy)}>
                                        <Pencil className="h-4 w-4" aria-hidden="true" />
                                    </Button>
                                    <ConfirmAction
                                        title={t('calculator.delete_policy')}
                                        consequence={t('calculator.delete_policy_consequence', { name: policy.developer_name ?? '' })}
                                        confirmLabel={t('calculator.delete_policy')}
                                        trigger={
                                            <Button type="button" variant="ghost" size="sm" aria-label={t('calculator.delete_policy')}>
                                                <Trash2 className="h-4 w-4" aria-hidden="true" />
                                            </Button>
                                        }
                                        onConfirm={() => router.delete(`/dashboard/calculator/policies/${policy.id}`, { preserveScroll: true })}
                                    />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
            <Dialog open={editing !== null} onOpenChange={(open) => (open ? null : setEditing(null))}>
                {editing !== null ? (
                    <DialogContent title={editing === 'new' ? t('calculator.add_policy') : t('calculator.edit_policy')} className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                        <PolicyForm policy={editing === 'new' ? null : editing} developers={developers} onDone={() => setEditing(null)} />
                    </DialogContent>
                ) : null}
            </Dialog>
        </Card>
    );
}

function PolicyForm({ policy, developers, onDone }: { policy: Policy | null; developers: Props['developers']; onDone: () => void }) {
    const t = useT();
    const today = new Date().toISOString().slice(0, 10);
    const form = useForm({
        developer: policy?.developer ?? '',
        penalty_basis: policy?.penalty_basis ?? '',
        penalty_min_bp: bpToPercent(policy?.penalty_min_bp),
        penalty_max_bp: bpToPercent(policy?.penalty_max_bp),
        refund_rule: policy?.refund_rule ?? '',
        refund_years_fixed: policy?.refund_years_fixed?.toString() ?? '',
        late_fee_bp: bpToPercent(policy?.late_fee_bp),
        transfer_fee_mode: policy?.transfer_fee_mode ?? '',
        transfer_fee_bp: bpToPercent(policy?.transfer_fee_bp),
        transfer_fee_fixed: policy?.transfer_fee_fixed?.toString() ?? '',
        source_title: policy?.source_title ?? '',
        source_url: policy?.source_url ?? '',
        source_published_on: policy?.source_published_on ?? '',
        checked_on: policy?.checked_on ?? today,
        approved: policy?.approved ?? false,
        note: policy?.note ?? '',
    });
    form.transform((data) => ({
        ...data,
        penalty_basis: data.penalty_basis || null,
        refund_rule: data.refund_rule || null,
        transfer_fee_mode: data.transfer_fee_mode || null,
        penalty_min_bp: percentToBp(data.penalty_min_bp),
        penalty_max_bp: percentToBp(data.penalty_max_bp),
        late_fee_bp: percentToBp(data.late_fee_bp),
        transfer_fee_bp: percentToBp(data.transfer_fee_bp),
        refund_years_fixed: wholeNumber(data.refund_years_fixed),
        transfer_fee_fixed: wholeNumber(data.transfer_fee_fixed),
    }));
    const market = t('calculator.policy_market');
    const choose = (key: string, options: string[]) => [{ value: '', label: market }, ...options.map((o) => ({ value: o, label: t(`calculator.options.${key}.${o}`) }))];
    const url = policy ? `/dashboard/calculator/policies/${policy.id}` : '/dashboard/calculator/policies';

    return (
        <form
            className="space-y-4"
            noValidate
            onSubmit={(event) => {
                event.preventDefault();
                const options = { preserveScroll: true, onSuccess: onDone };
                if (policy) {
                    form.put(url, options);
                } else {
                    form.post(url, options);
                }
            }}
        >
            <Alert tone="warning">{t('calculator.policy_rule')}</Alert>
            <SelectField label={t('calculator.policy_developer')} value={form.data.developer} onChange={(v) => form.setData('developer', v)} options={developers} placeholder={t('form.choose')} required error={form.errors.developer ?? null} />
            <p className="text-xs text-muted-foreground">{t('calculator.policy_overrides_hint')}</p>
            <div className="grid gap-4 sm:grid-cols-3">
                <SelectField label={t('setting_labels.penalty_basis')} value={form.data.penalty_basis} onChange={(v) => form.setData('penalty_basis', v)} options={choose('penalty_basis', ['total', 'paid'])} error={form.errors.penalty_basis ?? null} />
                <TextField label={t('setting_labels.penalty_min_bp')} value={form.data.penalty_min_bp} onChange={(v) => form.setData('penalty_min_bp', v)} dir="ltr" inputMode="decimal" placeholder={market} error={form.errors.penalty_min_bp ?? null} />
                <TextField label={t('setting_labels.penalty_max_bp')} value={form.data.penalty_max_bp} onChange={(v) => form.setData('penalty_max_bp', v)} dir="ltr" inputMode="decimal" placeholder={market} error={form.errors.penalty_max_bp ?? null} />
                <SelectField label={t('setting_labels.refund_rule')} value={form.data.refund_rule} onChange={(v) => form.setData('refund_rule', v)} options={choose('refund_rule', ['lump', 'mirror', 'fixed'])} error={form.errors.refund_rule ?? null} />
                <TextField label={t('setting_labels.refund_years_fixed')} value={form.data.refund_years_fixed} onChange={(v) => form.setData('refund_years_fixed', v)} dir="ltr" inputMode="numeric" placeholder={market} error={form.errors.refund_years_fixed ?? null} />
                <TextField label={t('setting_labels.late_fee_bp')} value={form.data.late_fee_bp} onChange={(v) => form.setData('late_fee_bp', v)} dir="ltr" inputMode="decimal" placeholder={market} error={form.errors.late_fee_bp ?? null} />
                <SelectField label={t('setting_labels.transfer_fee_mode')} value={form.data.transfer_fee_mode} onChange={(v) => form.setData('transfer_fee_mode', v)} options={choose('transfer_fee_mode', ['pct', 'fixed'])} error={form.errors.transfer_fee_mode ?? null} />
                <TextField label={t('setting_labels.transfer_fee_bp')} value={form.data.transfer_fee_bp} onChange={(v) => form.setData('transfer_fee_bp', v)} dir="ltr" inputMode="decimal" placeholder={market} error={form.errors.transfer_fee_bp ?? null} />
                <TextField label={t('setting_labels.transfer_fee_fixed')} value={form.data.transfer_fee_fixed} onChange={(v) => form.setData('transfer_fee_fixed', v)} dir="ltr" inputMode="numeric" placeholder={market} error={form.errors.transfer_fee_fixed ?? null} />
            </div>
            <TextField label={t('calculator.policy_source_title')} value={form.data.source_title} onChange={(v) => form.setData('source_title', v)} required error={form.errors.source_title ?? null} />
            <TextField label={t('calculator.policy_source_url')} value={form.data.source_url} onChange={(v) => form.setData('source_url', v)} type="url" dir="ltr" required error={form.errors.source_url ?? null} />
            <div className="grid gap-4 sm:grid-cols-2">
                <TextField label={t('calculator.source_published_on')} type="date" dir="ltr" value={form.data.source_published_on} onChange={(v) => form.setData('source_published_on', v)} error={form.errors.source_published_on ?? null} />
                <TextField label={t('calculator.checked_on')} type="date" dir="ltr" value={form.data.checked_on} onChange={(v) => form.setData('checked_on', v)} required error={form.errors.checked_on ?? null} />
            </div>
            <TextareaField label={t('calculator.note')} value={form.data.note} onChange={(v) => form.setData('note', v)} rows={2} hint={t('calculator.note_hint')} error={form.errors.note ?? null} />
            <SwitchField label={t('calculator.policy_approved_switch')} checked={form.data.approved} onChange={(v) => form.setData('approved', v)} hint={t('calculator.policy_approved_hint')} />
            <FormActions processing={form.processing} dirty={form.isDirty} onCancel={onDone} />
        </form>
    );
}
