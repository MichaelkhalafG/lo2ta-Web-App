import { AlertCircle } from 'lucide-react';
import { useEffect, useRef } from 'react';

import { Alert } from '@/Components/ui/alert';
import { useT } from '@/lib/i18n';

export function ErrorSummary({
    errors,
    labels = {},
    title,
}: {
    errors: Record<string, string>;
    labels?: Record<string, string>;
    title?: string;
}) {
    const t = useT();
    const box = useRef<HTMLDivElement>(null);
    const keys = Object.keys(errors);
    const count = keys.length;

    const signature = keys.sort().join('|');
    useEffect(() => {
        if (count > 0) {
            box.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, [signature, count]);

    if (count === 0) {
        return null;
    }

    return (
        <div ref={box} data-testid="error-summary">
            <Alert
                tone="error"
                title={
                    title ??
                    t('form.error_summary_title', 'لم يُحفَظ: :count حقل يحتاج تصحيحًا', {
                        count,
                    })
                }
            >
                <ul className="mt-1 space-y-1">
                    {keys.map((key) => (
                        <li key={key}>
                            <a
                                href={`#field-${key}`}
                                className="underline underline-offset-2"
                                onClick={(event) => {
                                    const target = document.getElementById(`field-${key}`);
                                    if (target !== null) {
                                        event.preventDefault();
                                        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                        const focusable = target.querySelector<HTMLElement>(
                                            'input, select, textarea, [tabindex]',
                                        );
                                        focusable?.focus({ preventScroll: true });
                                    }
                                }}
                            >
                                <span className="font-medium">{labels[key] ?? key}</span>
                                {' — '}
                                {errors[key]}
                            </a>
                        </li>
                    ))}
                </ul>
            </Alert>
        </div>
    );
}

export function FieldAnchor({ name, children }: { name: string; children: React.ReactNode }) {
    return (
        <div id={`field-${name}`} className="scroll-mt-24">
            {children}
        </div>
    );
}

export function ErrorCount({ errors }: { errors: Record<string, string> }) {
    const t = useT();
    const count = Object.keys(errors).length;

    if (count === 0) {
        return null;
    }

    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-destructive/10 px-2.5 py-0.5 text-xs font-medium text-destructive">
            <AlertCircle className="h-3.5 w-3.5" aria-hidden="true" />
            {t('form.error_count', ':count حقل يحتاج تصحيحًا', { count })}
        </span>
    );
}
