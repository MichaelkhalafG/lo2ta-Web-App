import { type ReactNode, useId } from 'react';

import { Label } from '@/Components/ui/label';
import { cn } from '@/lib/utils';

export interface FieldControlAttrs {
    id: string;
    'aria-describedby': string | undefined;
    'aria-invalid': boolean | undefined;
    'aria-errormessage': string | undefined;
    required: true | undefined;
    'aria-required': true | undefined;
}

export interface FieldShellProps {
    label: string;
    error?: string | null;
    hint?: string;
    required?: boolean;
    className?: string;
    inline?: boolean;
    labelHidden?: boolean;
}

export function Field({
    label,
    error = null,
    hint,
    required = false,
    className,
    inline = false,
    labelHidden = false,
    render,
}: FieldShellProps & {
    render: (attrs: FieldControlAttrs) => ReactNode;
}) {
    const id = `f-${useId().replace(/:/g, '')}`;
    const hintId = `${id}-hint`;
    const errorId = `${id}-error`;
    const describedBy = [hint ? hintId : null, error !== null ? errorId : null].filter(Boolean).join(' ') || undefined;

    const control = render({
        id,
        'aria-describedby': describedBy,
        'aria-invalid': error !== null ? true : undefined,
        'aria-errormessage': error !== null ? errorId : undefined,
        required: required || undefined,
        'aria-required': required || undefined,
    });

    return (
        <div className={cn(labelHidden ? undefined : 'space-y-2', className)}>
            {inline ? (
                <div className="flex items-center justify-between gap-4">
                    <Label htmlFor={id} required={required} className={labelHidden ? 'sr-only' : undefined}>
                        {label}
                    </Label>
                    {control}
                </div>
            ) : (
                <>
                    <Label htmlFor={id} required={required} className={labelHidden ? 'sr-only' : undefined}>
                        {label}
                    </Label>
                    {control}
                </>
            )}

            {hint ? (
                <p id={hintId} className="text-xs text-muted-foreground">
                    {hint}
                </p>
            ) : null}
            {error !== null ? (
                <p id={errorId} role="alert" className="text-xs font-medium text-destructive">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
