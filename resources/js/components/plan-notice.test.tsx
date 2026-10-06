import { PlanNotice } from '@/components/plan-notice';
import { type PlanNotice as Notice } from '@/lib/access-denied';
import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * The line that tells a workspace its plan is no longer running, before a page
 * has to be refused for anyone to find out.
 */

const { page } = vi.hoisted(() => ({ page: { url: '/dashboard', props: { planNotice: null as unknown } } }));

vi.mock('@inertiajs/react', () => ({
    usePage: () => page,
    Link: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
}));

const ended = (over: Partial<Notice> = {}): Notice => ({
    workspace: 'PioManage',
    state: 'trial_ended',
    plan: 'Growth',
    ended_on: '2026-10-06',
    can_manage_billing: true,
    ...over,
});

beforeEach(() => {
    sessionStorage.clear();
    page.url = '/dashboard';
    page.props.planNotice = null;
});

describe('PlanNotice', () => {
    it('says nothing while a plan is running', () => {
        const { container } = render(<PlanNotice />);

        expect(container).toBeEmptyDOMElement();
    });

    it('tells an owner the trial ended, that nothing is lost, and where to go', () => {
        page.props.planNotice = ended();
        render(<PlanNotice />);

        const notice = screen.getByRole('status');
        expect(notice).toHaveTextContent('The free trial for PioManage ended on');
        expect(notice).toHaveTextContent('Most of Piotrack is switched off until a plan is chosen');
        expect(notice).toHaveTextContent('nothing has been deleted');
        expect(screen.getByRole('link', { name: 'See plans' })).toHaveAttribute('href', '/billing/plans');
    });

    it('sends someone who cannot change the plan to an owner, with nothing to click', () => {
        page.props.planNotice = ended({ can_manage_billing: false });
        render(<PlanNotice />);

        expect(screen.getByRole('status')).toHaveTextContent('Ask an owner of the workspace to choose a plan.');
        expect(screen.queryByRole('link')).toBeNull();
    });

    it('points a subscription on hold at billing, not at the plans', () => {
        page.props.planNotice = ended({ state: 'suspended', ended_on: null });
        render(<PlanNotice />);

        expect(screen.getByRole('status')).toHaveTextContent('is on hold, usually because a payment did not go through');
        expect(screen.getByRole('link', { name: 'Open billing' })).toHaveAttribute('href', '/billing');
    });

    it('does not offer a link to the page it is already on', () => {
        page.url = '/billing/plans?interval=yearly';
        page.props.planNotice = ended();
        render(<PlanNotice />);

        expect(screen.getByRole('status')).toBeInTheDocument();
        expect(screen.queryByRole('link')).toBeNull();
    });

    it('can be put away for the visit, and comes back when the situation changes', () => {
        page.props.planNotice = ended();
        const first = render(<PlanNotice />);
        fireEvent.click(screen.getByRole('button', { name: 'Hide this notice for now' }));
        expect(screen.queryByRole('status')).toBeNull();
        first.unmount();

        // The next page in the same visit stays quiet...
        const second = render(<PlanNotice />);
        expect(screen.queryByRole('status')).toBeNull();
        second.unmount();

        // ...but different news is shown again.
        page.props.planNotice = ended({ state: 'suspended', ended_on: null });
        render(<PlanNotice />);
        expect(screen.getByRole('status')).toBeInTheDocument();
    });
});
