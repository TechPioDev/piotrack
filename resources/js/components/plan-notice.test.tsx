import { PlanNotice } from '@/components/plan-notice';
import { type PlanNotice as Notice } from '@/lib/access-denied';
import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

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

describe('PlanNotice, while a trial counts down', () => {
    /** A trial that runs out at 09:00, `days` days after "now" (10:00 on 6 October, the reader's time). */
    const ending = (days: number, over: Partial<Notice> = {}): Notice =>
        ended({ state: 'trial_ending', ended_on: null, ends_at: new Date(2026, 9, 6 + days, 9, 0).toISOString(), ...over });

    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date(2026, 9, 6, 10, 0));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('says how many days are left, the date, and what to do about it', () => {
        page.props.planNotice = ending(3);
        render(<PlanNotice />);

        const notice = screen.getByRole('status');
        expect(notice).toHaveTextContent('The free trial for PioManage ends in 3 days, on');
        expect(notice).toHaveTextContent('2026');
        expect(notice).toHaveTextContent('Choose a plan to keep everything switched on.');
        // Nothing is switched off yet, so it must not say so.
        expect(notice).not.toHaveTextContent('switched off');
        expect(screen.getByRole('link', { name: 'See plans' })).toHaveAttribute('href', '/billing/plans');
    });

    it('says tomorrow and today in plain words', () => {
        page.props.planNotice = ending(1);
        const first = render(<PlanNotice />);
        expect(screen.getByRole('status')).toHaveTextContent('ends tomorrow,');
        first.unmount();

        // It runs out at eleven tonight.
        page.props.planNotice = ended({ state: 'trial_ending', ended_on: null, ends_at: new Date(2026, 9, 6, 23, 0).toISOString() });
        render(<PlanNotice />);
        expect(screen.getByRole('status')).toHaveTextContent('The free trial for PioManage ends today.');
    });

    it('asks a teammate to get an owner to choose, with nothing to click', () => {
        page.props.planNotice = ending(2, { can_manage_billing: false });
        render(<PlanNotice />);

        expect(screen.getByRole('status')).toHaveTextContent('Ask an owner of the workspace to choose a plan, to keep everything switched on.');
        expect(screen.queryByRole('link')).toBeNull();
    });

    it('put away with three days left, it is back the next day', () => {
        page.props.planNotice = ending(3);
        const first = render(<PlanNotice />);
        fireEvent.click(screen.getByRole('button', { name: 'Hide this notice for now' }));
        first.unmount();

        const sameDay = render(<PlanNotice />);
        expect(screen.queryByRole('status')).toBeNull();
        sameDay.unmount();

        vi.setSystemTime(new Date(2026, 9, 7, 8, 0));
        render(<PlanNotice />);
        expect(screen.getByRole('status')).toHaveTextContent('ends in 2 days');
    });
});
