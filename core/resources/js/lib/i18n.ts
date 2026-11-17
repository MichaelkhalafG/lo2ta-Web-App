import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';

import type { Locale, Strings } from '@/types/global';

type Values = Record<string, string | number>;
export type Translate = (key: string, fallback?: string | Values, values?: Values) => string;

/**
 * Substitute Laravel-style `:name` placeholders, longest name first so `:to` never eats the start of `:total`.
 */
function interpolate(text: string, values: Values): string {
    return Object.entries(values)
        .sort(([a], [b]) => b.length - a.length)
        .reduce((carry, [name, value]) => carry.split(`:${name}`).join(String(value)), text);
}

function lookup(strings: Strings, key: string): string | null {
    let node: string | Strings | undefined = strings;
    for (const part of key.split('.')) {
        if (typeof node !== 'object') {
            return null;
        }
        node = node[part];
    }

    return typeof node === 'string' ? node : null;
}

/**
 * Translate a dotted key from the shared dashboard strings (lang/{locale}/dashboard.php).
 *
 * `t(key)`, `t(key, values)` or `t(key, fallback, values)`. Both languages live in the lang files and
 * tests/Feature/Dashboard/TranslationCoverageTest.php fails when a key used here is missing from either; the
 * fallback only keeps a screen readable while a key is being added. A missing key with no fallback shows the key.
 */
export function translate(strings: Strings, key: string, fallback?: string | Values, values?: Values): string {
    const replace = typeof fallback === 'object' ? fallback : values;
    const text = lookup(strings, key) ?? (typeof fallback === 'string' ? fallback : key);

    return replace === undefined ? text : interpolate(text, replace);
}

export function useT(): Translate {
    const { t } = usePage().props;

    return useMemo<Translate>(() => (key, fallback, values) => translate(t, key, fallback, values), [t]);
}

export function useLocale(): Locale {
    return usePage().props.locale;
}
