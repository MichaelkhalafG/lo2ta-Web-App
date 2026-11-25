import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

import { FormActions } from '@/Components/form/FormActions';
import { SelectField, TextareaField, TextField } from '@/Components/form/TextField';
import { SwitchField } from '@/Components/form/SwitchField';
import { ConfirmAction } from '@/Components/shell/ConfirmAction';
import { Alert } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Dialog, DialogContent } from '@/Components/ui/dialog';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useLocale, useT } from '@/lib/i18n';

interface Indicator {
    id: number;
    title_ar: string;
    title_en: string;
    value: string;
    unit: string;
    note_ar: string | null;
    note_en: string | null;
    as_of: string;
    source: string;
    sort: number;
    is_published: boolean;
}

interface Props {
    indicators: Indicator[];
    units: { value: string; label: string }[];
}

/**
 * The reports page's indicators (Phase 6, J9): only figures the client supplies, each dated, its source internal.
 * Nothing shows on the site until one is published.
 */
export default function IndicatorsIndex({ indicators, units }: Props) {
    const t = useT();
    const locale = useLocale();
    const [editing, setEditing] = useState<Indicator | 'new' | null>(null);
    const unitLabel = (code: string) => units.find((u) => u.value === code)?.label ?? code;

    return (
        <DashboardLayout
            title={t('indicators.title')}
            crumbs={[{ label: t('nav_items.reports') }]}
            actions={
                <Button type="button" className="gap-1.5" onClick={() => setEditing('new')}>
                    <Plus className="h-4 w-4" aria-hidden="true" />
                    {t('indicators.add')}
                </Button>
            }
        >
            <Alert tone="info">{t('indicators.intro')}</Alert>

            <Card>
                <CardContent className="pt-6">
                    {indicators.length === 0 ? (
                        <p className="text-sm text-muted-foreground">{t('indicators.empty')}</p>
                    ) : (
                        <ul className="divide-y rounded-md border">
                            {indicators.map((item) => (
                                <li key={item.id} className="flex flex-wrap items-center justify-between gap-3 p-3 text-sm">
                                    <div className="space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">{locale === 'en' ? item.title_en : item.title_ar}</span>
                                            {item.is_published ? <Badge variant="success">{t('indicators.published')}</Badge> : <Badge variant="neutral">{t('indicators.hidden')}</Badge>}
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            <span dir="ltr" className="font-mono tabular-nums">
                                                {item.value}
                                            </span>{' '}
                                            {unitLabel(item.unit)} · <span dir="ltr">{item.as_of}</span>
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <Button type="button" variant="ghost" size="sm" aria-label={t('indicators.edit')} onClick={() => setEditing(item)}>
                                            <Pencil className="h-4 w-4" aria-hidden="true" />
                                        </Button>
                                        <ConfirmAction
                                            title={t('indicators.delete')}
                                            consequence={t('indicators.delete_consequence')}
                                            confirmLabel={t('indicators.delete')}
                                            trigger={
                                                <Button type="button" variant="ghost" size="sm" aria-label={t('indicators.delete')}>
                                                    <Trash2 className="h-4 w-4" aria-hidden="true" />
                                                </Button>
                                            }
                                            onConfirm={() => router.delete(`/dashboard/indicators/${item.id}`, { preserveScroll: true })}
                                        />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>

            <Dialog open={editing !== null} onOpenChange={(open) => (open ? null : setEditing(null))}>
                {editing !== null ? (
                    <DialogContent title={editing === 'new' ? t('indicators.add') : t('indicators.edit')} className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                        <IndicatorForm indicator={editing === 'new' ? null : editing} units={units} onDone={() => setEditing(null)} />
                    </DialogContent>
                ) : null}
            </Dialog>
        </DashboardLayout>
    );
}

function IndicatorForm({ indicator, units, onDone }: { indicator: Indicator | null; units: Props['units']; onDone: () => void }) {
    const t = useT();
    const form = useForm({
        title_ar: indicator?.title_ar ?? '',
        title_en: indicator?.title_en ?? '',
        value: indicator?.value ?? '',
        unit: indicator?.unit ?? 'pct',
        note_ar: indicator?.note_ar ?? '',
        note_en: indicator?.note_en ?? '',
        as_of: indicator?.as_of ?? '',
        source: indicator?.source ?? '',
        sort: indicator?.sort?.toString() ?? '0',
        is_published: indicator?.is_published ?? false,
    });
    const url = indicator ? `/dashboard/indicators/${indicator.id}` : '/dashboard/indicators';

    return (
        <form
            className="space-y-4"
            noValidate
            onSubmit={(event) => {
                event.preventDefault();
                const options = { preserveScroll: true, onSuccess: onDone };
                if (indicator) {
                    form.put(url, options);
                } else {
                    form.post(url, options);
                }
            }}
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <TextField label={t('indicators.title_ar')} value={form.data.title_ar} onChange={(v) => form.setData('title_ar', v)} dir="rtl" required error={form.errors.title_ar ?? null} />
                <TextField label={t('indicators.title_en')} value={form.data.title_en} onChange={(v) => form.setData('title_en', v)} dir="ltr" required error={form.errors.title_en ?? null} />
                <TextField label={t('indicators.value')} value={form.data.value} onChange={(v) => form.setData('value', v)} dir="ltr" inputMode="decimal" required error={form.errors.value ?? null} />
                <SelectField label={t('indicators.unit')} value={form.data.unit} onChange={(v) => form.setData('unit', v)} options={units} required error={form.errors.unit ?? null} />
                <TextField label={t('indicators.note_ar')} value={form.data.note_ar} onChange={(v) => form.setData('note_ar', v)} dir="rtl" error={form.errors.note_ar ?? null} />
                <TextField label={t('indicators.note_en')} value={form.data.note_en} onChange={(v) => form.setData('note_en', v)} dir="ltr" error={form.errors.note_en ?? null} />
                <TextField label={t('indicators.as_of')} type="date" dir="ltr" value={form.data.as_of} onChange={(v) => form.setData('as_of', v)} required hint={t('indicators.as_of_hint')} error={form.errors.as_of ?? null} />
                <TextField label={t('indicators.sort')} value={form.data.sort} onChange={(v) => form.setData('sort', v)} dir="ltr" inputMode="numeric" error={form.errors.sort ?? null} />
            </div>
            <TextareaField label={t('indicators.source')} value={form.data.source} onChange={(v) => form.setData('source', v)} rows={3} required hint={t('indicators.source_hint')} error={form.errors.source ?? null} />
            <SwitchField label={t('indicators.publish')} checked={form.data.is_published} onChange={(v) => form.setData('is_published', v)} hint={t('indicators.publish_hint')} />
            <FormActions processing={form.processing} dirty={form.isDirty} onCancel={onDone} />
        </form>
    );
}
