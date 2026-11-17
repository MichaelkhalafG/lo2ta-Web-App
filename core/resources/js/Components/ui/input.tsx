import * as React from 'react';

import { cn } from '@/lib/utils';

const Input = React.forwardRef<HTMLInputElement, React.InputHTMLAttributes<HTMLInputElement>>(
    ({ className, type = 'text', ...props }, ref) => (
        <input
            ref={ref}
            type={type}
            className={cn(
                'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background',
                'placeholder:text-muted-foreground',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                'disabled:cursor-not-allowed disabled:opacity-50',
                'aria-[invalid=true]:border-destructive aria-[invalid=true]:focus-visible:ring-destructive',
                'file:border-0 file:bg-transparent file:text-sm file:font-medium',
                className,
            )}
            {...props}
        />
    ),
);
Input.displayName = 'Input';

const Textarea = React.forwardRef<HTMLTextAreaElement, React.TextareaHTMLAttributes<HTMLTextAreaElement>>(
    ({ className, rows = 4, ...props }, ref) => (
        <textarea
            ref={ref}
            rows={rows}
            className={cn(
                'flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background',
                'placeholder:text-muted-foreground',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                'disabled:cursor-not-allowed disabled:opacity-50',
                'aria-[invalid=true]:border-destructive',
                className,
            )}
            {...props}
        />
    ),
);
Textarea.displayName = 'Textarea';

const Select = React.forwardRef<HTMLSelectElement, React.SelectHTMLAttributes<HTMLSelectElement>>(
    ({ className, children, ...props }, ref) => (
        <select
            ref={ref}
            className={cn(
                'flex h-10 w-full appearance-none rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                'disabled:cursor-not-allowed disabled:opacity-50',
                'aria-[invalid=true]:border-destructive',
                className,
            )}
            {...props}
        >
            {children}
        </select>
    ),
);
Select.displayName = 'Select';

export { Input, Select, Textarea };
