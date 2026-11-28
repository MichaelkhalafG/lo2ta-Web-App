import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { KeyRound, Loader2 } from 'lucide-react';
import { useEffect } from 'react';

import { PasswordField } from '@/Components/form/PasswordField';
import { Wordmark } from '@/Components/shell/Wordmark';
import { Alert } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { useT } from '@/lib/i18n';

interface Props {
    token: string | null;
    purpose: 'invite' | 'reset' | null;
    name: string | null;
    minLength: number;
}

/**
 * The set-password page a staff member reaches from an invite or reset link (G13, G14). Same split layout as the
 * sign-in page. A wrong or used link shows one neutral message, never whether an account exists.
 */
export default function SetPassword({ token, purpose, name, minLength }: Props) {
    const t = useT();
    const { dir, locale, siteName } = usePage().props;
    const form = useForm({ password: '', password_confirmation: '' });
    const other = locale === 'ar' ? 'en' : 'ar';
    const title = token === null ? t('password_link.invalid_title') : purpose === 'invite' ? t('password_link.title_invite') : t('password_link.title_reset');

    useEffect(() => {
        document.documentElement.setAttribute('dir', dir);
        document.documentElement.setAttribute('lang', locale);
    }, [dir, locale]);

    return (
        <div dir={dir} className="min-h-screen bg-background lg:grid lg:grid-cols-[1.05fr_1fr]">
            <Head title={`${title} | ${siteName}`} />

            <div className="flex items-center justify-center border-b bg-muted px-6 py-16 lg:border-e lg:border-b-0 lg:py-24">
                <div className="flex flex-col items-center gap-6 text-center">
                    <Wordmark className="scale-150" />
                    <p className="mt-4 text-sm text-muted-foreground">{t('title')}</p>
                </div>
            </div>

            <div className="flex items-center justify-center px-6 py-12 sm:px-10">
                <div className="w-full max-w-sm space-y-8">
                    <div className="flex items-center justify-between gap-4">
                        <h1 className="text-2xl font-semibold">{title}</h1>
                        <button type="button" lang={other} className="text-xs text-muted-foreground hover:text-foreground" onClick={() => router.post('/dashboard/locale', { locale: other })}>
                            {t('switch_language')}
                        </button>
                    </div>

                    {token === null ? (
                        <div className="space-y-4">
                            <Alert tone="warning">{t('password_link.invalid')}</Alert>
                            <Link href="/dashboard/login" className="text-sm underline underline-offset-4">
                                {t('password_link.to_login')}
                            </Link>
                        </div>
                    ) : (
                        <form
                            className="space-y-5"
                            noValidate
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.post(`/dashboard/password/${token}`, { onFinish: () => form.reset() });
                            }}
                        >
                            {name ? <p className="text-sm">{t('password_link.hello', { name })}</p> : null}
                            <p className="text-sm text-muted-foreground">{t('password_link.intro', { min: minLength })}</p>
                            <PasswordField
                                label={t('password_link.password')}
                                autoComplete="new-password"
                                required
                                value={form.data.password}
                                onChange={(v) => form.setData('password', v)}
                                error={form.errors.password ?? null}
                            />
                            <PasswordField
                                label={t('password_link.confirm')}
                                autoComplete="new-password"
                                required
                                value={form.data.password_confirmation}
                                onChange={(v) => form.setData('password_confirmation', v)}
                                error={form.errors.password_confirmation ?? null}
                            />
                            <Button type="submit" className="w-full gap-2" disabled={form.processing}>
                                {form.processing ? <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" /> : <KeyRound className="h-4 w-4" aria-hidden="true" />}
                                {t('password_link.submit')}
                            </Button>
                        </form>
                    )}
                </div>
            </div>
        </div>
    );
}
