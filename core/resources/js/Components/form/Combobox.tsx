import { Check, ChevronDown, Search } from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';

import { Field, type FieldShellProps } from '@/Components/form/Field';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export interface Option {
    value: string;
    label: string;
    hint?: string;
}

export function Combobox({
    value,
    onChange,
    options,
    placeholder,
    searchPlaceholder,
    emptyText,
    disabled = false,
    ...shell
}: FieldShellProps & {
    value: string | null;
    onChange: (value: string | null) => void;
    options: Option[];
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    disabled?: boolean;
}) {
    const t = useT();
    const placeholderText = placeholder ?? t('form.choose', 'اختر…');
    const searchText = searchPlaceholder ?? t('form.search_placeholder', 'ابحث…');
    const emptyLabel = emptyText ?? t('form.no_results', 'لا نتائج');

    const [open, setOpen] = useState(false);
    const [term, setTerm] = useState('');
    const [active, setActive] = useState(0);
    const listId = `combobox-${useId().replace(/:/g, '')}`;
    const inputRef = useRef<HTMLInputElement>(null);

    const filtered = useMemo(() => {
        const needle = term.trim().toLowerCase();
        if (needle === '') {
            return options;
        }

        return options.filter((option) => option.label.toLowerCase().includes(needle) || option.value.toLowerCase().includes(needle));
    }, [options, term]);


    useEffect(() => {
        if (!open) {
            return;
        }
        const el = document.getElementById(`${listId}-option-${active}`);
        el?.scrollIntoView({ block: 'nearest' });
    }, [active, listId, open]);

    const selected = options.find((option) => option.value === value) ?? null;

    const choose = (option: Option | undefined) => {
        if (option === undefined) {
            return;
        }
        onChange(option.value === value ? null : option.value);
        setOpen(false);
        setTerm('');
    };

    return (
        <Field
            {...shell}
            render={(attrs) => (
                <Popover
                    open={open}
                    onOpenChange={(next) => {
                        setOpen(next);
                        setActive(0);
                    }}
                >
                    <PopoverTrigger asChild>
                        <button
                            {...attrs}
                            type="button"
                            disabled={disabled}
                            role="combobox"
                            aria-expanded={open}
                            aria-controls={listId}
                            className={cn(
                                'flex h-10 w-full items-center justify-between gap-2 rounded-md border border-input bg-background px-3 text-sm',
                                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                                'disabled:cursor-not-allowed disabled:opacity-50',
                                'aria-[invalid=true]:border-destructive',
                                selected === null && 'text-muted-foreground',
                            )}
                        >
                            <span className="truncate">{selected?.label ?? placeholderText}</span>
                            <ChevronDown className="h-4 w-4 shrink-0 opacity-60" aria-hidden="true" />
                        </button>
                    </PopoverTrigger>

                    <PopoverContent className="p-0" align="start">
                        <div className="relative border-b p-2">
                            <Search className="pointer-events-none absolute inset-y-0 start-4 my-auto h-4 w-4 text-muted-foreground" aria-hidden="true" />
                            <input
                                ref={inputRef}
                                value={term}
                                onChange={(event) => {
                                    setTerm(event.target.value);
                                    setActive(0);
                                }}
                                placeholder={searchText}
                                aria-label={searchPlaceholder}
                                aria-controls={listId}
                                aria-activedescendant={filtered.length > 0 ? `${listId}-option-${active}` : undefined}
                                className="h-9 w-full rounded-md bg-transparent ps-8 text-sm outline-none placeholder:text-muted-foreground"
                                onKeyDown={(event) => {
                                    if (event.key === 'ArrowDown') {
                                        event.preventDefault();
                                        setActive((current) => (filtered.length === 0 ? 0 : (current + 1) % filtered.length));
                                    } else if (event.key === 'ArrowUp') {
                                        event.preventDefault();
                                        setActive((current) => (filtered.length === 0 ? 0 : (current - 1 + filtered.length) % filtered.length));
                                    } else if (event.key === 'Home') {
                                        event.preventDefault();
                                        setActive(0);
                                    } else if (event.key === 'End') {
                                        event.preventDefault();
                                        setActive(Math.max(0, filtered.length - 1));
                                    } else if (event.key === 'Enter') {
                                        event.preventDefault();
                                        choose(filtered[active]);
                                    }
                                }}
                            />
                        </div>

                        <ul id={listId} role="listbox" className="max-h-64 overflow-y-auto scrollbar-thin p-1">
                            {filtered.length === 0 ? (
                                <li className="px-3 py-6 text-center text-sm text-muted-foreground">{emptyLabel}</li>
                            ) : (
                                filtered.map((option, index) => (
                                    <li
                                        key={option.value}
                                        id={`${listId}-option-${index}`}
                                        role="option"
                                        aria-selected={option.value === value}
                                        onMouseEnter={() => setActive(index)}
                                        onClick={() => choose(option)}
                                        className={cn(
                                            'flex cursor-pointer items-center gap-2 rounded-sm px-2 py-2 text-sm',
                                            index === active && 'bg-accent text-accent-foreground',
                                        )}
                                    >
                                        <Check className={cn('h-4 w-4 shrink-0', option.value === value ? 'opacity-100' : 'opacity-0')} aria-hidden="true" />
                                        <span className="truncate">{option.label}</span>
                                        {option.hint ? <span className="ms-auto text-xs text-muted-foreground">{option.hint}</span> : null}
                                    </li>
                                ))
                            )}
                        </ul>
                    </PopoverContent>
                </Popover>
            )}
        />
    );
}
