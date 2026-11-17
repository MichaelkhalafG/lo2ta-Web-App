import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import DashboardLayout from '@/Components/DashboardLayout';
import { useT } from '@/lib/i18n';

export default function Login() {
    const t = useT();
    const form = useForm({ email: '', password: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/dashboard/login', { onFinish: () => form.reset('password') });
    };

    return (
        <DashboardLayout title={t('login.title')}>
            <h1>{t('login.title')}</h1>
            <form onSubmit={submit} noValidate>
                <p>
                    <label htmlFor="email">{t('login.email')}</label>
                    <input
                        id="email"
                        type="email"
                        dir="ltr"
                        autoComplete="username"
                        required
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        aria-invalid={form.errors.email ? true : undefined}
                        aria-describedby={form.errors.email ? 'email-error' : undefined}
                    />
                </p>
                {form.errors.email && (
                    <p id="email-error" role="alert">
                        {form.errors.email}
                    </p>
                )}
                <p>
                    <label htmlFor="password">{t('login.password')}</label>
                    <input
                        id="password"
                        type="password"
                        dir="ltr"
                        autoComplete="current-password"
                        required
                        value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)}
                    />
                </p>
                <button type="submit" disabled={form.processing}>
                    {t('login.submit')}
                </button>
            </form>
        </DashboardLayout>
    );
}
