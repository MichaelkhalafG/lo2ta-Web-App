import { useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';

import { FormActions } from '@/Components/form/FormActions';
import { SwitchField } from '@/Components/form/SwitchField';
import { TextField } from '@/Components/form/TextField';
import { Alert } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Dialog, DialogContent } from '@/Components/ui/dialog';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

interface AreaRow {
    id: number;
    parent_id: number | null;
    depth: number;
    code: string | null;
    name_ar: string;
    name_en: string;
    slug: string;
    is_active: boolean;
    units: number;
    code_locked: boolean;
}

type Editing = { area: AreaRow | null; parent: AreaRow | null };

/** Areas in three levels (PLAN 3.3; part 7.6; G16; K1: governorates one under the other). */
export default function AreasIndex({ areas, canEdit }: { areas: AreaRow[]; canEdit: boolean }) {
    const t = useT();
    const locale = useLocale();
    const [editing, setEditing] = useState<Editing | null>(null);
    const name = (a: AreaRow) => (locale === 'en' ? a.name_en : a.name_ar);

    return (
        <DashboardLayout
            title={t('lists.areas')}
            crumbs={[{ label: t('lists.areas') }]}
            actions={
                canEdit ? (
                    <Button type="button" className="gap-1.5" onClick={() => setEditing({ area: null, parent: null })}>
                        <Plus className="h-4 w-4" aria-hidden="true" />
                        {t('lists.add_governorate')}
                    </Button>
                ) : null
            }
        >
            <Alert tone="info">
                <p>{t('lists.areas_intro')}</p>
                {!canEdit ? <p className="mt-1 text-muted-foreground">{t('lists.admin_only')}</p> : null}
            </Alert>
            <Card className="p-0">
                <ul className="divide-y">
                    {areas.map((a) => (
                        <li key={a.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5" style={{ paddingInlineStart: `${1 + a.depth * 1.5}rem` }}>
                            <span className={cn('flex flex-wrap items-center gap-2', a.depth === 0 && 'font-semibold')}>
                                {name(a)}
                                {a.code ? (
                                    <Badge variant={a.code_locked ? 'neutral' : 'outline'} className="font-mono" dir="ltr">
                                        {a.code}
                                    </Badge>
                                ) : null}
                                {!a.is_active ? <Badge variant="warning">{t('lists.inactive')}</Badge> : null}
                                {a.units > 0 ? <span className="text-xs text-muted-foreground">{t('lists.units_count', { count: a.units })}</span> : null}
                            </span>
                            {canEdit ? (
                                <span className="flex gap-1">
                                    {a.depth < 2 ? (
                                        <Button type="button" size="sm" variant="ghost" className="gap-1" onClick={() => setEditing({ area: null, parent: a })}>
                                            <Plus className="h-3.5 w-3.5" aria-hidden="true" />
                                            {t('lists.add')}
                                        </Button>
                                    ) : null}
                                    <Button type="button" size="sm" variant="ghost" className="gap-1" onClick={() => setEditing({ area: a, parent: null })}>
                                        <Pencil className="h-3.5 w-3.5" aria-hidden="true" />
                                        {t('lists.edit')}
                                    </Button>
                                </span>
                            ) : null}
                        </li>
                    ))}
                </ul>
            </Card>
            <Dialog open={editing !== null} onOpenChange={(next) => (next ? null : setEditing(null))}>
                {editing ? (
                    <DialogContent title={editing.area ? t('lists.edit') : editing.parent ? t('lists.add_area', { parent: name(editing.parent) }) : t('lists.add_governorate')} className="sm:max-w-lg">
                        <AreaForm editing={editing} onDone={() => setEditing(null)} />
                    </DialogContent>
                ) : null}
            </Dialog>
        </DashboardLayout>
    );
}

function AreaForm({ editing, onDone }: { editing: Editing; onDone: () => void }) {
    const t = useT();
    const a = editing.area;
    const form = useForm({
        parent_id: a ? a.parent_id : (editing.parent?.id ?? null),
        name_ar: a?.name_ar ?? '',
        name_en: a?.name_en ?? '',
        code: a?.code ?? '',
        is_active: a?.is_active ?? true,
    });
    const showCode = (a ? a.depth : (editing.parent?.depth ?? -1) + 1) === 1;

    return (
        <form
            className="space-y-4"
            noValidate
            onSubmit={(event) => {
                event.preventDefault();
                const opts = { preserveScroll: true, onSuccess: onDone };
                if (a) {
                    form.put(`/dashboard/lists/areas/${a.id}`, opts);
                } else {
                    form.post('/dashboard/lists/areas', opts);
                }
            }}
        >
            <TextField label={t('lists.name_ar')} value={form.data.name_ar} onChange={(v) => form.setData('name_ar', v)} required error={form.errors.name_ar ?? null} />
            <TextField label={t('lists.name_en')} dir="ltr" value={form.data.name_en} onChange={(v) => form.setData('name_en', v)} required error={form.errors.name_en ?? null} />
            {showCode ? (
                <TextField
                    label={t('lists.code')}
                    hint={a?.code_locked ? t('lists.code_locked') : t('lists.code_hint')}
                    dir="ltr"
                    value={form.data.code}
                    disabled={a?.code_locked === true}
                    onChange={(v) => form.setData('code', v.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 4))}
                    error={form.errors.code ?? null}
                />
            ) : null}
            <SwitchField label={t('lists.active')} checked={form.data.is_active} onChange={(v) => form.setData('is_active', v)} error={form.errors.is_active ?? null} />
            {form.errors.parent_id ? <Alert tone="error">{form.errors.parent_id}</Alert> : null}
            <FormActions processing={form.processing} dirty={form.isDirty} onCancel={onDone} />
        </form>
    );
}
