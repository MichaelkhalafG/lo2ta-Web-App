/**
 * Settings keep shares in basis points (100 = 1%); staff type percentages. "12.5" ⇄ 1250. A value that is not a
 * number stays as typed, so the server's check names the field instead of a silent zero.
 */
export function bpToPercent(bp: number | null | undefined): string {
    if (bp === null || bp === undefined) {
        return '';
    }

    return String(Math.round(bp) / 100);
}

export function percentToBp(text: string): number | string | null {
    const clean = text.trim().replace('٫', '.').replace(',', '.');
    if (clean === '') {
        return null;
    }
    const value = Number(clean);

    return Number.isFinite(value) ? Math.round(value * 100) : text;
}

/** A whole number typed in a field, or the text as typed when it is not one (the server then refuses it). */
export function wholeNumber(text: string): number | string | null {
    const clean = text.trim().replace(/[,\s]/g, '');
    if (clean === '') {
        return null;
    }

    return /^-?\d+$/.test(clean) ? Number(clean) : text;
}
