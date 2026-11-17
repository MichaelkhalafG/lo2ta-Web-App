import { Head, usePage } from '@inertiajs/react';

interface Props {
    stack: { laravel: string; php: string };
}

/** Local-only page that proves Inertia, React, TypeScript, Tailwind and RTL are wired. Replaced in phase 1. */
export default function Setup({ stack }: Props) {
    const { locale, dir } = usePage().props;

    return (
        <>
            <Head title="Setup" />
            <main className="mx-auto max-w-2xl px-6 py-16">
                <h1 className="text-2xl font-semibold">لوحة الإدارة: الإعداد يعمل</h1>
                <p className="mt-4 text-start text-neutral-400">
                    Laravel {stack.laravel} · PHP {stack.php} · locale {locale} · dir {dir}
                </p>
                <div className="mt-8 flex gap-3">
                    <span className="rounded-md border border-neutral-700 ps-4 pe-2 py-1">بداية ← start</span>
                    <span className="ms-auto rounded-md border border-neutral-700 px-3 py-1">نهاية → end</span>
                </div>
            </main>
        </>
    );
}
