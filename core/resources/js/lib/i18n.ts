import { usePage } from '@inertiajs/react';

import type { Strings } from '@/types/global';

/**
 * Looks up a dotted key in the shared dashboard strings, e.g. t('login.title').
 * Replaces :name placeholders. A missing key shows the key itself, so a gap is visible, never silent.
 */
export function translate(strings: Strings, key: string, replace: Record<string, string | number> = {}): string {
    let node: string | Strings | undefined = strings;
    for (const part of key.split('.')) {
        if (typeof node !== 'object') {
            return key;
        }
        node = node[part];
    }
    if (typeof node !== 'string') {
        return key;
    }

    return Object.entries(replace).reduce((text, [name, value]) => text.replaceAll(`:${name}`, String(value)), node);
}

export function useT(): (key: string, replace?: Record<string, string | number>) => string {
    const { t } = usePage().props;

    return (key, replace = {}) => translate(t, key, replace);
}
