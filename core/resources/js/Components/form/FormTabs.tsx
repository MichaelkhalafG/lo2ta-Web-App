import { type ReactNode, useCallback, useRef } from 'react';

import { cn } from '@/lib/utils';
import { useT } from '@/lib/i18n';

export interface FormTab {
    key: string;
    label: string;
    fields: string[];
}

export function errorsByTab(tabs: FormTab[], errors: Record<string, string>): Record<string, number> {
    const out: Record<string, number> = {};
    for (const tab of tabs) {
        out[tab.key] = 0;
    }

    for (const name of Object.keys(errors)) {
        for (const tab of tabs) {
            if (tab.fields.some((field) => name === field || name.startsWith(`${field}.`))) {
                out[tab.key] = (out[tab.key] ?? 0) + 1;
                break;
            }
        }
    }

    return out;
}

export function firstTabWithError(tabs: FormTab[], errors: Record<string, string>): string | null {
    const counts = errorsByTab(tabs, errors);
    for (const tab of tabs) {
        if ((counts[tab.key] ?? 0) > 0) {
            return tab.key;
        }
    }

    return null;
}

export function FormTabs({
    tabs,
    active,
    onChange,
    errors,
}: {
    tabs: FormTab[];
    active: string;
    onChange: (key: string) => void;
    errors: Record<string, string>;
}) {
    const t = useT();
    const counts = errorsByTab(tabs, errors);
    const list = useRef<HTMLDivElement>(null);

    const onKeyDown = useCallback(
        (event: React.KeyboardEvent) => {
            const index = tabs.findIndex((tab) => tab.key === active);
            if (index === -1) {
                return;
            }
            const rtl = document.documentElement.getAttribute('dir') === 'rtl';
            const forward = rtl ? 'ArrowLeft' : 'ArrowRight';
            const back = rtl ? 'ArrowRight' : 'ArrowLeft';

            let next: number | null = null;
            if (event.key === forward) {
                next = (index + 1) % tabs.length;
            } else if (event.key === back) {
                next = (index - 1 + tabs.length) % tabs.length;
            } else if (event.key === 'Home') {
                next = 0;
            } else if (event.key === 'End') {
                next = tabs.length - 1;
            }

            if (next !== null) {
                event.preventDefault();
                const key = tabs[next]?.key;
                if (key !== undefined) {
                    onChange(key);
                    list.current?.querySelector<HTMLElement>(`[data-tab="${key}"]`)?.focus();
                }
            }
        },
        [tabs, active, onChange],
    );

    return (
        <div
            ref={list}
            role="tablist"
            aria-label={t('form.tabs_label', 'أقسام النموذج')}
            onKeyDown={onKeyDown}
            className="flex flex-wrap gap-1 border-b"
        >
            {tabs.map((tab) => {
                const count = counts[tab.key] ?? 0;
                const current = tab.key === active;

                return (
                    <button
                        key={tab.key}
                        type="button"
                        role="tab"
                        data-tab={tab.key}
                        id={`tab-${tab.key}`}
                        aria-selected={current}
                        aria-controls={`panel-${tab.key}`}
                        tabIndex={current ? 0 : -1}
                        onClick={() => onChange(tab.key)}
                        className={cn(
                            'relative -mb-px flex items-center gap-1.5 rounded-t-md border border-transparent px-3 py-2 text-sm transition-colors',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                            current
                                ? 'border-border border-b-background bg-background font-medium text-foreground'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {tab.label}
                        {count > 0 ? (
                            <span
                                className="rounded-full bg-destructive/10 px-1.5 text-xs font-medium text-destructive"
                                aria-label={t('form.tab_error_count', ':count حقل يحتاج تصحيحًا', { count })}
                            >
                                {count}
                            </span>
                        ) : null}
                    </button>
                );
            })}
        </div>
    );
}

export function TabPanel({ when, active, children }: { when: string; active: string; children: ReactNode }) {
    if (when !== active) {
        return null;
    }

    return (
        <div id={`panel-${when}`} role="tabpanel" aria-labelledby={`tab-${when}`} className="space-y-6">
            {children}
        </div>
    );
}

export function StickySaveBar({ children }: { children: ReactNode }) {
    return (
        <div className="sticky top-header z-20 -mx-4 mb-4 border-b bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80 sm:-mx-6 sm:px-6">
            {children}
        </div>
    );
}
