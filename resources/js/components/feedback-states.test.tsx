import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { ConnectionNotice } from './connection-notice';
import { FlashMessage } from './flash-message';

/**
 * UI-P4: the states between "it worked" and "there is nothing here" — a
 * refusal from the server, a request that never arrived, a collection with no
 * rows yet — each say what happened and what to do next.
 */

let flash: { status?: string | null; error?: string | null } = {};

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    usePage: () => ({ props: { flash } }),
}));

describe('FlashMessage', () => {
    beforeEach(() => {
        flash = {};
    });

    it('shows a refusal as an error, not as a confirmation', () => {
        flash = { error: 'Your session expired — please try again.' };
        render(<FlashMessage />);

        const alert = screen.getByRole('alert');
        expect(alert).toHaveTextContent('Your session expired — please try again.');
        expect(alert.className).toContain('text-destructive');
    });

    it('shows an error and a confirmation together, error first', () => {
        flash = { status: 'Saved.', error: 'One row was skipped.' };
        render(<FlashMessage />);

        expect(screen.getAllByRole('alert').map((alert) => alert.textContent)).toEqual(['One row was skipped.', 'Saved.']);
    });

    it('lets a page ask for errors only, so its own status line is not repeated', () => {
        flash = { status: 'Reset link sent.', error: 'Too many attempts.' };
        render(<FlashMessage kinds={['error']} />);

        expect(screen.getAllByRole('alert')).toHaveLength(1);
        expect(screen.queryByText('Reset link sent.')).not.toBeInTheDocument();
    });

    it('dismisses one message without hiding the other', async () => {
        flash = { status: 'Saved.', error: 'One row was skipped.' };
        render(<FlashMessage />);

        await userEvent.click(screen.getAllByRole('button', { name: 'Dismiss message' })[0]);

        expect(screen.queryByText('One row was skipped.')).not.toBeInTheDocument();
        expect(screen.getByText('Saved.')).toBeInTheDocument();
    });
});

function fire(type: 'exception' | 'success') {
    const event = new CustomEvent(`inertia:${type}`, {
        cancelable: true,
        detail: type === 'exception' ? { exception: new Error('Network Error') } : {},
    });
    act(() => {
        document.dispatchEvent(event);
    });

    return event;
}

describe('ConnectionNotice', () => {
    it('stays silent while requests succeed', () => {
        render(<ConnectionNotice />);

        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });

    it('says so when a request never reached the server, and handles the failure', () => {
        render(<ConnectionNotice />);
        const event = fire('exception');

        expect(screen.getByRole('alert')).toHaveTextContent("Couldn't reach Piotrack");
        expect(event.defaultPrevented).toBe(true); // Inertia would otherwise reject the visit unseen
    });

    it('clears once a later request succeeds', () => {
        render(<ConnectionNotice />);
        fire('exception');
        fire('success');

        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });
});

const pages = import.meta.glob('../pages/**/index.tsx', { query: '?raw', import: 'default', eager: true }) as Record<string, string>;

describe('collection pages when empty', () => {
    it('show the designed empty state with a way forward', () => {
        const collections = [
            'advertising/campaigns',
            'advertising/retargeting',
            'content/pieces',
            'content/social',
            'crm/companies',
            'crm/contacts',
            'crm/deals',
            'crm/leads',
            'marketing/automation',
            'marketing/campaigns',
            'marketing/forms',
            'marketing/funnels',
            'marketing/landing-pages',
            'marketing/lists',
            'sales/accounts',
            'sales/alerts',
            'sales/booking',
            'sales/enablement',
            'sales/intent',
            'sales/scoring',
            'seo/keywords',
        ];

        const missing = collections.filter((page) => {
            const source = pages[`../pages/${page}/index.tsx`];

            return source === undefined || !/<EmptyState[\s\S]*?action=/.test(source);
        });

        expect(missing).toEqual([]);
    });
});
