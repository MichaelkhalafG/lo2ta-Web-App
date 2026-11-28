import { useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';

import { FormActions } from '@/Components/form/FormActions';
import { SwitchField } from '@/Components/form/SwitchField';
import { SelectField, TextareaField, TextField } from '@/Components/form/TextField';
import { Alert } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Dialog, DialogContent } from '@/Components/ui/dialog';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useLocale, useT } from '@/lib/i18n';

interface CompoundRow {
    id: number;
    developer_id: number;
    area_id: number;
    name_ar: string;
    name_en: string;
    slug: string;
    is_active: boolean;
    intro_ar: string | null;
    intro_en: string | null;
    intro_is_placeholder: boolean;
    developer: string | null;
    area: string | null;
    units: number;
}

type Option = { value: string; label: string };

/** Compounds (PLAN 3.2; part 7.6; G16): both roles see them, an admin adds and edits. */
export default function CompoundsIndex({ compounds, developers, areas, canEdit }: { compounds: CompoundRow[]; developers: Option[]; areas: Option[]; canEdit: boolean }) {
    const t = useT();
    const locale = useLocale();
    const [editing, setEditing] = useState<CompoundRow | 'new' | null>(null);

    return (
        <DashboardLayout
            title={t('lists.compounds')}
            crumbs={[{ label: t('lists.compounds') }]}
            actions={
                canEdit ? (
                    <Button type="button" className="gap-1.5" onClick={() => setEditing('new')}>
                        <Plus className="h-4 w-4" aria-hidden="true" />
                        {t('lists.add')}
                    </Button>
                ) : null
            }
        >
            <Alert tone="info">
                <p>{t('lists.compounds_intro')}</p>
                {!canEdit ? <p className="mt-1 text-muted-foreground">{t('lists.admin_only')}</p> : null}
            </Alert>
            <Card className="p-0">
                <ul className="divide-y">
                    {compounds.map((c) => (
                        <li key={c.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                            <span className="min-w-0 space-y-0.5">
                                <span className="flex flex-wrap items-center gap-2 font-medium">
                                    {locale === 'en' ? c.name_en : c.name_ar}
                                    {!c.is_active ? <Badge variant="warning">{t('lists.inactive')}</Badge> : null}
                                    <Badge variant={c.intro_is_placeholder ? 'neutral' : 'success'}>{c.intro_is_placeholder ? t('lists.intro_hidden') : t('lists.intro_shown')}</Badge>
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    {[c.developer, c.area, c.units > 0 ? t('lists.units_count', { count: c.units }) : null].filter(Boolean).join(' · ')}
                                </span>
                            </span>
                            {canEdit ? (
                                <Button type="button" size="sm" variant="ghost" className="gap-1" onClick={() => setEditing(c)}>
                                    <Pencil className="h-3.5 w-3.5" aria-hidden="true" />
                                    {t('lists.edit')}
                                </Button>
                            ) : null}
                        </li>
                    ))}
                </ul>
            </Card>
            <Dialog open={editing !== null} onOpenChange={(next) => (next ? null : setEditing(null))}>
                {editing ? (
                    <DialogContent title={editing === 'new' ? t('lists.add') : t('lists.edit')} className="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
                        <CompoundForm compound={editing === 'new' ? null : editing} developers={developers} areas={areas} onDone={() => setEditing(null)} />
                    </DialogContent>
                ) : null}
            </Dialog>
        </DashboardLayout>
    );
}

function CompoundForm({ compound, developers, areas, onDone }: { compound: CompoundRow | null; developers: Option[]; areas: Option[]; onDone: () => void }) {
    const t = useT();
    const form = useForm({
        developer_id: compound ? String(compound.developer_id) : '',
        area_id: compound ? String(compound.area_id) : '',
        name_ar: compound?.name_ar ?? '',
        name_en: compound?.name_en ?? '',
        intro_ar: compound?.intro_ar ?? '',
        intro_en: compound?.intro_en ?? '',
        is_active: compound?.is_active ?? true,
    });

    return (
        <form
            className="space-y-4"
            noValidate
            onSubmit={(event) => {
                event.preventDefault();
                const opts = { preserveScroll: true, onSuccess: onDone };
                if (compound) {
                    form.put(`/dashboard/lists/compounds/${compound.id}`, opts);
                } else {
                    form.post('/dashboard/lists/compounds', opts);
                }
            }}
        >
            <TextField label={t('lists.name_ar')} value={form.data.name_ar} onChange={(v) => form.setData('name_ar', v)} required error={form.errors.name_ar ?? null} />
            <TextField label={t('lists.name_en')} dir="ltr" value={form.data.name_en} onChange={(v) => form.setData('name_en', v)} required error={form.errors.name_en ?? null} />
            <SelectField label={t('lists.developer')} value={form.data.developer_id} placeholder={t('form.choose')} options={developers} onChange={(v) => form.setData('developer_id', v)} required error={form.errors.developer_id ?? null} />
            <SelectField label={t('lists.area')} value={form.data.area_id} placeholder={t('form.choose')} options={areas} onChange={(v) => form.setData('area_id', v)} required error={form.errors.area_id ?? null} />
            <TextareaField label={t('lists.intro_ar')} hint={t('lists.intro_hint')} rows={4} value={form.data.intro_ar} onChange={(v) => form.setData('intro_ar', v.slice(0, 4000))} error={form.errors.intro_ar ?? null} />
            <TextareaField label={t('lists.intro_en')} rows={4} value={form.data.intro_en} onChange={(v) => form.setData('intro_en', v.slice(0, 4000))} error={form.errors.intro_en ?? null} />
            <SwitchField label={t('lists.active')} checked={form.data.is_active} onChange={(v) => form.setData('is_active', v)} error={form.errors.is_active ?? null} />
            <FormActions processing={form.processing} dirty={form.isDirty} onCancel={onDone} />
        </form>
    );
}
