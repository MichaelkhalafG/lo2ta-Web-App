import * as React from 'react';

import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

function Table({ className, ...props }: React.TableHTMLAttributes<HTMLTableElement>) {
    const t = useT();
    const box = React.useRef<HTMLDivElement>(null);
    const [edges, setEdges] = React.useState({ start: false, end: false });

    const measure = React.useCallback(() => {
        const node = box.current;
        if (node === null) {
            return;
        }
        const offset = Math.abs(node.scrollLeft);
        const hidden = node.scrollWidth - node.clientWidth;

        setEdges({
            start: offset > 1,
            end: hidden - offset > 1,
        });
    }, []);

    React.useEffect(() => {
        const node = box.current;
        if (node === null) {
            return;
        }
        measure();

        const observer = new ResizeObserver(measure);
        observer.observe(node);
        for (const child of Array.from(node.children)) {
            observer.observe(child);
        }

        return () => observer.disconnect();
    }, [measure]);

    return (
        <div className="relative">
            <div
                ref={box}
                onScroll={measure}
                tabIndex={edges.start || edges.end ? 0 : -1}
                role="region"
                aria-label={t('table.scroll_region', 'جدول قابل للتمرير أفقيًا')}
                className="w-full overflow-x-auto scrollbar-visible focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset"
            >
                <table className={cn('w-full caption-bottom border-collapse text-sm', className)} {...props} />
            </div>

            {edges.start ? (
                <span
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-y-0 start-0 w-8 bg-linear-to-r from-background to-transparent rtl:bg-linear-to-l"
                />
            ) : null}
            {edges.end ? (
                <span
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-y-0 end-0 w-8 bg-linear-to-l from-background to-transparent rtl:bg-linear-to-r"
                />
            ) : null}
        </div>
    );
}

function TableHeader({ className, ...props }: React.HTMLAttributes<HTMLTableSectionElement>) {
    return <thead className={cn('[&_tr]:border-b', className)} {...props} />;
}

function TableBody({ className, ...props }: React.HTMLAttributes<HTMLTableSectionElement>) {
    return <tbody className={cn('[&_tr:last-child]:border-0', className)} {...props} />;
}

function TableRow({ className, ...props }: React.HTMLAttributes<HTMLTableRowElement>) {
    return (
        <tr
            className={cn('border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-accent', className)}
            {...props}
        />
    );
}

function isolate(dir: string | undefined, children: React.ReactNode): React.ReactNode {
    return dir === undefined ? children : <bdi dir={dir}>{children}</bdi>;
}

export type CellAlign = 'start' | 'end' | 'center';

const ALIGN: Record<CellAlign, string> = {
    start: 'text-start',
    end: 'text-end',
    center: 'text-center',
};

interface HeadProps extends Omit<React.ThHTMLAttributes<HTMLTableCellElement>, 'align'> {
    align?: CellAlign;
}

function TableHead({ className, align = 'start', dir, children, ...props }: HeadProps) {
    return (
        <th
            className={cn(
                'h-11 whitespace-nowrap px-4 align-middle text-xs font-semibold uppercase tracking-wide text-muted-foreground',
                ALIGN[align],
                className,
            )}
            {...props}
        >
            {isolate(dir, children)}
        </th>
    );
}

interface CellProps extends Omit<React.TdHTMLAttributes<HTMLTableCellElement>, 'align'> {
    align?: CellAlign;
}

function TableCell({ className, align = 'start', dir, children, ...props }: CellProps) {
    return (
        <td className={cn('px-4 py-3 align-middle', ALIGN[align], className)} {...props}>
            {isolate(dir, children)}
        </td>
    );
}

export { Table, TableBody, TableCell, TableHead, TableHeader, TableRow };
