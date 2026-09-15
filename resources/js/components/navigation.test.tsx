import { CommandPalette, shortcutLabel } from '@/components/command-palette';
import { DASHBOARD, navigablePages, navigationSections, searchPages, withSection } from '@/lib/navigation';
import { type BreadcrumbItem } from '@/types';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * UI-P5: a page is findable by name, every header says which section it is in,
 * and nothing in the navigation looks like something else.
 */

const everything = () => true;

const { page, visit } = vi.hoisted(() => ({
    page: { url: '/dashboard', props: { auth: { permissions: [] as string[] } } },
    visit: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({
    usePage: () => page,
    router: { visit },
}));

describe('the navigation definition', () => {
    const sections = navigationSections(everything);
    const items = [DASHBOARD, ...sections.flatMap((section) => section.items)];

    it('gives every sidebar link its own icon, because the collapsed rail shows only icons', () => {
        const icons = items.map((item) => item.icon);

        expect(new Set(icons).size).toBe(icons.length);
    });

    it('links each page from exactly one place', () => {
        const urls = navigablePages(everything).map(({ item }) => item.url);

        expect(new Set(urls).size).toBe(urls.length);
    });

    it('never repeats a name inside a section, and repeats "Overview" only across sections', () => {
        for (const section of sections) {
            const titles = section.items.map((item) => item.title);
            expect(new Set(titles).size).toBe(titles.length);
        }

        const repeated = Object.entries(
            items.reduce<Record<string, number>>((counts, item) => ({ ...counts, [item.title]: (counts[item.title] ?? 0) + 1 }), {}),
        ).filter(([, count]) => count > 1);
        expect(repeated.map(([title]) => title).sort()).toEqual(['Campaigns', 'Conversations', 'Overview']);
    });

    it('hides pages the user may not open', () => {
        const pages = navigablePages((permission) => permission === 'seo.view').map(({ item }) => item.url);

        expect(pages).toContain('/seo/keywords');
        expect(pages).not.toContain('/crm/deals');
        expect(pages).not.toContain('/billing');
    });
});

describe('finding a page by name', () => {
    const pages = navigablePages(everything);
    const top = (query: string) => searchPages(query, pages).map(({ section, item }) => `${section} › ${item.title}`);

    it('finds the pages the measurement could not reach through search', () => {
        expect(top('keywords')[0]).toBe('SEO › Keywords');
        expect(top('billing')[0]).toBe('Settings › Billing');
        expect(top('attribution')[0]).toBe('Analytics › Attribution');
        expect(top('members')[0]).toBe('Settings › Members');
        expect(top('deals')[0]).toBe('CRM › Deals');
        expect(top('booking')[0]).toBe('Sales › Booking');
    });

    it('matches the words people use for a page, and section plus page together', () => {
        expect(top('pipeline')).toContain('CRM › Deals');
        expect(top('invoices')).toContain('Settings › Billing');
        expect(top('seo key')).toEqual(['SEO › Keywords']);
        expect(top('ads camp')[0]).toBe('Advertising › Campaigns');
    });

    it('matches from the start of words, so "ads" does not find Leads', () => {
        expect(top('ads')).not.toContain('CRM › Leads');
        expect(top('zzz')).toEqual([]);
        expect(top('   ')).toEqual([]);
    });
});

describe('section breadcrumbs', () => {
    const trail = (crumbs: BreadcrumbItem[], path: string) =>
        withSection(crumbs, path, everything)
            .map((crumb) => crumb.title)
            .join(' › ');

    it('names the section in front of the page', () => {
        expect(trail([{ title: 'Ad campaigns', href: '/ads/campaigns' }], '/ads/campaigns')).toBe('Advertising › Ad campaigns');
        expect(
            trail(
                [
                    { title: 'Contacts', href: '/crm/contacts' },
                    { title: 'Ann Lee', href: '/crm/contacts/5' },
                ],
                '/crm/contacts/5',
            ),
        ).toBe('CRM › Contacts › Ann Lee');
        expect(trail([{ title: 'Members', href: '/settings/members' }], '/settings/members')).toBe('Settings › Members');
    });

    it('drops the section word a page already repeated', () => {
        expect(trail([{ title: 'AI Agent', href: '/ai/agent' }], '/ai/agent')).toBe('AI › Agent');
        expect(trail([{ title: 'SEO Audits', href: '/seo/audits' }], '/seo/audits')).toBe('SEO › Audits');
    });

    it('leaves a section overview, an already-sectioned trail and the dashboard alone', () => {
        expect(trail([{ title: 'SEO', href: '/seo' }], '/seo')).toBe('SEO');
        expect(
            trail(
                [
                    { title: 'SEO', href: '/seo' },
                    { title: 'Links', href: '/seo/links' },
                ],
                '/seo/links',
            ),
        ).toBe('SEO › Links');
        expect(trail([{ title: 'Dashboard', href: '/dashboard' }], '/dashboard')).toBe('Dashboard');
    });

    it('gives every page in the app a header no other page shares', () => {
        const sources = import.meta.glob('../pages/**/*.tsx', { query: '?raw', import: 'default', eager: true }) as Record<string, string>;
        const trails = new Map<string, string>();
        const collisions: string[] = [];

        for (const source of Object.values(sources)) {
            const match = source.match(/const breadcrumbs: BreadcrumbItem\[\] = \[\{ title: '([^']+)', href: '([^']+)' \}\];/);
            if (!match) continue;

            const [, title, href] = match;
            const header = trail([{ title, href }], href);
            const owner = trails.get(header);
            if (owner !== undefined && owner !== href) collisions.push(`${header}: ${owner} and ${href}`);
            trails.set(header, href);
        }

        expect(trails.size).toBeGreaterThan(40);
        expect(collisions).toEqual([]);
    });
});

describe('CommandPalette', () => {
    beforeEach(() => {
        page.props.auth.permissions = ['seo.view', 'crm.deal.read'];
        vi.stubGlobal('route', () => '/search');
        vi.stubGlobal(
            'fetch',
            vi.fn(() => Promise.resolve({ json: () => Promise.resolve({ groups: [] }) })),
        );
        localStorage.clear();
    });

    it('shows the shortcut people actually press on their platform', () => {
        expect(shortcutLabel('Mozilla/5.0 (Windows NT 10.0; Win64; x64)')).toBe('Ctrl K');
        expect(shortcutLabel('Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5)')).toBe('⌘K');
    });

    it('goes to a page by name with the keyboard alone', async () => {
        render(<CommandPalette />);
        await userEvent.click(screen.getByRole('button', { name: 'Search pages and records' }));
        await userEvent.type(screen.getByRole('combobox'), 'keyw');

        const option = screen.getByRole('option', { name: /Keywords/ });
        expect(option).toHaveTextContent('SEO');
        expect(option).toHaveAttribute('aria-selected', 'true');

        await userEvent.keyboard('{Enter}');
        expect(visit).toHaveBeenCalledWith('/seo/keywords');
    });

    it('moves the highlight with the arrow keys', async () => {
        render(<CommandPalette />);
        await userEvent.click(screen.getByRole('button', { name: 'Search pages and records' }));
        await userEvent.type(screen.getByRole('combobox'), 'seo');

        const options = screen.getAllByRole('option');
        expect(options.length).toBeGreaterThan(1);

        await userEvent.keyboard('{ArrowDown}');
        expect(screen.getAllByRole('option')[1]).toHaveAttribute('aria-selected', 'true');
        await userEvent.keyboard('{ArrowUp}{ArrowUp}');
        expect(screen.getAllByRole('option').at(-1)).toHaveAttribute('aria-selected', 'true');
    });

    it('never offers a page the user cannot open', async () => {
        render(<CommandPalette />);
        await userEvent.click(screen.getByRole('button', { name: 'Search pages and records' }));
        await userEvent.type(screen.getByRole('combobox'), 'billing');

        expect(screen.queryByRole('option', { name: /Billing/ })).not.toBeInTheDocument();
    });
});
