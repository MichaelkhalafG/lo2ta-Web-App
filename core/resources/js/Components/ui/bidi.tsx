import * as React from 'react';

import { cn } from '@/lib/utils';

type BdiProps = React.HTMLAttributes<HTMLElement>;

export function Ltr({ className, ...props }: BdiProps) {
    return <bdi dir="ltr" className={cn('inline', className)} {...props} />;
}

export function Num({ className, ...props }: BdiProps) {
    return <bdi dir="ltr" className={cn('inline whitespace-nowrap tabular-nums', className)} {...props} />;
}

export function Name({ className, ...props }: BdiProps) {
    return <bdi dir="auto" className={cn('inline', className)} {...props} />;
}
