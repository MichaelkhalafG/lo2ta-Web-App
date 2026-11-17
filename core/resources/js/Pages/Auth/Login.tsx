import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Loader2, LogIn } from 'lucide-react';
import { useEffect } from 'react';

import { PasswordField } from '@/Components/form/PasswordField';
import { TextField } from '@/Components/form/TextField';
import { Wordmark } from '@/Components/shell/Wordmark';
import { Alert } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { useT } from '@/lib/i18n';

/**
 * Dashboard sign-in. A split page: the wordmark alone on the bg-1 panel (reading-start side in both languages),
 * the form on the page ground. E-mail and password stay LTR in both languages.
 */
export default function Login() {
    const t = useT();
    const { dir, locale, siteName, flash } = usePage().props;
    const form = useForm({ email: '', password: '' });
    const other = locale === 'ar' ? 'en' : 'ar';

    useEffect(() => {
        document.documentElement.setAttribute('dir', dir);
        document.documentElement.setAttribute('lang', locale);
    }, [dir, locale]);

    return (
        <div dir={dir} className="min-h-screen bg-background lg:grid lg:grid-cols-[1.05fr_1fr]">
            <Head title={`${t('login.title')} | ${siteName}`} />

            <div className="flex items-center justify-center border-b bg-muted px-6 py-16 lg:border-e lg:border-b-0 lg:py-24">
                <div className="flex flex-col items-center gap-6 text-center">
                    <Wordmark className="scale-150" />
                    <p className="mt-4 text-sm text-muted-foreground">{t('title')}</p>
                </div>
            </div>

            <div className="flex items-center justify-center px-6 py-12 sm:px-10">
                <div className="w-full max-w-sm space-y-8">
                    <div className="flex items-center justify-between gap-4">
                        <h1 className="text-2xl font-semibold">{t('login.title')}</h1>
                        <button
                            type="button"
                            lang={other}
                            className="text-xs text-muted-foreground hover:text-foreground"
                            onClick={() => router.post('/dashboard/locale', { locale: other })}
                        >
                            {t('switch_language')}
                        </button>
                    </div>

                    {flash.status ? <Alert tone="success">{flash.status}</Alert> : null}

                    <form
                        className="space-y-5"
                        noValidate
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post('/dashboard/login', { onFinish: () => form.reset('password') });
                        }}
                    >
                        <TextField
                            label={t('login.email')}
                            type="email"
                            dir="ltr"
                            autoComplete="username"
                            required
                            value={form.data.email}
                            onChange={(value) => form.setData('email', value)}
                            error={form.errors.email ?? null}
                        />
                        <PasswordField
                            label={t('login.password')}
                            autoComplete="current-password"
                            required
                            value={form.data.password}
                            onChange={(value) => form.setData('password', value)}
                            error={form.errors.password ?? null}
                        />
                        <Button type="submit" className="w-full gap-2" disabled={form.processing}>
                            {form.processing ? (
                                <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                            ) : (
                                <LogIn className="h-4 w-4 rtl:-scale-x-100" aria-hidden="true" />
                            )}
                            {t('login.submit')}
                        </Button>
                    </form>
                </div>
            </div>
        </div>
    );
}
