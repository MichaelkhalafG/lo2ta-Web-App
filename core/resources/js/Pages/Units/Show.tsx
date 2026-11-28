import { Link, router, useForm, usePage } from '@inertiajs/react';
import { ExternalLink, EyeOff, FileText, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

import { FormActions } from '@/Components/form/FormActions';
import { SelectField, TextareaField, TextField } from '@/Components/form/TextField';
import { ReasonDialog } from '@/Components/review/ReasonDialog';
import { RevisionBlock, type Revision } from '@/Components/review/RevisionBlock';
import { StateBadge } from '@/Components/review/StateBadge';
import { ConfirmAction } from '@/Components/shell/ConfirmAction';
import { Alert } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Num } from '@/Components/ui/bidi';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Dialog, DialogContent } from '@/Components/ui/dialog';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useT } from '@/lib/i18n';
import { unitTone } from '@/Pages/Units/Index';

interface FieldRow {
    key: string;
    label: string;
    type: 'money' | 'int' | 'decimal' | 'date' | 'select' | 'bool' | 'text' | 'code';
    raw: string | number | boolean | null;
    display: string | null;
    options: { value: string; label: string }[];
    editable: boolean;
    group: 'unit' | 'money' | 'description';
}

interface DocumentRow {
    id: number;
    type: string;
    type_label: string;
    is_national_id: boolean;
    status: 'uploaded' | 'approved' | 'needs_replacement';
    status_label: string;
    note: string | null;
    mime: string;
    bytes: number;
    flags: string[];
    uploaded_at: string | null;
    reviewed_at: string | null;
    purged: boolean;
    url: string | null;
}

interface PhotoRow {
    id: number;
    kind: 'photo' | 'floor_plan';
    url: string;
    is_hidden: boolean;
    hidden_reason: string | null;
    is_project_image: boolean;
    is_public: boolean;
}

interface Place {
    id: number | null;
    name: string | null;
    other: string | null;
}

interface Option {
    value: string;
    label: string;
}

interface Props {
    unit: {
        id: number;
        reference: string | null;
        code: string | null;
        state: string;
        state_label: string;
        main_type: string | null;
        sub_type: string | null;
        contract_number: string | null;
        unit_number: string | null;
        contract_date: string | null;
        facade: string[];
        features: string[];
        submitted_at: string | null;
        published_at: string | null;
        verified: boolean;
        deal_tag: boolean;
        withdraw_requested: boolean;
        withdraw_reason: string | null;
        pending_change: boolean;
        recheck: boolean;
        public_url: string | null;
        warnings: string[];
    };
    seller: { name: string; email: string; phone: string; email_verified: boolean; units: number } | null;
    places: { developer: Place; compound: Place; area: Place; mappable: boolean };
    choices: { developers: Option[]; compounds: (Option & { developer: number })[]; areas: Option[] } | null;
    fields: FieldRow[];
    documents: DocumentRow[];
    photos: PhotoRow[];
    duplicates: { at: string; kind: string; stage: string; blocked: boolean; other: string | null; other_id: number | null }[];
    lastReview: { outcome: string; reason: string | null; fields: { label: string; note: string }[]; at: string } | null;
    blockers: string[];
    noteFields: Option[];
    history: { at: string; event: string; by: string; note: string | null }[];
    can: { decide: boolean; reject: boolean; verify: boolean; flags: boolean; withdrawal: boolean; documents: boolean };
}

const base = (id: number) => `/dashboard/units/${id}`;
const opts = { preserveScroll: true } as const;

/**
 * One unit under review (PLAN 2.2.6, 2.4, 2.7; part 7.2). The decision panel comes first on a phone and sits beside
 * the record on a wide screen; the record reads top to bottom as the seller filled it in, then documents, photos,
 * duplicate checks and the history as a revision block.
 */
export default function UnitShow(props: Props) {
    const t = useT();
    const { errors } = usePage().props as { errors?: Record<string, string> };
    const { unit } = props;
    const title = [unit.sub_type, props.places.compound.name ?? props.places.compound.other].filter(Boolean).join(' · ') || (unit.reference ?? '');
    const errorList = Object.values(errors ?? {});

    return (
        <DashboardLayout title={title} crumbs={[{ label: t('units.title'), href: '/dashboard/units' }, { label: unit.code ?? unit.reference ?? `#${unit.id}` }]}>
            <div className="flex flex-wrap items-center gap-2">
                <StateBadge tone={unitTone(unit.state)}>{unit.state_label}</StateBadge>
                {unit.verified ? <Badge variant="verified">{t('units.badge_verified')}</Badge> : null}
                {unit.deal_tag ? <Badge variant="outline">{t('units.badge_deal')}</Badge> : null}
                {unit.pending_change ? (
                    <Link href="/dashboard/changes">
                        <Badge variant="warning">{t('changes.pending')}</Badge>
                    </Link>
                ) : null}
                {unit.recheck ? (
                    <Link href="/dashboard/changes">
                        <Badge variant="warning">{t('changes.recheck')}</Badge>
                    </Link>
                ) : null}
                <span className="font-mono text-sm text-muted-foreground" dir="ltr">
                    {[unit.code, unit.reference].filter(Boolean).join(' · ')}
                </span>
                {unit.public_url ? (
                    <a href={unit.public_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-sm underline underline-offset-4">
                        <ExternalLink className="h-3.5 w-3.5" aria-hidden="true" />
                        {t('units.open_public')}
                    </a>
                ) : null}
            </div>
            {errorList.length > 0 ? (
                <Alert tone="error">
                    {errorList.map((message) => (
                        <p key={message}>{message}</p>
                    ))}
                </Alert>
            ) : null}

            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div className="order-2 min-w-0 space-y-6 lg:order-1">
                    <SellerCard {...props} />
                    <ContractCard {...props} />
                    <FieldsCard {...props} />
                    <DocumentsCard {...props} />
                    <PhotosCard {...props} />
                    {props.duplicates.length > 0 ? <DuplicatesCard {...props} /> : null}
                    <Section title={t('units.sections.history')}>
                        <RevisionBlock rows={props.history.map((h): Revision => ({ at: h.at, event: h.event, by: h.by, note: h.note }))} />
                    </Section>
                </div>
                <div className="order-1 space-y-6 lg:sticky lg:top-20 lg:order-2">
                    <DecisionCard {...props} />
                </div>
            </div>
        </DashboardLayout>
    );
}

function Section({ title, actions, children }: { title: string; actions?: React.ReactNode; children: React.ReactNode }) {
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

function Facts({ rows }: { rows: { label: string; value: React.ReactNode }[] }) {
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

function DecisionCard({ unit, blockers, can, lastReview, noteFields }: Props) {
    const t = useT();
    const [changes, setChanges] = useState(false);

    return (
        <Card className="space-y-4 p-4 sm:p-5">
            <h2 className="border-b pb-3 text-base font-semibold">{t('units.sections.decision')}</h2>
            {can.decide ? (
                <>
                    {blockers.length > 0 ? (
                        <div className="space-y-1 text-sm">
                            <p className="font-medium">{t('units.actions.blockers')}</p>
                            <ul className="list-disc space-y-0.5 ps-5 text-muted-foreground">
                                {blockers.map((b) => (
                                    <li key={b}>{b}</li>
                                ))}
                            </ul>
                        </div>
                    ) : null}
                    <div className="flex flex-col gap-2">
                        <ConfirmAction
                            title={t('units.actions.publish')}
                            consequence={t('units.actions.publish_consequence')}
                            confirmLabel={t('units.actions.publish')}
                            tone="default"
                            disabled={blockers.length > 0}
                            trigger={
                                <Button type="button" className="w-full" disabled={blockers.length > 0}>
                                    {t('units.actions.publish')}
                                </Button>
                            }
                            onConfirm={() => router.post(`${base(unit.id)}/publish`, {}, opts)}
                        />
                        {can.verify ? (
                            <ConfirmAction
                                title={t('units.actions.publish_verified')}
                                consequence={t('units.actions.publish_verified_consequence')}
                                confirmLabel={t('units.actions.publish_verified')}
                                tone="default"
                                disabled={blockers.length > 0}
                                trigger={
                                    <Button type="button" variant="outline" className="w-full" disabled={blockers.length > 0}>
                                        {t('units.actions.publish_verified')}
                                    </Button>
                                }
                                onConfirm={() => router.post(`${base(unit.id)}/publish-verified`, {}, opts)}
                            />
                        ) : null}
                        <Button type="button" variant="outline" className="w-full" onClick={() => setChanges(true)}>
                            {t('units.actions.request_changes')}
                        </Button>
                    </div>
                </>
            ) : null}
            {can.reject ? (
                <ReasonDialog
                    title={t('units.actions.reject')}
                    consequence={t('units.actions.reject_consequence')}
                    confirmLabel={t('units.actions.reject')}
                    trigger={
                        <Button type="button" variant="ghost" className="w-full text-error">
                            {t('units.actions.reject')}
                        </Button>
                    }
                    onConfirm={(reason) => router.post(`${base(unit.id)}/reject`, { reason }, opts)}
                />
            ) : null}
            {can.flags ? <FlagsBlock unit={unit} canVerify={can.verify} /> : null}
            {can.withdrawal ? (
                <div className="space-y-2 border-t pt-4">
                    <p className="font-medium">{t('units.actions.withdrawal')}</p>
                    <p className="text-sm text-muted-foreground">{t('units.actions.withdrawal_hint')}</p>
                    {unit.withdraw_reason ? <p className="text-sm">“{unit.withdraw_reason}”</p> : null}
                    <div className="flex flex-col gap-2">
                        <ConfirmAction
                            title={t('units.actions.withdrawal_approve')}
                            consequence={t('units.actions.withdrawal_approve_consequence')}
                            confirmLabel={t('units.actions.withdrawal_approve')}
                            trigger={
                                <Button type="button" variant="outline" className="w-full">
                                    {t('units.actions.withdrawal_approve')}
                                </Button>
                            }
                            onConfirm={() => router.post(`${base(unit.id)}/withdrawal`, { approve: true }, opts)}
                        />
                        <ReasonDialog
                            title={t('units.actions.withdrawal_decline')}
                            consequence={t('units.actions.withdrawal_decline_consequence')}
                            confirmLabel={t('units.actions.withdrawal_decline')}
                            tone="default"
                            trigger={
                                <Button type="button" variant="ghost" className="w-full">
                                    {t('units.actions.withdrawal_decline')}
                                </Button>
                            }
                            onConfirm={(reason) => router.post(`${base(unit.id)}/withdrawal`, { approve: false, reason }, opts)}
                        />
                    </div>
                </div>
            ) : null}
            {!can.decide && !can.reject && !can.flags && !can.withdrawal ? <p className="text-sm text-muted-foreground">{unit.state_label}</p> : null}
            {lastReview ? (
                <div className="space-y-1 border-t pt-4 text-sm">
                    <p className="font-medium">
                        {t('units.sections.last_review')}: {lastReview.outcome}{' '}
                        <Num className="font-mono text-xs text-muted-foreground">{lastReview.at}</Num>
                    </p>
                    {lastReview.reason ? <p className="whitespace-pre-line text-muted-foreground">{lastReview.reason}</p> : null}
                    {lastReview.fields.length > 0 ? (
                        <ul className="list-disc ps-5 text-muted-foreground">
                            {lastReview.fields.map((f) => (
                                <li key={f.label}>
                                    {f.label}: {f.note}
                                </li>
                            ))}
                        </ul>
                    ) : null}
                </div>
            ) : null}
            <Dialog open={changes} onOpenChange={setChanges}>
                {changes ? (
                    <DialogContent title={t('units.actions.request_changes')} className="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
                        <RequestChangesForm unitId={unit.id} noteFields={noteFields} onDone={() => setChanges(false)} />
                    </DialogContent>
                ) : null}
            </Dialog>
        </Card>
    );
}

function FlagsBlock({ unit, canVerify }: { unit: Props['unit']; canVerify: boolean }) {
    const t = useT();

    return (
        <div className="space-y-2 border-t pt-4">
            <p className="font-medium">{t('units.sections.flags')}</p>
            <div className="flex flex-col gap-2">
                {canVerify ? (
                    <ReasonDialog
                        title={unit.verified ? t('units.actions.verified_off') : t('units.actions.verified_on')}
                        consequence={t('units.actions.verified_consequence')}
                        confirmLabel={unit.verified ? t('units.actions.verified_off') : t('units.actions.verified_on')}
                        tone={unit.verified ? 'destructive' : 'default'}
                        trigger={
                            <Button type="button" variant="outline" className="w-full">
                                {unit.verified ? t('units.actions.verified_off') : t('units.actions.verified_on')}
                            </Button>
                        }
                        onConfirm={(reason) => router.put(`${base(unit.id)}/verified`, { verified: !unit.verified, reason }, opts)}
                    />
                ) : null}
                <Button type="button" variant="outline" className="w-full" onClick={() => router.put(`${base(unit.id)}/deal-tag`, { deal_tag: !unit.deal_tag }, opts)}>
                    {unit.deal_tag ? t('units.actions.deal_off') : t('units.actions.deal_on')}
                </Button>
            </div>
        </div>
    );
}

function RequestChangesForm({ unitId, noteFields, onDone }: { unitId: number; noteFields: Option[]; onDone: () => void }) {
    const t = useT();
    const [notes, setNotes] = useState<{ field: string; note: string }[]>([]);
    const form = useForm<{ reason: string; fields: Record<string, string> }>({ reason: '', fields: {} });

    return (
        <form
            className="space-y-4"
            noValidate
            onSubmit={(event) => {
                event.preventDefault();
                const fields: Record<string, string> = {};
                for (const row of notes) {
                    if (row.field !== '' && row.note.trim() !== '') {
                        fields[row.field] = row.note.trim();
                    }
                }
                form.transform((data) => ({ ...data, fields }));
                form.post(`${base(unitId)}/request-changes`, { ...opts, onSuccess: onDone });
            }}
        >
            <p className="text-sm text-muted-foreground">{t('units.actions.request_changes_hint')}</p>
            <TextareaField label={t('reason.label')} required rows={3} value={form.data.reason} onChange={(v) => form.setData('reason', v.slice(0, 1000))} error={form.errors.reason ?? null} />
            <div className="space-y-3">
                <p className="text-sm font-medium">{t('units.actions.field_notes')}</p>
                {notes.map((row, i) => (
                    <div key={i} className="grid gap-2 rounded-sm border p-3 sm:grid-cols-[12rem_1fr_auto] sm:items-end">
                        <SelectField
                            label={t('units.actions.note_field')}
                            value={row.field}
                            placeholder={t('form.choose')}
                            options={noteFields}
                            onChange={(v) => setNotes(notes.map((n, j) => (j === i ? { ...n, field: v } : n)))}
                        />
                        <TextField label={t('units.actions.note_text')} value={row.note} onChange={(v) => setNotes(notes.map((n, j) => (j === i ? { ...n, note: v.slice(0, 300) } : n)))} />
                        <Button type="button" variant="ghost" size="sm" className="gap-1" onClick={() => setNotes(notes.filter((_, j) => j !== i))}>
                            <Trash2 className="h-4 w-4" aria-hidden="true" />
                            {t('units.actions.remove_note')}
                        </Button>
                    </div>
                ))}
                {notes.length < 20 ? (
                    <Button type="button" variant="outline" size="sm" className="gap-1.5" onClick={() => setNotes([...notes, { field: '', note: '' }])}>
                        <Plus className="h-4 w-4" aria-hidden="true" />
                        {t('units.actions.add_note')}
                    </Button>
                ) : null}
            </div>
            <FormActions processing={form.processing} dirty={form.data.reason.trim().length >= 3} onCancel={onDone} submitLabel={t('units.actions.send')} />
        </form>
    );
}

function SellerCard({ seller }: Props) {
    const t = useT();
    if (seller === null) {
        return null;
    }

    return (
        <Section title={t('units.sections.seller')}>
            <Facts
                rows={[
                    { label: t('staff.name'), value: seller.name },
                    {
                        label: t('units.email'),
                        value: (
                            <span className="flex flex-wrap items-center gap-2">
                                <span dir="ltr" className="break-all">
                                    {seller.email}
                                </span>
                                <Badge variant={seller.email_verified ? 'success' : 'warning'}>{seller.email_verified ? t('units.seller_email_verified') : t('units.seller_email_unverified')}</Badge>
                            </span>
                        ),
                    },
                    { label: t('units.phone'), value: <span dir="ltr">{seller.phone}</span> },
                    { label: t('units.sections.seller'), value: t('units.seller_units', { count: seller.units }) },
                ]}
            />
        </Section>
    );
}

function ContractCard({ unit, places, choices }: Props) {
    const t = useT();
    const [mapping, setMapping] = useState(false);
    const place = (p: Place) =>
        p.name ?? (p.other ? <span className="text-warning">{t('units.places.typed', { text: p.other })}</span> : '—');

    return (
        <Section
            title={t('units.sections.contract')}
            actions={
                places.mappable && choices ? (
                    <Button type="button" variant="outline" size="sm" onClick={() => setMapping(true)}>
                        {t('units.places.map')}
                    </Button>
                ) : null
            }
        >
            <Facts
                rows={[
                    { label: t('units.type'), value: [unit.main_type, unit.sub_type].filter(Boolean).join(' · ') },
                    { label: t('units.contract_date'), value: unit.contract_date ? <Num>{unit.contract_date}</Num> : null },
                    { label: t('units.contract_number'), value: <span dir="ltr">{unit.contract_number}</span> },
                    { label: t('units.unit_number'), value: <span dir="ltr">{unit.unit_number}</span> },
                    { label: t('units.places.developer'), value: place(places.developer) },
                    { label: t('units.places.compound'), value: place(places.compound) },
                    { label: t('units.places.area'), value: place(places.area) },
                ]}
            />
            <p className="text-xs text-muted-foreground">{t('units.private_hint')}</p>
            {choices ? (
                <Dialog open={mapping} onOpenChange={setMapping}>
                    {mapping ? (
                        <DialogContent title={t('units.places.map')} className="max-h-[90dvh] overflow-y-auto sm:max-w-lg">
                            <MapForm unitId={unit.id} places={places} choices={choices} onDone={() => setMapping(false)} />
                        </DialogContent>
                    ) : null}
                </Dialog>
            ) : null}
        </Section>
    );
}

function MapForm({ unitId, places, choices, onDone }: { unitId: number; places: Props['places']; choices: NonNullable<Props['choices']>; onDone: () => void }) {
    const t = useT();
    const form = useForm({
        developer_id: places.developer.id !== null ? String(places.developer.id) : '',
        compound_id: places.compound.id !== null ? String(places.compound.id) : '',
        area_id: places.area.id !== null ? String(places.area.id) : '',
    });
    const compounds = choices.compounds.filter((c) => form.data.developer_id === '' || String(c.developer) === form.data.developer_id);
    const typed = (p: Place) => (p.other ? { hint: t('units.places.typed', { text: p.other }) } : {});
    const errors = form.errors as Record<string, string | undefined>;

    return (
        <form
            className="space-y-4"
            noValidate
            onSubmit={(event) => {
                event.preventDefault();
                form.put(`${base(unitId)}/places`, { ...opts, onSuccess: onDone });
            }}
        >
            <p className="text-sm text-muted-foreground">{t('units.places.map_hint')}</p>
            <SelectField label={t('units.places.developer')} {...typed(places.developer)} value={form.data.developer_id} placeholder={t('units.places.keep')} options={choices.developers} onChange={(v) => form.setData({ ...form.data, developer_id: v, compound_id: '' })} error={form.errors.developer_id ?? null} />
            <SelectField label={t('units.places.compound')} {...typed(places.compound)} value={form.data.compound_id} placeholder={t('units.places.keep')} options={compounds} onChange={(v) => form.setData('compound_id', v)} error={errors.compound ?? errors.compound_id ?? null} />
            <SelectField label={t('units.places.area')} {...typed(places.area)} value={form.data.area_id} placeholder={t('units.places.keep')} options={choices.areas} onChange={(v) => form.setData('area_id', v)} error={form.errors.area_id ?? null} />
            {errors.places ? <Alert tone="error">{errors.places}</Alert> : null}
            <FormActions processing={form.processing} dirty={form.isDirty} onCancel={onDone} />
        </form>
    );
}

function FieldsCard({ unit, fields }: Props) {
    const t = useT();
    const [editing, setEditing] = useState(false);
    const editable = fields.filter((f) => f.editable);
    const value = (f: FieldRow) => (f.display === null ? '—' : f.type === 'money' || f.type === 'decimal' || f.type === 'int' || f.type === 'date' ? <Num>{f.display}</Num> : f.display);
    const group = (g: FieldRow['group']) => fields.filter((f) => f.group === g && (f.display !== null || f.editable));
    const description = fields.find((f) => f.key === 'description');

    return (
        <>
            <Section
                title={t('units.sections.unit')}
                actions={
                    editable.length > 0 ? (
                        <Button type="button" variant="outline" size="sm" className="gap-1.5" onClick={() => setEditing(true)}>
                            <Pencil className="h-4 w-4" aria-hidden="true" />
                            {t('units.edit')}
                        </Button>
                    ) : null
                }
            >
                <Facts
                    rows={[
                        ...group('unit').map((f) => ({ label: f.label, value: value(f) })),
                        { label: t('units.facade'), value: unit.facade.length > 0 ? unit.facade.join(' · ') : null },
                        { label: t('units.features'), value: unit.features.length > 0 ? unit.features.join(' · ') : t('units.none') },
                    ]}
                />
            </Section>
            <Section title={t('units.sections.money')}>
                <Facts rows={group('money').map((f) => ({ label: f.label, value: value(f) }))} />
                {unit.warnings.length > 0 ? (
                    <Alert tone="warning">
                        <p className="font-medium">{t('units.sections.warnings')}</p>
                        {unit.warnings.map((w) => (
                            <p key={w}>{w}</p>
                        ))}
                    </Alert>
                ) : null}
            </Section>
            <Section title={t('units.sections.description')}>
                <p className="whitespace-pre-line break-words text-sm leading-7">{description?.display ?? '—'}</p>
            </Section>
            <Dialog open={editing} onOpenChange={setEditing}>
                {editing ? (
                    <DialogContent title={t('units.edit')} className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                        <EditFieldsForm unit={unit} fields={editable} onDone={() => setEditing(false)} />
                    </DialogContent>
                ) : null}
            </Dialog>
        </>
    );
}

function EditFieldsForm({ unit, fields, onDone }: { unit: Props['unit']; fields: FieldRow[]; onDone: () => void }) {
    const t = useT();
    const initial: Record<string, string> = {};
    for (const f of fields) {
        initial[f.key] = f.raw === null ? '' : f.type === 'bool' ? (f.raw ? '1' : '0') : String(f.raw);
    }
    const [values, setValues] = useState(initial);
    const form = useForm<{ reason: string; values: Record<string, string | number | boolean | null> }>({ reason: '', values: {} });
    const changed = fields.filter((f) => values[f.key] !== initial[f.key]);

    return (
        <form
            className="space-y-4"
            noValidate
            onSubmit={(event) => {
                event.preventDefault();
                const out: Record<string, string | number | boolean | null> = {};
                for (const f of changed) {
                    const v = values[f.key] ?? '';
                    out[f.key] = v === '' ? null : f.type === 'bool' ? v === '1' : f.type === 'money' || f.type === 'int' ? Number(v) : v;
                }
                form.transform((data) => ({ ...data, values: out }));
                form.put(`${base(unit.id)}/fields`, { ...opts, onSuccess: onDone });
            }}
        >
            <p className="text-sm text-muted-foreground">{unit.state === 'submitted' ? t('units.edit_hint') : t('units.edit_published_hint')}</p>
            <div className="grid gap-4 sm:grid-cols-2">
                {fields.map((f) => {
                    const error = form.errors[`values.${f.key}` as keyof typeof form.errors] ?? (form.errors as Record<string, string>)[f.key] ?? null;
                    const set = (v: string) => setValues({ ...values, [f.key]: v });
                    if (f.type === 'select' || f.type === 'bool') {
                        const options = f.type === 'bool' ? [{ value: '1', label: t('common.yes') }, { value: '0', label: t('common.no') }] : f.options;
                        return <SelectField key={f.key} label={f.label} value={values[f.key] ?? ''} placeholder="—" options={options} onChange={set} error={error} />;
                    }
                    if (f.type === 'text') {
                        return (
                            <div key={f.key} className="sm:col-span-2">
                                <TextareaField label={f.label} rows={5} value={values[f.key] ?? ''} onChange={set} error={error} />
                            </div>
                        );
                    }
                    return (
                        <TextField
                            key={f.key}
                            label={f.label}
                            type={f.type === 'date' ? 'date' : 'text'}
                            inputMode={f.type === 'money' || f.type === 'int' ? 'numeric' : f.type === 'decimal' ? 'decimal' : 'text'}
                            dir="ltr"
                            value={values[f.key] ?? ''}
                            onChange={(v) => set(f.type === 'money' || f.type === 'int' ? v.replace(/[^0-9]/g, '') : v)}
                            error={error}
                        />
                    );
                })}
            </div>
            <TextareaField label={t('reason.label')} hint={t('reason.hint')} required rows={2} value={form.data.reason} onChange={(v) => form.setData('reason', v.slice(0, 500))} error={form.errors.reason ?? (form.errors as Record<string, string>).fields ?? null} />
            <FormActions processing={form.processing} dirty={changed.length > 0 && form.data.reason.trim().length >= 3} onCancel={onDone} />
        </form>
    );
}

function DocumentsCard({ unit, documents, can }: Props) {
    const t = useT();

    return (
        <Section title={t('units.sections.documents')}>
            <ul className="divide-y">
                {documents.map((d) => (
                    <li key={d.id} className="flex flex-wrap items-start justify-between gap-3 py-3">
                        <div className="min-w-0 space-y-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <FileText className="h-4 w-4 text-muted-foreground" aria-hidden="true" />
                                <span className="font-medium">{d.type_label}</span>
                                <StateBadge tone={d.status === 'approved' ? 'done' : d.status === 'needs_replacement' ? 'stopped' : 'waiting'}>{d.status_label}</StateBadge>
                                {d.flags.map((flag) => (
                                    <Badge key={flag} variant="warning">
                                        {t(`units.documents.flags.${flag}`, flag)}
                                    </Badge>
                                ))}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {d.uploaded_at ? t('units.documents.uploaded', { at: `⁦${d.uploaded_at}⁩` }) : null}
                                {d.reviewed_at ? <> · {t('units.documents.reviewed', { at: `⁦${d.reviewed_at}⁩` })}</> : null}
                            </p>
                            {d.note ? <p className="text-sm text-muted-foreground">“{d.note}”</p> : null}
                            {d.purged ? <p className="text-xs text-muted-foreground">{t('units.documents.purged')}</p> : null}
                            {!d.purged && d.url === null ? <p className="text-xs text-muted-foreground">{t('units.documents.id_locked')}</p> : null}
                        </div>
                        <div className="flex flex-wrap items-center gap-1">
                            {d.url ? (
                                <a href={d.url} target="_blank" rel="noreferrer" className="inline-flex h-8 items-center gap-1 px-2 text-sm underline underline-offset-4">
                                    {t('units.documents.open')}
                                </a>
                            ) : null}
                            {can.documents && !d.purged ? (
                                <>
                                    {d.status !== 'approved' ? (
                                        <Button type="button" variant="ghost" size="sm" onClick={() => router.put(`${base(unit.id)}/documents/${d.id}`, { status: 'approved' }, opts)}>
                                            {t('units.documents.approve')}
                                        </Button>
                                    ) : null}
                                    <ReasonDialog
                                        title={`${t('units.documents.replace')}: ${d.type_label}`}
                                        consequence={t('units.documents.replace_hint')}
                                        confirmLabel={t('units.documents.replace')}
                                        tone="default"
                                        label={t('units.documents.note')}
                                        hint={t('units.documents.replace_hint')}
                                        trigger={
                                            <Button type="button" variant="ghost" size="sm">
                                                {t('units.documents.replace')}
                                            </Button>
                                        }
                                        onConfirm={(note) => router.put(`${base(unit.id)}/documents/${d.id}`, { status: 'needs_replacement', note }, opts)}
                                    />
                                </>
                            ) : null}
                        </div>
                    </li>
                ))}
            </ul>
        </Section>
    );
}

function PhotosCard({ unit, photos }: Props) {
    const t = useT();

    return (
        <Section title={t('units.sections.photos')}>
            <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                {photos.map((p) => (
                    <li key={p.id} className="space-y-2">
                        <div className="relative aspect-[4/3] overflow-hidden rounded-sm border bg-muted">
                            <img src={p.url} alt="" loading="lazy" className={p.is_hidden ? 'h-full w-full object-cover opacity-40' : 'h-full w-full object-cover'} />
                        </div>
                        <div className="flex flex-wrap gap-1">
                            {p.kind === 'floor_plan' ? <Badge variant="neutral">{t('units.photos.floor_plan')}</Badge> : null}
                            {p.is_hidden ? (
                                <Badge variant="destructive" className="gap-1">
                                    <EyeOff className="h-3 w-3" aria-hidden="true" />
                                    {t('units.photos.hidden')}
                                </Badge>
                            ) : null}
                            {p.is_project_image ? <Badge variant="outline">{t('units.photos.project')}</Badge> : null}
                            {p.is_public ? <Badge variant="success">{t('units.photos.public')}</Badge> : null}
                        </div>
                        {p.hidden_reason ? <p className="text-xs text-muted-foreground">“{p.hidden_reason}”</p> : null}
                        <div className="flex flex-wrap gap-1">
                            {p.is_hidden ? (
                                <Button type="button" variant="ghost" size="sm" onClick={() => router.put(`${base(unit.id)}/photos/${p.id}`, { hidden: false }, opts)}>
                                    {t('units.photos.show')}
                                </Button>
                            ) : (
                                <ReasonDialog
                                    title={t('units.photos.hide_title')}
                                    consequence={t('units.photos.hide_consequence')}
                                    confirmLabel={t('units.photos.hide')}
                                    trigger={
                                        <Button type="button" variant="ghost" size="sm">
                                            {t('units.photos.hide')}
                                        </Button>
                                    }
                                    onConfirm={(reason) => router.put(`${base(unit.id)}/photos/${p.id}`, { hidden: true, reason }, opts)}
                                />
                            )}
                            {p.kind === 'photo' ? (
                                <Button type="button" variant="ghost" size="sm" aria-pressed={p.is_project_image} onClick={() => router.put(`${base(unit.id)}/photos/${p.id}`, { project_image: !p.is_project_image }, opts)}>
                                    {t('units.photos.project')}
                                </Button>
                            ) : null}
                        </div>
                    </li>
                ))}
            </ul>
        </Section>
    );
}

function DuplicatesCard({ duplicates }: Props) {
    const t = useT();

    return (
        <Section title={t('units.sections.duplicates')}>
            <ul className="divide-y text-sm">
                {duplicates.map((d, i) => (
                    <li key={i} className="flex flex-wrap items-center gap-2 py-2">
                        <Num className="font-mono text-xs text-muted-foreground">{d.at}</Num>
                        <Badge variant={d.blocked ? 'destructive' : 'warning'}>{d.blocked ? t('units.duplicates.blocked') : t('units.duplicates.warning')}</Badge>
                        <span>{t(`duplicates.kinds.${d.kind}`, d.kind)}</span>
                        <span className="text-muted-foreground">{t(`duplicates.stages.${d.stage}`, d.stage)}</span>
                        {d.other && d.other_id ? (
                            <Link href={`/dashboard/units/${d.other_id}`} className="font-mono underline underline-offset-4" dir="ltr">
                                {t('units.duplicates.with', { unit: d.other })}
                            </Link>
                        ) : null}
                    </li>
                ))}
            </ul>
        </Section>
    );
}
