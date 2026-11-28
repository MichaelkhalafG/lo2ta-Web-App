import { useForm } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useMemo, useState } from 'react';

import { FormActions } from '@/Components/form/FormActions';
import { SelectField, TextareaField } from '@/Components/form/TextField';
import { StateBadge, type StateTone } from '@/Components/review/StateBadge';
import { Alert } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Dialog, DialogContent } from '@/Components/ui/dialog';
import { Input, Select } from '@/Components/ui/input';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useLocale, useT } from '@/lib/i18n';

interface TextRow {
    id: number;
    key: string;
    group: string;
    ar: string | null;
    en: string | null;
    source: string;
    source_label: string;
    note: string | null;
    updated_at: string | null;
}

const TONE: Record<string, StateTone> = { placeholder: 'waiting', ours: 'progress', legal_review: 'stopped', client: 'done' };

/** Site texts (PLAN 6; K7, D68, D70; part 7.6; admin only). */
export default function SiteTextsIndex({ texts, sources }: { texts: TextRow[]; sources: { value: string; label: string }[] }) {
    const t = useT();
    const locale = useLocale();
    const [term, setTerm] = useState('');
    const [group, setGroup] = useState('');
    const [state, setState] = useState('');
    const [editing, setEditing] = useState<TextRow | null>(null);
    const groups = useMemo(() => [...new Set(texts.map((x) => x.group))], [texts]);
    const shown = texts.filter(
        (x) =>
            (group === '' || x.group === group) &&
            (state === '' || x.source === state) &&
            (term === '' || [x.key, x.ar, x.en].some((v) => (v ?? '').toLowerCase().includes(term.toLowerCase()))),
    );

    return (
        <DashboardLayout title={t('site_texts.title')} crumbs={[{ label: t('site_texts.title') }]}>
            <Alert tone="info">{t('site_texts.intro')}</Alert>
            <Card className="flex flex-wrap items-center gap-3 p-4">
                <Input type="search" value={term} onChange={(e) => setTerm(e.target.value)} placeholder={t('site_texts.search')} aria-label={t('site_texts.search')} className="min-w-[14rem] flex-1" />
                <Select aria-label={t('site_texts.group')} value={group} onChange={(e) => setGroup(e.target.value)} className="w-auto min-w-[10rem]">
                    <option value="">{t('site_texts.all_groups')}</option>
                    {groups.map((g) => (
                        <option key={g} value={g}>
                            {g}
                        </option>
                    ))}
                </Select>
                <Select aria-label={t('site_texts.state')} value={state} onChange={(e) => setState(e.target.value)} className="w-auto min-w-[10rem]">
                    <option value="">{t('inbox.all')}</option>
                    {sources.map((s) => (
                        <option key={s.value} value={s.value}>
                            {s.label}
                        </option>
                    ))}
                </Select>
            </Card>
            <Card className="p-0">
                <ul className="divide-y">
                    {shown.map((x) => (
                        <li key={x.id} className="flex flex-wrap items-start justify-between gap-3 px-4 py-3">
                            <div className="min-w-0 flex-1 space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-mono text-xs" dir="ltr">
                                        {x.key}
                                    </span>
                                    <StateBadge tone={TONE[x.source] ?? 'info'}>{x.source_label}</StateBadge>
                                </div>
                                <p className="line-clamp-2 text-sm text-muted-foreground" dir={locale === 'en' ? 'ltr' : 'rtl'}>
                                    {(locale === 'en' ? x.en : x.ar) ?? '—'}
                                </p>
                                {x.note ? <p className="text-xs text-muted-foreground">{t('site_texts.used', { note: x.note })}</p> : null}
                            </div>
                            <Button type="button" size="sm" variant="ghost" className="gap-1" onClick={() => setEditing(x)}>
                                <Pencil className="h-3.5 w-3.5" aria-hidden="true" />
                                {t('site_texts.edit')}
                            </Button>
                        </li>
                    ))}
                </ul>
            </Card>
            <Dialog open={editing !== null} onOpenChange={(next) => (next ? null : setEditing(null))}>
                {editing ? (
                    <DialogContent title={editing.key} className="max-h-[90dvh] overflow-y-auto sm:max-w-3xl">
                        <TextForm text={editing} sources={sources} onDone={() => setEditing(null)} />
                    </DialogContent>
                ) : null}
            </Dialog>
        </DashboardLayout>
    );
}

function TextForm({ text, sources, onDone }: { text: TextRow; sources: { value: string; label: string }[]; onDone: () => void }) {
    const t = useT();
    const form = useForm({ ar: text.ar ?? '', en: text.en ?? '', source: text.source });

    return (
        <form
            className="space-y-4"
            noValidate
            onSubmit={(event) => {
                event.preventDefault();
                form.put(`/dashboard/site-texts/${text.id}`, { preserveScroll: true, onSuccess: onDone });
            }}
        >
            {text.note ? <p className="text-sm text-muted-foreground">{t('site_texts.used', { note: text.note })}</p> : null}
            <div dir="rtl">
                <TextareaField label={t('site_texts.ar')} rows={8} value={form.data.ar} onChange={(v) => form.setData('ar', v)} required error={form.errors.ar ?? null} />
            </div>
            <div dir="ltr">
                <TextareaField label={t('site_texts.en')} rows={8} value={form.data.en} onChange={(v) => form.setData('en', v)} required error={form.errors.en ?? null} />
            </div>
            <SelectField label={t('site_texts.state')} value={form.data.source} options={sources} onChange={(v) => form.setData('source', v)} required error={form.errors.source ?? null} />
            {text.updated_at ? <p className="text-xs text-muted-foreground">{t('site_texts.updated', { at: `⁦${text.updated_at}⁩` })}</p> : null}
            <FormActions processing={form.processing} dirty={form.isDirty} onCancel={onDone} />
        </form>
    );
}
