/**
 * Format a minor-unit amount (cents) as a currency string.
 */
export function formatMoney(amount: number | null | undefined, currency = 'USD'): string {
    if (amount === null || amount === undefined) {
        return '—';
    }

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
        minimumFractionDigits: amount % 100 === 0 ? 0 : 2,
    }).format(amount / 100);
}

/** "1 form", "3 forms" — for the context lines under KPI values. */
export function countOf(count: number, singular: string, plural = `${singular}s`): string {
    return `${count.toLocaleString('en-US')} ${count === 1 ? singular : plural}`;
}

/** Whole-number share, or null when there is no base to divide by. */
export function shareOf(part: number, whole: number): string | null {
    return whole > 0 ? `${Math.round((part / whole) * 100)}%` : null;
}

/** A flow counted in the current window and the one before it (server: PeriodComparison). */
export type Compared = { value: number; previous: number; delta_pct: number | null };

export type Delta = { value: string; direction: 'up' | 'down' | 'neutral' };

/** "+18%" against the previous window; a null previous means "new", not +∞. */
export function formatDelta(c: Compared): Delta | undefined {
    if (c.delta_pct === null) {
        return c.value > 0 ? { value: 'new', direction: 'neutral' } : undefined;
    }

    return {
        value: `${c.delta_pct > 0 ? '+' : ''}${c.delta_pct}%`,
        direction: c.delta_pct > 0 ? 'up' : c.delta_pct < 0 ? 'down' : 'neutral',
    };
}

/**
 * Human label for an entitlement/limit key.
 */
export function humanizeKey(key: string): string {
    return key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}
