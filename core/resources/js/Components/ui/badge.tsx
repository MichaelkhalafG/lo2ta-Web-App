import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';

import { cn } from '@/lib/utils';

const badgeVariants = cva(
    'inline-flex items-center gap-1 rounded-sm border px-2 py-0.5 text-xs font-medium transition-colors',
    {
        variants: {
            variant: {
                default: 'border-transparent bg-accent text-foreground',
                neutral: 'border-transparent bg-muted text-muted-foreground',
                success: 'border-transparent bg-success-tint text-success',
                warning: 'border-transparent bg-warning-tint text-warning',
                destructive:
                    'border-transparent bg-error-tint text-error',
                outline: 'border-border text-foreground',
                verified: 'border-verified bg-verified-fill text-verified',
            },
        },
        defaultVariants: { variant: 'default' },
    },
);

function Badge({ className, variant, ...props }: React.HTMLAttributes<HTMLSpanElement> & VariantProps<typeof badgeVariants>) {
    return <span className={cn(badgeVariants({ variant }), className)} {...props} />;
}

export { Badge, badgeVariants };
