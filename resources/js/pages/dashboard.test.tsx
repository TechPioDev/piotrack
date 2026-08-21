import Dashboard from '@/pages/dashboard';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

/**
 * The dashboard must render even when the payload is incomplete. A blank white
 * page (the page component throwing on `metrics.leads` when metrics is absent)
 * is far worse than a page of zeros, so the component normalises its props.
 */

vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children }: { children: React.ReactNode }) => <a>{children}</a>,
    router: {},
    usePage: () => ({ props: {} }),
}));
vi.mock('@/components/onboarding-checklist', () => ({ OnboardingChecklist: () => <div>onboarding</div> }));

const fullMetrics = {
    leads: 12,
    sqls: 5,
    meetings: 3,
    opportunities: 4,
    qualified_pipeline: 480000,
    closed_won: 2,
    mrr: 31400,
    arr: 376800,
};

describe('Dashboard', () => {
    it('renders the KPI grid from a complete payload', () => {
        render(<Dashboard onboarding={{ steps: [], complete: true }} metrics={fullMetrics} sources={{ google: 5, referral: 3 }} />);

        expect(screen.getByRole('heading', { name: 'Dashboard' })).toBeInTheDocument();
        expect(screen.getByText('New Leads')).toBeInTheDocument();
        expect(screen.getByText('12')).toBeInTheDocument();
    });

    it('renders without throwing when metrics, sources and onboarding are absent', () => {
        // The exact shape an older backend or empty tenant can send.
        expect(() => render(<Dashboard />)).not.toThrow();
        expect(screen.getByRole('heading', { name: 'Dashboard' })).toBeInTheDocument();
        // Missing numeric metrics fall back to zero rather than crashing.
        expect(screen.getAllByText('0').length).toBeGreaterThan(0);
    });
});
