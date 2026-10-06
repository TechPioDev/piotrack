import { navigablePages, navigationSections, settingsItems } from '@/lib/navigation';
import { planLocks } from '@/lib/plan-areas';
import { describe, expect, it } from 'vitest';

/** A Growth-like plan: chat and SEO in; advertising, the AI assistant, AI visibility and the audit log out. */
const growth = {
    '/crm': true,
    '/chat': true,
    '/seo': true,
    '/seo/ai-visibility': false,
    '/seo/llmo': false,
    '/ads': false,
    '/ai': false,
    '/ai/visibility': true,
    '/settings/teams': true,
    '/settings/audit-log': false,
};

const everything = () => true;

describe('planLocks', () => {
    const locked = planLocks(growth);

    it('marks a page in an area the plan leaves out, and every page under it', () => {
        expect(locked('/ads')).toBe(true);
        expect(locked('/ads/campaigns')).toBe(true);
        expect(locked('/chat/widgets')).toBe(false);
    });

    it('lets the most specific area decide', () => {
        // SEO is in the plan; two pages under it need more than SEO.
        expect(locked('/seo/keywords')).toBe(false);
        expect(locked('/seo/llmo')).toBe(true);
        // The AI assistant is out, AI visibility - which lives under /ai - is in.
        expect(locked('/ai/agent')).toBe(true);
        expect(locked('/ai/visibility')).toBe(false);
    });

    it('treats an area as a path, not a run of letters', () => {
        expect(locked('/adsense')).toBe(false);
        expect(locked('/ai-tools')).toBe(false);
    });

    it('ignores a query string or fragment', () => {
        expect(locked('/ads?range=30d')).toBe(true);
        expect(locked('/chat/widgets#embed')).toBe(false);
    });

    it('never marks a page that sits under no area', () => {
        expect(locked('/dashboard')).toBe(false);
        expect(locked('/billing/plans')).toBe(false);
        expect(locked('/settings/profile')).toBe(false);
    });

    it('marks nothing when it has been told nothing', () => {
        expect(planLocks(undefined)('/ads')).toBe(false);
        expect(planLocks({})('/ads')).toBe(false);
    });
});

describe('navigation, with a plan that leaves things out', () => {
    const locked = planLocks(growth);
    const titles = (items: { title: string; locked?: boolean }[]) => items.filter((item) => item.locked).map((item) => item.title);

    it('keeps every page in the menu and marks the ones the plan leaves out', () => {
        const plain = navigationSections(everything);
        const marked = navigationSections(everything, locked);

        // Nothing is hidden: the same sections with the same pages.
        expect(marked.map((section) => section.items.map((item) => item.url))).toEqual(plain.map((section) => section.items.map((item) => item.url)));

        const ads = marked.find((section) => section.items.some((item) => item.url.startsWith('/ads')));
        expect(ads?.items.every((item) => item.locked)).toBe(true);

        const chat = marked.find((section) => section.items.some((item) => item.url.startsWith('/chat')));
        expect(chat?.items.some((item) => item.locked)).toBe(false);
    });

    it('marks no page at all unless it is asked to', () => {
        expect(navigationSections(everything).flatMap((section) => titles(section.items))).toEqual([]);
        expect(titles(settingsItems(everything))).toEqual([]);
    });

    it('marks the settings pages a plan leaves out', () => {
        expect(titles(settingsItems(everything, locked))).toEqual(['Audit log']);
    });

    it('carries the mark into everything the command palette can find', () => {
        const pages = navigablePages(everything, locked);

        expect(pages.find(({ item }) => item.url === '/settings/audit-log')?.item.locked).toBe(true);
        expect(pages.find(({ item }) => item.url === '/dashboard')?.item.locked).toBeUndefined();
        expect(pages.filter(({ item }) => item.locked).length).toBeGreaterThan(3);
    });
});
