import { useForm } from '@inertiajs/react';

import { FormActions } from '@/Components/form/FormActions';
import { PasswordField } from '@/Components/form/PasswordField';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useT } from '@/lib/i18n';

interface Props {
    account: { name: string; email: string; role_label: string; last_login_at: string | null };
    minLength: number;
}

/** "My account" (G13): every staff member's own details and their own password change, with the current one. */
export default function AccountIndex({ account, minLength }: Props) {
    const t = useT();
    const form = useForm({ current_password: '', password: '', password_confirmation: '' });

    return (
        <DashboardLayout title={t('account.title')} crumbs={[{ label: t('account.title') }]}>
            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
                <Card>
                    <CardHeader>
                        <CardTitle>{t('account.details')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                            <dt className="text-muted-foreground">{t('staff.name')}</dt>
                            <dd>{account.name}</dd>
                            <dt className="text-muted-foreground">{t('staff.email')}</dt>
                            <dd dir="ltr" className="truncate text-start">
                                {account.email}
                            </dd>
                            <dt className="text-muted-foreground">{t('account.role')}</dt>
                            <dd>{account.role_label}</dd>
                            <dt className="text-muted-foreground">{t('account.last_login')}</dt>
                            <dd dir="ltr" className="text-start font-mono text-xs tabular-nums">
                                {account.last_login_at ?? '—'}
                            </dd>
                        </dl>
                        <p className="mt-4 text-xs text-muted-foreground">{t('account.details_hint')}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('account.password_title')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="space-y-4"
                            noValidate
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.put('/dashboard/account/password', { preserveScroll: true, onFinish: () => form.reset() });
                            }}
                        >
                            <p className="text-sm text-muted-foreground">{t('account.password_intro', { min: minLength })}</p>
                            <PasswordField
                                label={t('account.current_password')}
                                autoComplete="current-password"
                                required
                                value={form.data.current_password}
                                onChange={(v) => form.setData('current_password', v)}
                                error={form.errors.current_password ?? null}
                            />
                            <PasswordField
                                label={t('account.new_password')}
                                autoComplete="new-password"
                                required
                                value={form.data.password}
                                onChange={(v) => form.setData('password', v)}
                                error={form.errors.password ?? null}
                            />
                            <PasswordField
                                label={t('account.confirm_password')}
                                autoComplete="new-password"
                                required
                                value={form.data.password_confirmation}
                                onChange={(v) => form.setData('password_confirmation', v)}
                                error={form.errors.password_confirmation ?? null}
                            />
                            <FormActions processing={form.processing} dirty={form.isDirty} submitLabel={t('account.save_password')} />
                        </form>
                    </CardContent>
                </Card>
            </div>
        </DashboardLayout>
    );
}
