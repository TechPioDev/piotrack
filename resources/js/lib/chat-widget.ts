/** One entry per line in a textarea; the API wants a trimmed array. */
function lines(value: unknown): string[] {
    return String(value ?? '')
        .split('\n')
        .map((entry) => entry.trim())
        .filter(Boolean);
}

/**
 * The payload the widget API expects: page rules, allowed domains and suggested
 * questions as arrays rather than the newline text the textareas hold.
 *
 * It matters that this is ONE function applied with a single `transform`. The
 * page used two transform calls, and Inertia's transform replaces the previous
 * callback rather than chaining, so the first call's list conversions were
 * dropped: the server refused the whole save as invalid, and — with no errors
 * rendered on that page — every appearance change looked simply ignored and came
 * back as defaults on the next load.
 */
export function widgetPayload<T extends Record<string, unknown>>(data: T): T {
    const targeting = (data.targeting ?? {}) as Record<string, unknown>;
    const settings = (data.settings ?? {}) as Record<string, unknown>;

    return {
        ...data,
        allowed_domains: lines(data.allowed_domains),
        targeting: { ...targeting, include: lines(targeting.include), exclude: lines(targeting.exclude) },
        settings: { ...settings, suggested_questions: lines(settings.suggested_questions).slice(0, 6) },
    };
}
