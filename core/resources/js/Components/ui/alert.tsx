import * as React from 'react';
import { AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-react';

import { cn } from '@/lib/utils';

const TONES = {
    info: { className: 'border-border bg-accent text-foreground', Icon: Info },
    success: { className: 'border-success/40 bg-success-tint text-success', Icon: CheckCircle2 },
    warning: { className: 'border-warning/40 bg-warning-tint text-warning', Icon: AlertTriangle },
    error: {
        className: 'border-error/40 bg-error-tint text-error',
        Icon: XCircle,
    },
} as const;

export type AlertTone = keyof typeof TONES;

function Alert({
    tone = 'info',
    title,
    children,
    className,
}: {
    tone?: AlertTone;
    title?: string;
    children?: React.ReactNode;
    className?: string;
}) {
    const { className: toneClass, Icon } = TONES[tone];

    return (
        <div
            role={tone === 'error' || tone === 'warning' ? 'alert' : 'status'}
            className={cn('flex items-start gap-3 rounded-sm border p-4 text-sm', toneClass, className)}
        >
            <Icon className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
            <div className="space-y-1">
                {title ? <p className="font-medium">{title}</p> : null}
                {children ? <div className="text-sm opacity-90">{children}</div> : null}
            </div>
        </div>
    );
}

export { Alert };
