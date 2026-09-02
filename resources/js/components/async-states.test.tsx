import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { ErrorState, LoadingState, PartialFailure } from './async-states';

/**
 * DSGN-009: the async-state standard. Waiting announces itself politely,
 * failure explains and offers a retry that actually retries, and a partial
 * failure names what is missing without hiding what loaded.
 */
describe('async states', () => {
    it('LoadingState announces itself as a live status', () => {
        render(<LoadingState label="Loading deals…" rows={2} />);

        const status = screen.getByRole('status');
        expect(status).toHaveTextContent('Loading deals…');
        expect(status).toHaveAttribute('aria-live', 'polite');
    });

    it('ErrorState explains the failure and retries on demand', async () => {
        const retry = vi.fn();
        render(<ErrorState message="The rank check could not reach the provider." onRetry={retry} />);

        const alert = screen.getByRole('alert');
        expect(alert).toHaveTextContent('Something went wrong');
        expect(alert).toHaveTextContent('The rank check could not reach the provider.');

        await userEvent.click(screen.getByRole('button', { name: /try again/i }));
        expect(retry).toHaveBeenCalledTimes(1);
    });

    it('ErrorState without a retry handler offers no dead button', () => {
        render(<ErrorState message="Nope." />);
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });

    it('PartialFailure names what is missing and keeps the rest of the page honest', () => {
        render(<PartialFailure failed={['Live AI engine data']} detail="Fixture provider in use." />);

        const notice = screen.getByRole('status');
        expect(notice).toHaveTextContent('Some of this page could not load');
        expect(notice).toHaveTextContent('Live AI engine data');
        expect(notice).toHaveTextContent('Everything else on the page is current.');
        expect(notice).toHaveTextContent('Fixture provider in use.');
    });

    it('PartialFailure renders nothing when nothing failed', () => {
        const { container } = render(<PartialFailure failed={[]} />);
        expect(container).toBeEmptyDOMElement();
    });
});
