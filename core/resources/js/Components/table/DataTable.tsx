import { router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Download,
    Inbox,
    RotateCcw,
    Search,
} from 'lucide-react';
import { type ReactNode, useCallback, useMemo, useRef, useState } from 'react';

import { Alert } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Skeleton } from '@/Components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { TablePayload } from '@/types/global';

function sortName(key: string): string {
    const at = key.lastIndexOf('.');

    return at === -1 ? key : key.slice(at + 1);
}

export interface BulkScope {
    matching: boolean;
    count: number;
    query: string;
}

export interface Column<Row> {
    key: string;
    header: string;
    cell: (row: Row) => ReactNode;
    sortable?: boolean;
    className?: string;
    headerClassName?: string;
    hideOnMobile?: boolean;
    hideBelow?: 'lg' | 'xl' | '2xl';
}

const HIDE_BELOW: Record<'lg' | 'xl' | '2xl', string> = {
    lg: 'hidden lg:table-cell',
    xl: 'hidden xl:table-cell',
    '2xl': 'hidden 2xl:table-cell',
};

export interface DataTableProps<Row> {
    table: TablePayload<Row>;
    columns: Array<Column<Row>>;
    cellAlign?: 'middle' | 'top';
    rowId: (row: Row) => string | number;
    rowActions?: (row: Row) => ReactNode;
    filters?: (
        setFilter: (key: string, value: string | null) => void,
        current: Record<string, string | null>,
        setFilters: (values: Record<string, string | null>) => void,
    ) => ReactNode;
    bulkActions?: (selected: Array<string | number>, clear: () => void, scope: BulkScope) => ReactNode;
    searchPlaceholder?: string;
    emptyTitle?: string;
    emptyDescription?: string;
    only?: string[];
    error?: string | null;
}

export function DataTable<Row>({
    table,
    columns,
    rowId,
    rowActions,
    filters,
    bulkActions,
    searchPlaceholder,
    emptyTitle,
    emptyDescription,
    only,
    error = null,
    cellAlign = 'middle',
}: DataTableProps<Row>) {
    const t = useT();
    const alignClass = cellAlign === 'top' ? 'align-top' : undefined;
    const searchText = searchPlaceholder ?? t('table.search_placeholder', 'بحث…');
    const emptyHeading = emptyTitle ?? t('table.empty_title', 'لا توجد نتائج');
    const emptyBody = emptyDescription ?? t('table.empty_description', 'جرِّب تعديل البحث أو عوامل التصفية.');

    const { data, meta } = table;
    const [term, setTerm] = useState(meta.search ?? '');
    const [busy, setBusy] = useState(false);
    const [selected, setSelected] = useState<Array<string | number>>([]);
    const [allMatching, setAllMatching] = useState(false);
    const debounce = useRef<number | undefined>(undefined);
    const [jump, setJump] = useState(String(meta.page));

    // A new page, search, sort or filter set from the server is a new selection universe, and moves the search box
    // and the page box to what the server applied (Back and Forward included). Adjusted during render, not in an
    // effect, so there is no extra render with stale values.
    const viewKey = [meta.page, meta.search, meta.sort, meta.direction, JSON.stringify(meta.filters)].join('|');
    const [seenKey, setSeenKey] = useState(viewKey);
    if (seenKey !== viewKey) {
        setSeenKey(viewKey);
        setSelected([]);
        setAllMatching(false);
        setTerm(meta.search ?? '');
        setJump(String(meta.page));
    }

    const visit = useCallback(
        (params: Record<string, string | number | null>) => {
            const base: Record<string, string | number | null> = {
                q: meta.search,
                sort: meta.sort,
                direction: meta.direction,
                per_page: meta.per_page,
                page: meta.page,
            };
            for (const [key, value] of Object.entries(meta.filters)) {
                base[`filters[${key}]`] = value;
            }

            const query: Record<string, string | number> = {};
            for (const [key, value] of Object.entries({ ...base, ...params })) {
                if (value !== null && value !== '') {
                    query[key] = value;
                }
            }

            router.get(window.location.pathname, query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                ...(only !== undefined ? { only } : {}),
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
            });
        },
        [meta, only],
    );

    const setFilter = useCallback(
        (key: string, value: string | null) => visit({ [`filters[${key}]`]: value, page: 1 }),
        [visit],
    );

    /** Several filters in ONE visit (a date range): two setFilter calls would race and the first would be lost. */
    const setFilters = useCallback(
        (values: Record<string, string | null>) => {
            const params: Record<string, string | null> = {};
            for (const [key, value] of Object.entries(values)) {
                params[`filters[${key}]`] = value;
            }
            visit({ ...params, page: 1 });
        },
        [visit],
    );

    const onSearch = useCallback(
        (value: string) => {
            setTerm(value);
            window.clearTimeout(debounce.current);
            debounce.current = window.setTimeout(() => visit({ q: value === '' ? null : value, page: 1 }), 350);
        },
        [visit],
    );

    const toggleSort = useCallback(
        (key: string) => {
            const same = meta.sort === key;
            visit({ sort: key, direction: same && meta.direction === 'asc' ? 'desc' : 'asc', page: 1 });
        },
        [meta.direction, meta.sort, visit],
    );

    // The export is this URL plus export=csv: the file matches the screen (filters, search, sort).
    const exportHref = (() => {
        if (typeof window === 'undefined') {
            return '';
        }
        const url = new URL(window.location.href);
        url.searchParams.set('export', 'csv');
        url.searchParams.delete('page');
        return `${url.pathname}${url.search}`;
    })();

    const allOnPage = useMemo(() => data.map((row) => rowId(row)), [data, rowId]);
    const clearSelection = useCallback(() => {
        setSelected([]);
        setAllMatching(false);
    }, []);
    const headerState: boolean | 'indeterminate' =
        selected.length === 0 ? false : selected.length === allOnPage.length ? true : 'indeterminate';

    const canSort = (column: Column<Row>) => column.sortable !== false && meta.sortable.includes(sortName(column.key));
    const columnCount = columns.length + (bulkActions ? 1 : 0) + (rowActions ? 1 : 0);
    const filtered = meta.search !== null || Object.values(meta.filters).some((value) => value !== null);

    return (
        <Card className="overflow-hidden">
            <div className="flex flex-wrap items-center gap-3 border-b p-4">
                <div className="relative min-w-[14rem] flex-1">
                    <Search className="pointer-events-none absolute inset-y-0 start-3 my-auto h-4 w-4 text-muted-foreground" aria-hidden="true" />
                    <Input
                        type="search"
                        value={term}
                        onChange={(event) => onSearch(event.target.value)}
                        placeholder={searchText}
                        aria-label={searchText}
                        className="ps-9"
                    />
                </div>
                {filters?.(setFilter, meta.filters, setFilters)}
                {filtered ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            const cleared: Record<string, string | number | null> = { q: null, page: 1 };
                            for (const key of Object.keys(meta.filters)) {
                                cleared[`filters[${key}]`] = null;
                            }
                            visit(cleared);
                        }}
                        className="gap-1.5"
                    >
                        <RotateCcw className="h-3.5 w-3.5" />
                        {t('table.clear_filters', 'إلغاء التصفية')}
                    </Button>
                ) : null}
                <span className="ms-auto text-xs text-muted-foreground" aria-live="polite">
                    {busy ? t('table.loading', 'جارٍ التحميل…') : t('table.row_count', ':count سجل', { count: meta.total })}
                </span>
                {meta.exportable ? (
                    <Button asChild variant="outline" size="sm" className="gap-1.5">
                        <a href={exportHref} download title={t('table.download_filtered', 'تنزيل :count سجل بعوامل التصفية الحالية', { count: meta.total })}>
                            <Download className="h-3.5 w-3.5" />
                            {t('common.export_csv', 'تصدير CSV')}
                        </a>
                    </Button>
                ) : null}
            </div>

            {error !== null ? (
                <div className="border-b p-4">
                    <Alert tone="error" title={t('table.load_failed', 'تعذّر تحميل البيانات')}>
                        {error}
                    </Alert>
                </div>
            ) : null}

            {bulkActions && selected.length > 0 ? (
                <div className="border-b bg-accent px-4 py-2.5 text-sm">
                    <div className="flex flex-wrap items-center gap-3">
                        <span className="font-medium">
                            {allMatching
                                ? t('table.selected_all_matching', 'كل :count سجل مطابق للتصفية', { count: meta.total })
                                : t('table.selected_count', ':count محدد', { count: selected.length })}
                        </span>
                        {bulkActions(selected, clearSelection, {
                            matching: allMatching,
                            count: allMatching ? meta.total : selected.length,
                            query: allMatching && typeof window !== 'undefined' ? window.location.search : '',
                        })}
                        <Button type="button" variant="ghost" size="sm" className="ms-auto" onClick={clearSelection}>
                            {t('table.clear_selection', 'إلغاء التحديد')}
                        </Button>
                    </div>

                    {selected.length === allOnPage.length && meta.total > allOnPage.length ? (
                        <p className="mt-1.5 text-xs">
                            {allMatching ? (
                                <button
                                    type="button"
                                    className="underline underline-offset-2"
                                    onClick={() => setAllMatching(false)}
                                >
                                    {t('table.select_page_only', 'اقتصر على هذه الصفحة (:count)', { count: allOnPage.length })}
                                </button>
                            ) : (
                                <button
                                    type="button"
                                    className="underline underline-offset-2"
                                    onClick={() => setAllMatching(true)}
                                >
                                    {t('table.select_all_matching', 'حدِّد كل :count سجل مطابق للتصفية', { count: meta.total })}
                                </button>
                            )}
                        </p>
                    ) : null}
                </div>
            ) : null}

            <Table>
                <TableHeader>
                    <TableRow>
                        {bulkActions ? (
                            <TableHead className="w-10">
                                <Checkbox
                                    checked={headerState}
                                    onCheckedChange={(value) => {
                                        setSelected(value === true ? allOnPage : []);
                                        if (value !== true) {
                                            setAllMatching(false);
                                        }
                                    }}
                                    aria-label={t('table.select_all_rows', 'تحديد كل الصفوف')}
                                />
                            </TableHead>
                        ) : null}
                        {columns.map((column) => (
                            <TableHead
                                key={column.key}
                                className={cn(
                                    column.headerClassName,
                                    column.hideOnMobile && 'hidden sm:table-cell',
                                    column.hideBelow && HIDE_BELOW[column.hideBelow],
                                )}
                                aria-sort={
                                    meta.sort === sortName(column.key)
                                        ? meta.direction === 'asc'
                                            ? 'ascending'
                                            : 'descending'
                                        : undefined
                                }
                            >
                                {canSort(column) ? (
                                    <button
                                        type="button"
                                        onClick={() => toggleSort(sortName(column.key))}
                                        className="inline-flex items-center gap-1 rounded transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    >
                                        {column.header}
                                        {meta.sort !== sortName(column.key) ? (
                                            <ArrowUpDown className="h-3 w-3 opacity-50" aria-hidden="true" />
                                        ) : meta.direction === 'asc' ? (
                                            <ArrowUp className="h-3 w-3" aria-hidden="true" />
                                        ) : (
                                            <ArrowDown className="h-3 w-3" aria-hidden="true" />
                                        )}
                                    </button>
                                ) : (
                                    column.header
                                )}
                            </TableHead>
                        ))}
                        {rowActions ? <TableHead className="w-16 text-end">{t('common.actions', 'إجراءات')}</TableHead> : null}
                    </TableRow>
                </TableHeader>

                <TableBody>
                    {busy && data.length === 0
                        ? Array.from({ length: 5 }).map((_, index) => (
                              <TableRow key={`skeleton-${index}`}>
                                  <TableCell colSpan={columnCount}>
                                      <Skeleton className="h-5 w-full" />
                                  </TableCell>
                              </TableRow>
                          ))
                        : null}

                    {!busy && data.length === 0 ? (
                        <TableRow>
                            <TableCell colSpan={columnCount}>
                                <div className="flex flex-col items-center gap-2 py-10 text-center">
                                    <Inbox className="h-8 w-8 text-muted-foreground" aria-hidden="true" />
                                    <p className="font-medium">{emptyHeading}</p>
                                    <p className="max-w-sm text-sm text-muted-foreground">
                                        {filtered ? emptyBody : t('table.no_data_yet', 'لا توجد بيانات لعرضها بعد.')}
                                    </p>
                                </div>
                            </TableCell>
                        </TableRow>
                    ) : null}

                    {data.map((row) => {
                        const id = rowId(row);
                        const isSelected = selected.includes(id);

                        return (
                            <TableRow key={id} data-state={isSelected ? 'selected' : undefined}>
                                {bulkActions ? (
                                    <TableCell className={alignClass}>
                                        <Checkbox
                                            checked={isSelected}
                                            onCheckedChange={(value) =>
                                                setSelected((current) => (value === true ? [...current, id] : current.filter((item) => item !== id)))
                                            }
                                            aria-label={t('table.select_row', 'تحديد الصف')}
                                        />
                                    </TableCell>
                                ) : null}
                                {columns.map((column) => (
                                    <TableCell
                                        key={column.key}
                                        className={cn(
                                            alignClass,
                                            column.className,
                                            column.hideOnMobile && 'hidden sm:table-cell',
                                            column.hideBelow && HIDE_BELOW[column.hideBelow],
                                        )}
                                    >
                                        {column.cell(row)}
                                    </TableCell>
                                ))}
                                {rowActions ? <TableCell className={cn('text-end', alignClass)}>{rowActions(row)}</TableCell> : null}
                            </TableRow>
                        );
                    })}
                </TableBody>
            </Table>

            {meta.last_page > 1 ? (
                <div className="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3 text-sm">
                    <span className="text-muted-foreground">
                        {t('table.range', ':from–:to من :total', {
                            from: meta.from ?? 0,
                            to: meta.to ?? 0,
                            total: meta.total,
                        })}
                    </span>
                    <div className="flex flex-wrap items-center gap-2">
                        <label className="flex items-center gap-1.5 text-xs text-muted-foreground">
                            {t('table.per_page', 'لكل صفحة')}
                            <select
                                className="h-8 rounded-md border border-input bg-background px-2 text-sm"
                                value={meta.per_page}
                                onChange={(event) => visit({ per_page: Number(event.target.value), page: 1 })}
                                aria-label={t('table.per_page', 'لكل صفحة')}
                            >
                                {Array.from(new Set([25, 50, 100, meta.per_page]))
                                    .sort((a, b) => a - b)
                                    .map((size) => (
                                        <option key={size} value={size}>
                                            {size}
                                        </option>
                                    ))}
                            </select>
                        </label>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={meta.page <= 1}
                            onClick={() => visit({ page: 1 })}
                            aria-label={t('table.first_page', 'الصفحة الأولى')}
                            title={t('table.first_page', 'الصفحة الأولى')}
                            className="px-2"
                        >
                            <ChevronsRight className="h-4 w-4 ltr:hidden" />
                            <ChevronsLeft className="h-4 w-4 rtl:hidden" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={meta.page <= 1}
                            onClick={() => visit({ page: meta.page - 1 })}
                            className="gap-1"
                        >
                            <ChevronRight className="h-4 w-4 ltr:hidden" />
                            <ChevronLeft className="h-4 w-4 rtl:hidden" />
                            {t('table.previous', 'السابق')}
                        </Button>

                        <form
                            className="flex items-center gap-1 px-1 text-xs text-muted-foreground"
                            onSubmit={(event) => {
                                event.preventDefault();
                                const wanted = Number(jump);
                                if (Number.isFinite(wanted) && wanted >= 1 && wanted <= meta.last_page) {
                                    visit({ page: Math.trunc(wanted) });
                                }
                            }}
                        >
                            <input
                                type="number"
                                min={1}
                                max={meta.last_page}
                                value={jump}
                                onChange={(event) => setJump(event.target.value)}
                                aria-label={t('table.go_to_page', 'اذهب إلى صفحة رقم')}
                                className="h-8 w-16 rounded-md border border-input bg-background px-2 text-center text-sm tabular-nums"
                                dir="ltr"
                            />
                            <span>/</span>
                            <span className="tabular-nums" dir="ltr">
                                {meta.last_page}
                            </span>
                        </form>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={meta.page >= meta.last_page}
                            onClick={() => visit({ page: meta.page + 1 })}
                            className="gap-1"
                        >
                            {t('table.next', 'التالي')}
                            <ChevronLeft className="h-4 w-4 ltr:hidden" />
                            <ChevronRight className="h-4 w-4 rtl:hidden" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={meta.page >= meta.last_page}
                            onClick={() => visit({ page: meta.last_page })}
                            aria-label={t('table.last_page', 'الصفحة الأخيرة')}
                            title={t('table.last_page', 'الصفحة الأخيرة')}
                            className="px-2"
                        >
                            <ChevronsLeft className="h-4 w-4 ltr:hidden" />
                            <ChevronsRight className="h-4 w-4 rtl:hidden" />
                        </Button>
                    </div>
                </div>
            ) : null}
        </Card>
    );
}
