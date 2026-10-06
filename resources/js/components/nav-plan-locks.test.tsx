import { AppSidebar } from '@/components/app-sidebar';
import { SidebarProvider } from '@/components/ui/sidebar';
import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * The sidebar marks what the workspace's plan leaves out (ENTL-012).
 *
 * The menu used to list every module the same way, so the only way to learn a
 * plan did not include one was to click it and be refused. A page left out
 * stays in the menu - hiding it would hide what an upgrade buys - and carries
 * a lock that a screen reader hears as "(not in your plan)".
 */

const { page } = vi.hoisted(() => ({
    page: {
        url: '/dashboard',
        props: {
            auth: {
                user: { id: 1, name: 'Dana Whitfield', email: 'demo@piotrack.test' },
                currentOrganization: { id: 1, name: 'Acme', slug: 'acme' },
                organizations: [{ id: 1, name: 'Acme', slug: 'acme', role: 'owner' }],
                permissions: ['crm.contact.read', 'crm.company.read', 'crm.lead.read', 'crm.deal.read', 'marketing.view'],
                role: 'owner',
            },
            entitlements: { features: {}, plan: null as string | null, areas: {} as Record<string, boolean> },
        },
    },
}));

vi.mock('@inertiajs/react', () => ({
    usePage: () => page,
    Link: ({ href, children, prefetch, ...rest }: { href: string; children: React.ReactNode; prefetch?: boolean }) => (
        <a href={href} data-prefetch={String(Boolean(prefetch))} {...rest}>
            {children}
        </a>
    ),
    router: { post: vi.fn(), get: vi.fn() },
}));

function renderSidebar(url: string, areas: Record<string, boolean>) {
    page.url = url;
    page.props.entitlements.areas = areas;

    return render(
        <SidebarProvider>
            <AppSidebar />
        </SidebarProvider>,
    );
}

const locks = (container: HTMLElement) => container.querySelectorAll('[data-plan-lock]').length;

beforeEach(() => {
    page.url = '/dashboard';
    page.props.entitlements.areas = {};
});

describe('AppSidebar, with a plan that leaves things out', () => {
    it('marks nothing when the plan includes everything', () => {
        const { container } = renderSidebar('/marketing', { '/crm': true, '/marketing': true, '/marketing/automation': true });

        expect(locks(container)).toBe(0);
        expect(screen.getByRole('link', { name: 'Automation' })).toBeInTheDocument();
    });

    it('marks one page inside a section the plan otherwise includes', () => {
        const { container } = renderSidebar('/marketing', { '/crm': true, '/marketing': true, '/marketing/automation': false });

        // The page is still there, still a link to itself, and says why it is marked.
        const automation = screen.getByRole('link', { name: 'Automation (not in your plan)' });
        expect(automation).toHaveAttribute('href', '/marketing/automation');
        // No point warming up a page that will be refused.
        expect(automation).toHaveAttribute('data-prefetch', 'false');

        // Its neighbours are untouched, and the section header carries no lock.
        expect(screen.getByRole('link', { name: 'Campaigns' })).toHaveAttribute('data-prefetch', 'true');
        expect(screen.getByRole('button', { name: 'Marketing' })).toBeInTheDocument();
        expect(locks(container)).toBe(1);
    });

    it('marks the section itself when the plan leaves all of it out, so nobody has to open it to find out', () => {
        renderSidebar('/dashboard', { '/crm': true, '/marketing': false, '/marketing/automation': false });

        // Closed, and already telling: the header says it.
        expect(screen.getByRole('button', { name: 'Marketing (not in your plan)' })).toBeInTheDocument();
        expect(screen.queryByRole('link', { name: /Campaigns/ })).not.toBeInTheDocument();

        // CRM is in every plan and is not marked.
        expect(screen.getByRole('button', { name: 'CRM' })).toBeInTheDocument();
    });

    it('marks every page of such a section once it is open', () => {
        renderSidebar('/marketing/lists', { '/crm': true, '/marketing': false, '/marketing/automation': false });

        for (const title of ['Overview', 'Lists', 'Forms', 'Campaigns', 'Automation', 'Funnels']) {
            expect(screen.getByRole('link', { name: `${title} (not in your plan)` })).toBeInTheDocument();
        }
    });
});
