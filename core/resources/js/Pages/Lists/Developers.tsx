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

interface DeveloperRow {
    id: number;
    name_ar: string;
    name_en: string;
    slug: string;
    is_active: boolean;
    compounds: number;
    units: number;
}

/** Developers (PLAN 3.2; part 7.6; G16): both roles see them, an admin adds and edits. */
export default function DevelopersIndex({ developers, canEdit }: { developers: DeveloperRow[]; canEdit: boolean }) {
    const t = useT();
    const locale = useLocale();
    const [editing, setEditing] = useState<DeveloperRow | 'new' | null>(null);

    return (
        <DashboardLayout
            title={t('lists.developers')}
            crumbs={[{ label: t('lists.developers') }]}
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
                <p>{t('lists.developers_intro')}</p>
                {!canEdit ? <p className="mt-1 text-muted-foreground">{t('lists.admin_only')}</p> : null}
            </Alert>
            <Card className="p-0">
                <ul className="divide-y">
                    {developers.map((d) => (
                        <li key={d.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                            <span className="space-y-0.5">
                                <span className="flex flex-wrap items-center gap-2 font-medium">
                                    {locale === 'en' ? d.name_en : d.name_ar}
                                    {!d.is_active ? <Badge variant="warning">{t('lists.inactive')}</Badge> : null}
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    {t('lists.compounds_count', { count: d.compounds })} · {t('lists.units_count', { count: d.units })}
                                </span>
                            </span>
                            {canEdit ? (
                                <Button type="button" size="sm" variant="ghost" className="gap-1" onClick={() => setEditing(d)}>
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
                    <DialogContent title={editing === 'new' ? t('lists.add') : t('lists.edit')} className="sm:max-w-lg">
                        <DeveloperForm developer={editing === 'new' ? null : editing} onDone={() => setEditing(null)} />
                    </DialogContent>
                ) : null}
            </Dialog>
        </DashboardLayout>
    );
}

function DeveloperForm({ developer, onDone }: { developer: DeveloperRow | null; onDone: () => void }) {
    const t = useT();
    const form = useForm({ name_ar: developer?.name_ar ?? '', name_en: developer?.name_en ?? '', is_active: developer?.is_active ?? true });

    return (
        <form
            className="space-y-4"
            noValidate
            onSubmit={(event) => {
                event.preventDefault();
                const opts = { preserveScroll: true, onSuccess: onDone };
                if (developer) {
                    form.put(`/dashboard/lists/developers/${developer.id}`, opts);
                } else {
                    form.post('/dashboard/lists/developers', opts);
                }
            }}
        >
            <TextField label={t('lists.name_ar')} value={form.data.name_ar} onChange={(v) => form.setData('name_ar', v)} required error={form.errors.name_ar ?? null} />
            <TextField label={t('lists.name_en')} dir="ltr" value={form.data.name_en} onChange={(v) => form.setData('name_en', v)} required error={form.errors.name_en ?? null} />
            <SwitchField label={t('lists.active')} checked={form.data.is_active} onChange={(v) => form.setData('is_active', v)} error={form.errors.is_active ?? null} />
            <FormActions processing={form.processing} dirty={form.isDirty} onCancel={onDone} />
        </form>
    );
}
