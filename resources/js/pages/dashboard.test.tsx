import Dashboard from '@/pages/dashboard';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

/**
 * The command center must render even when the payload is incomplete. A blank
 * white page (the component throwing on a missing prop) is far worse than a
 * page of zeros, so the component normalises everything it receives.
 */

vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children }: { children: React.ReactNode }) => <a>{children}</a>,
    router: {},
    usePage: () => ({ props: {} }),
}));
vi.mock('@/components/onboarding-checklist', () => ({ OnboardingChecklist: () => <div>onboarding</div> }));

// Ziggy's route() is provided globally by the app shell; the top-deals links use it.
vi.stubGlobal('route', (name: string, id?: number) => `/${name.replace(/\./g, '/')}/${id ?? ''}`);

const fullKpis = {
    new_leads: { value: 12, previous: 10, delta_pct: 20 },
    meetings: { value: 3, previous: 0, delta_pct: null },
    deals_won: { value: 2, previous: 4, delta_pct: -50 },
    new_mrr: { value: 450000, previous: 0, delta_pct: null },
    qualified_pipeline: 480000,
    sqls: 5,
    arr: 5400000,
};

describe('Dashboard', () => {
    it('renders the KPI grid with deltas from a complete payload', () => {
        render(
            <Dashboard
                onboarding={{ steps: [], complete: true }}
                kpis={fullKpis}
                growthScore={{ overall: 72, recommendations: [{ area: 'seo', score: 40, action: 'Track more keywords.' }], history: [] }}
                funnel={[{ label: 'Leads', value: 12 }]}
                channels={[{ label: 'Website', value: 5400000 }]}
                attention={{ alerts: [], waiting_chats: 2, hot_leads: 1 }}
                topDeals={[{ id: 1, name: 'Managed Cybersecurity + CMMC', stage: 'Proposal', value: 5400000 }]}
                sources={{ google: 5, referral: 3 }}
            />,
        );

        expect(screen.getByRole('heading', { name: 'Dashboard' })).toBeInTheDocument();
        expect(screen.getByText('New Leads')).toBeInTheDocument();
        // "12" appears in the KPI card and again in the funnel bars.
        expect(screen.getAllByText('12').length).toBeGreaterThan(0);
        // Period deltas: +20% up, -50% down, and "new" when the previous window was zero.
        expect(screen.getByText('+20%')).toBeInTheDocument();
        expect(screen.getByText('-50%')).toBeInTheDocument();
        expect(screen.getAllByText('new').length).toBeGreaterThan(0);
        // Growth score with its band, and attention items.
        expect(screen.getByText('72')).toBeInTheDocument();
        expect(screen.getByText('Healthy')).toBeInTheDocument();
        expect(screen.getByText(/2 chat visitors waiting/)).toBeInTheDocument();
        expect(screen.getByText(/1 hot lead ready/)).toBeInTheDocument();
        expect(screen.getByText('Managed Cybersecurity + CMMC')).toBeInTheDocument();
    });

    it('renders without throwing when every prop is absent', () => {
        // The exact shape an older backend or empty tenant can send.
        expect(() => render(<Dashboard />)).not.toThrow();
        expect(screen.getByRole('heading', { name: 'Dashboard' })).toBeInTheDocument();
        // Missing numbers fall back to zero rather than crashing.
        expect(screen.getAllByText('0').length).toBeGreaterThan(0);
        expect(screen.getByText('All clear — nothing is waiting on you.')).toBeInTheDocument();
    });
});
