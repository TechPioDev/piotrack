/**
 * Page rules for the chat widget (§34): which pages it appears on.
 *
 * A rule is a path, optionally followed by link settings after a "?":
 *   /cybersecurity           that page and everything under it
 *   /services/*              * matches anything
 *   ?utm_source=google       any page opened from a link with that setting
 *   /pricing?ref=partner*    one page, and only from such a link
 * Every setting in a rule must be present; a value of * or no value at all
 * only requires the setting to be there. Values ignore case, since campaign
 * tags are typed by hand and "Google" and "google" mean the same campaign.
 */

function wildcard(pattern: string, flags = ''): RegExp {
    // Escape everything except the wildcard, then let * mean "anything".
    const escaped = pattern
        .split('*')
        .map((part) => part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
        .join('.*');
    return new RegExp('^' + escaped + '$', flags);
}

function pathMatches(pattern: string, path: string): boolean {
    if (pattern === '') return true;
    if (pattern.includes('*')) return wildcard(pattern).test(path);
    return path === pattern || path.startsWith(pattern.endsWith('/') ? pattern : pattern + '/');
}

function settingsMatch(wanted: string, search: string): boolean {
    const actual = new URLSearchParams(search);
    for (const [name, value] of new URLSearchParams(wanted)) {
        const present = actual.getAll(name);
        if (present.length === 0) return false;
        if (value === '' || value === '*') continue;
        const expected = wildcard(value, 'i');
        if (!present.some((candidate) => expected.test(candidate))) return false;
    }
    return true;
}

/** Whether any of the rules matches the page at `path` opened with `search` (e.g. "?utm_source=google"). */
export function pageMatches(rules: string[], path: string, search = ''): boolean {
    return rules.some((raw) => {
        const rule = raw.trim();
        if (!rule) return false;
        const at = rule.indexOf('?');
        if (at === -1) return pathMatches(rule, path);
        return pathMatches(rule.slice(0, at), path) && settingsMatch(rule.slice(at + 1), search);
    });
}
