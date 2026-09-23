import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { TestDialog } from './test-dialog';

/**
 * Testing a draft conversation: what the tester just answered, and what the bot
 * said back, must be on screen without reaching for the scrollbar - and the box
 * must be ready for the next answer.
 */

const scrollTo = vi.fn();

beforeAll(() => {
    (globalThis as unknown as { route: (name: string) => string }).route = (name: string) => `/${name}`;
    Element.prototype.scrollTo = scrollTo as unknown as Element['scrollTo'];
    window.matchMedia = ((query: string) =>
        ({
            matches: false,
            media: query,
            addEventListener: () => {},
            removeEventListener: () => {},
        }) as unknown as MediaQueryList) as typeof window.matchMedia;
});

/** The engine's answers, one exchange at a time. */
function engineSays(replies: { messages: { role: string; body: string }[]; node: unknown; done?: boolean }[]) {
    let turn = 0;
    globalThis.fetch = vi.fn(async () => {
        const reply = replies[Math.min(turn, replies.length - 1)];
        turn += 1;

        return new Response(JSON.stringify({ token: 'tok', ...reply }));
    }) as typeof fetch;
}

const asking = (text: string) => ({ id: text, type: 'input', text, input: 'text' });

beforeEach(() => {
    scrollTo.mockClear();
});

describe('test conversation', () => {
    it('follows the newest reply down, and leaves the cursor ready for the next answer', async () => {
        const user = userEvent.setup();
        engineSays([
            { messages: [{ role: 'bot', body: 'What is your first name?' }], node: asking('What is your first name?') },
            { messages: [{ role: 'bot', body: 'And your last name?' }], node: asking('And your last name?') },
        ]);

        render(<TestDialog widgetId={7} flow={{ start: null, nodes: {} }} open={true} onOpenChange={() => {}} />);

        const box = await screen.findByRole('textbox', { name: 'What is your first name?' });
        await waitFor(() => expect(scrollTo).toHaveBeenCalled());
        expect(box).toHaveFocus();

        scrollTo.mockClear();
        await user.type(box, 'Ram{Enter}');

        // The answer and the question after it are both at the bottom of the
        // transcript, which the transcript scrolled to on its own.
        expect(await screen.findByText('Ram')).toBeInTheDocument();
        await waitFor(() => expect(scrollTo).toHaveBeenCalled());
        expect(scrollTo.mock.calls.at(-1)?.[0]).toMatchObject({ behavior: 'smooth' });
        await waitFor(() => expect(screen.getByRole('textbox', { name: 'And your last name?' })).toHaveFocus());
    });

    it('stops following while the tester reads back, and offers a way to catch up', async () => {
        const user = userEvent.setup();
        engineSays([
            { messages: [{ role: 'bot', body: 'What is your first name?' }], node: asking('What is your first name?') },
            { messages: [{ role: 'bot', body: 'And your last name?' }], node: asking('And your last name?') },
        ]);

        render(<TestDialog widgetId={7} flow={{ start: null, nodes: {} }} open={true} onOpenChange={() => {}} />);
        const box = await screen.findByRole('textbox', { name: 'What is your first name?' });

        // Scrolled up to re-read. jsdom lays nothing out, so the geometry that
        // makes "not at the bottom" true is set by hand.
        const log = document.querySelector('.overflow-y-auto') as HTMLElement;
        Object.defineProperty(log, 'scrollHeight', { value: 600, configurable: true });
        Object.defineProperty(log, 'clientHeight', { value: 200, configurable: true });
        log.scrollTop = 0;
        log.dispatchEvent(new Event('scroll', { bubbles: true }));

        scrollTo.mockClear();
        await user.type(box, 'Ram{Enter}');

        // Nothing jumps; a button appears instead, and using it follows again.
        await screen.findByText('Ram');
        expect(scrollTo).not.toHaveBeenCalled();

        await user.click(await screen.findByRole('button', { name: /Latest reply/ }));
        expect(scrollTo).toHaveBeenCalled();
        expect(screen.queryByRole('button', { name: /Latest reply/ })).toBeNull();
    });
});
