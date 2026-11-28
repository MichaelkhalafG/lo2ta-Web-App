import { router, useForm, usePage } from '@inertiajs/react';
import { KeyRound, Pencil, UserPlus } from 'lucide-react';
import { useState } from 'react';

import { FormActions } from '@/Components/form/FormActions';
import { SelectField, TextField } from '@/Components/form/TextField';
import { SwitchField } from '@/Components/form/SwitchField';
import { RevisionBlock, type Revision } from '@/Components/review/RevisionBlock';
import { StateBadge } from '@/Components/review/StateBadge';
import { ConfirmAction } from '@/Components/shell/ConfirmAction';
import { Alert } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent } from '@/Components/ui/dialog';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useT } from '@/lib/i18n';

/** A date inside a sentence keeps its own left-to-right order in Arabic (Unicode isolate). */
const ltr = (text: string) => `\u2066${text}\u2069`;

interface Member {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    locale: 'ar' | 'en';
    is_active: boolean;
    is_self: boolean;
    last_login_at: string | null;
    link_until: string | null;
    history: { action: string; at: string | null; by: string; reason: string | null }[];
}

interface Props {
    staff: Member[];
    roles: { value: string; label: string }[];
}

/**
 * Staff (PLAN 2.7, G13, G14; admin only). One row per person with their state and a history drawn as a revision
 * block. New staff are invited by link; a forgotten password gets a link too. Nobody types another person's password.
 */
export default function StaffIndex({ staff, roles }: Props) {
    const t = useT();
    const { errors } = usePage().props as { errors?: Record<string, string> };
    const [editing, setEditing] = useState<Member | 'new' | null>(null);
    const [open, setOpen] = useState<number | null>(null);

    return (
        <DashboardLayout
            title={t('staff.title')}
            crumbs={[{ label: t('staff.title') }]}
            actions={
                <Button type="button" className="gap-1.5" onClick={() => setEditing('new')}>
                    <UserPlus className="h-4 w-4" aria-hidden="true" />
                    {t('staff.invite')}
                </Button>
            }
        >
            <Alert tone="info">
                <p>{t('staff.intro')}</p>
                <p className="mt-1 text-muted-foreground">{t('staff.roles_hint')}</p>
            </Alert>
            {errors?.staff ? <Alert tone="error">{errors.staff}</Alert> : null}

            <ul className="divide-y border-y">
                {staff.map((member) => (
                    <li key={member.id} className="py-4">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="min-w-0 space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">{member.name}</span>
                                    {member.is_self ? <Badge variant="outline">{t('staff.you')}</Badge> : null}
                                    <Badge variant="neutral">{member.role_label}</Badge>
                                    <StateBadge tone={member.is_active ? 'done' : 'stopped'}>{member.is_active ? t('staff.status_active') : t('staff.status_off')}</StateBadge>
                                </div>
                                <p className="truncate text-sm text-muted-foreground" dir="ltr">
                                    {member.email}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {member.last_login_at ? t('staff.last_login', { at: ltr(member.last_login_at) }) : t('staff.never_signed_in')}
                                    {member.link_until ? <> · {t('staff.link_until', { at: ltr(member.link_until) })}</> : null}
                                </p>
                            </div>
                            <div className="flex flex-wrap items-center gap-1">
                                <Button type="button" variant="ghost" size="sm" className="gap-1.5" onClick={() => setEditing(member)}>
                                    <Pencil className="h-4 w-4" aria-hidden="true" />
                                    {t('staff.edit')}
                                </Button>
                                {member.is_active ? (
                                    <ConfirmAction
                                        title={t('staff.send_link')}
                                        consequence={t('staff.send_link_consequence')}
                                        confirmLabel={t('staff.send_link')}
                                        tone="default"
                                        trigger={
                                            <Button type="button" variant="ghost" size="sm" className="gap-1.5">
                                                <KeyRound className="h-4 w-4" aria-hidden="true" />
                                                {t('staff.send_link')}
                                            </Button>
                                        }
                                        onConfirm={() => router.post(`/dashboard/staff/${member.id}/password-link`, {}, { preserveScroll: true })}
                                    />
                                ) : null}
                                <Button type="button" variant="ghost" size="sm" aria-expanded={open === member.id} onClick={() => setOpen(open === member.id ? null : member.id)}>
                                    {t('staff.history')}
                                </Button>
                            </div>
                        </div>
                        {open === member.id ? (
                            <RevisionBlock
                                className="mt-3"
                                rows={member.history.map((h): Revision => ({ at: h.at, event: h.action, by: h.by, note: h.reason }))}
                            />
                        ) : null}
                    </li>
                ))}
            </ul>

            <Dialog open={editing !== null} onOpenChange={(next) => (next ? null : setEditing(null))}>
                {editing !== null ? (
                    <DialogContent title={editing === 'new' ? t('staff.invite') : t('staff.edit')} className="max-h-[90dvh] overflow-y-auto sm:max-w-lg">
                        {editing === 'new' ? <InviteForm roles={roles} onDone={() => setEditing(null)} /> : <EditForm member={editing} roles={roles} onDone={() => setEditing(null)} />}
                    </DialogContent>
                ) : null}
            </Dialog>
        </DashboardLayout>
    );
}

function InviteForm({ roles, onDone }: { roles: Props['roles']; onDone: () => void }) {
    const t = useT();
    const form = useForm({ name: '', email: '', role: 'reviewer', locale: 'ar' });

    return (
        <form
            className="space-y-4"
            noValidate
            onSubmit={(event) => {
                event.preventDefault();
                form.post('/dashboard/staff', { preserveScroll: true, onSuccess: onDone });
            }}
        >
            <p className="text-sm text-muted-foreground">{t('staff.invite_hint')}</p>
            <TextField label={t('staff.name')} value={form.data.name} onChange={(v) => form.setData('name', v)} required error={form.errors.name ?? null} />
            <TextField label={t('staff.email')} type="email" dir="ltr" value={form.data.email} onChange={(v) => form.setData('email', v)} required error={form.errors.email ?? null} />
            <SelectField label={t('staff.role')} value={form.data.role} onChange={(v) => form.setData('role', v)} options={roles} required error={form.errors.role ?? null} />
            <LocaleField value={form.data.locale} onChange={(v) => form.setData('locale', v)} error={form.errors.locale ?? null} />
            <FormActions processing={form.processing} dirty={form.isDirty} onCancel={onDone} submitLabel={t('staff.send_invite')} />
        </form>
    );
}

function EditForm({ member, roles, onDone }: { member: Member; roles: Props['roles']; onDone: () => void }) {
    const t = useT();
    const form = useForm({ name: member.name, role: member.role, locale: member.locale, is_active: member.is_active });

    return (
        <form
            className="space-y-4"
            noValidate
            onSubmit={(event) => {
                event.preventDefault();
                form.put(`/dashboard/staff/${member.id}`, { preserveScroll: true, onSuccess: onDone });
            }}
        >
            <p className="text-sm text-muted-foreground" dir="ltr">
                {member.email}
            </p>
            <TextField label={t('staff.name')} value={form.data.name} onChange={(v) => form.setData('name', v)} required error={form.errors.name ?? null} />
            <SelectField label={t('staff.role')} value={form.data.role} onChange={(v) => form.setData('role', v)} options={roles} required disabled={member.is_self} error={form.errors.role ?? null} />
            <LocaleField value={form.data.locale} onChange={(v) => form.setData('locale', v as 'ar' | 'en')} error={form.errors.locale ?? null} />
            <SwitchField label={t('staff.active')} hint={t('staff.active_hint')} checked={form.data.is_active} disabled={member.is_self} onChange={(v) => form.setData('is_active', v)} error={form.errors.is_active ?? null} />
            <FormActions processing={form.processing} dirty={form.isDirty} onCancel={onDone} />
        </form>
    );
}

function LocaleField({ value, onChange, error }: { value: string; onChange: (value: string) => void; error: string | null }) {
    const t = useT();

    return (
        <SelectField
            label={t('staff.locale')}
            value={value}
            onChange={onChange}
            options={[
                { value: 'ar', label: t('staff.locale_ar') },
                { value: 'en', label: t('staff.locale_en') },
            ]}
            required
            error={error}
        />
    );
}
