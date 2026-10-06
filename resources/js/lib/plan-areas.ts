/**
 * Which parts of the app the workspace's plan includes, by where they live:
 * `{ '/chat': true, '/ads': false, … }`. The server builds it from the same
 * feature gates that refuse a page (PlanAreas), so a menu can mark what the
 * plan leaves out before anyone clicks into a refusal.
 */
export type PlanAreas = Record<string, boolean>;

/**
 * A test for "the plan does not include this page".
 *
 * The longest area a page sits under decides, as it does on the server:
 * `/ai/visibility` follows its own entry rather than `/ai`, so a plan with AI
 * visibility but no AI assistant marks one and not the other. A page under no
 * area - the dashboard, billing, a profile - is never marked.
 */
export function planLocks(areas: PlanAreas | null | undefined): (url: string) => boolean {
    const prefixes = Object.keys(areas ?? {}).sort((a, b) => b.length - a.length);

    return (url) => {
        const path = url.split(/[?#]/)[0];
        const area = prefixes.find((prefix) => path === prefix || path.startsWith(`${prefix}/`));

        return area !== undefined && areas![area] === false;
    };
}

/** What a marked item says to someone who cannot see the lock. */
export const NOT_IN_PLAN = 'not in your plan';
