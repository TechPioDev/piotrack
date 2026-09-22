import type { Flow } from '@/lib/flow-tree';
import FlowBuilder from '@/pages/chat/flow/edit';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * The conversation builder, used the way an owner uses it: dragging blocks
 * from the library into the tree, adding with the "+" menu, switching a
 * contact detail between required and optional on its card, loading a
 * template and undoing. What gets published is the conversation the tree shows.
 */

const { put } = vi.hoisted(() => ({ put: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children }: { children: React.ReactNode }) => <a>{children}</a>,
    router: { put },
    usePage: () => ({ props: {} }),
}));
vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

beforeAll(() => {
    (globalThis as unknown as { route: (name: string) => string }).route = (name: string) => `/${name}`;
    Element.prototype.scrollIntoView = () => {};
    Element.prototype.hasPointerCapture = () => false;
    Element.prototype.releasePointerCapture = () => {};
});

beforeEach(() => {
    put.mockReset();
    // The builder shows step settings beside the tree on a wide screen.
    window.matchMedia = (query: string) =>
        ({ matches: query.includes('1280'), media: query, addEventListener: () => {}, removeEventListener: () => {} }) as unknown as MediaQueryList;
    globalThis.fetch = vi.fn(async () => new Response(JSON.stringify({ valid: true, errors: [], warnings: [] }))) as typeof fetch;
});

const simple: Flow = {
    start: 'welcome',
    nodes: {
        welcome: { type: 'message', text: 'Hi there!', next: 'ask_name' },
        ask_name: { type: 'input', input: 'text', field: 'first_name', text: 'What is your first name?', next: 'done' },
        done: { type: 'end', outcome: 'lead', text: 'Thanks!' },
    },
};

const store: Flow = {
    start: 'q',
    nodes: {
        q: {
            type: 'choice',
            text: 'What do you need?',
            field: 'need',
            options: [
                { id: 'order', label: 'Where is my order?', next: 'order_email' },
                { id: 'product', label: 'A product question', next: 'thanks' },
            ],
        },
        order_email: { type: 'input', input: 'email', field: 'email', text: 'Your order email?', next: 'thanks' },
        thanks: { type: 'end', outcome: 'lead', text: 'Thanks!' },
    },
};

function renderBuilder(flow: Flow = simple) {
    return render(
        <FlowBuilder
            widget={{ id: 7, name: 'Website', status: 'draft' }}
            flow={flow}
            validation={{ valid: true, errors: [], warnings: [] }}
            templates={[
                { key: 'ecommerce', name: 'Online store', description: 'Orders and products.', category: 'Online store', steps: 3, flow: store },
            ]}
            assignees={[]}
        />,
    );
}

/** The steps in the tree, top to bottom. */
function stepIds(): string[] {
    return [...document.querySelectorAll('[id^="flow-step-"]')].map((el) => el.id.replace('flow-step-', ''));
}

/** A browser-like drag, carrying data the way the page reads it. */
function dataTransfer() {
    const data: Record<string, string> = {};
    return { data, setData: (k: string, v: string) => (data[k] = v), getData: (k: string) => data[k], dropEffect: '', effectAllowed: '' };
}

function publishedFlow(): Flow {
    fireEvent.click(screen.getByRole('button', { name: 'Publish' }));
    return put.mock.calls.at(-1)?.[1].flow as Flow;
}

describe('conversation builder', () => {
    it('draws the conversation top to bottom', () => {
        renderBuilder();

        expect(stepIds()).toEqual(['welcome', 'ask_name', 'done']);
        expect(screen.getByText('What is your first name?')).toBeInTheDocument();
    });

    it('adds a block dragged from the library into the gap it is dropped in', () => {
        renderBuilder();
        const transfer = dataTransfer();

        fireEvent.dragStart(screen.getByRole('button', { name: /^Email/ }), { dataTransfer: transfer });
        // Every gap now offers itself; drop into the one before "Finish".
        const gaps = screen.getAllByText('Drop here');
        const beforeFinish = gaps[gaps.length - 1].parentElement as HTMLElement;
        fireEvent.dragOver(beforeFinish, { dataTransfer: transfer });
        fireEvent.drop(beforeFinish, { dataTransfer: transfer });

        expect(stepIds()).toEqual(['welcome', 'ask_name', 'ask_email', 'done']);
        const flow = publishedFlow();
        expect(flow.nodes.ask_name.next).toBe('ask_email');
        expect(flow.nodes.ask_email).toMatchObject({ type: 'input', input: 'email', field: 'email', optional: false, next: 'done' });
    });

    it('refuses to drop a Finish where the conversation carries on', () => {
        renderBuilder();

        fireEvent.dragStart(screen.getByRole('button', { name: /^Finish: new lead/ }), { dataTransfer: dataTransfer() });

        // Every gap here leads on to another step, so none accepts it.
        expect(screen.queryAllByText('Drop here')).toHaveLength(0);
    });

    it('moves a step by dragging it to another gap', () => {
        renderBuilder({
            ...simple,
            nodes: {
                ...simple.nodes,
                ask_name: { ...simple.nodes.ask_name, next: 'ask_email' },
                ask_email: { type: 'input', input: 'email', field: 'email', text: 'Email?', next: 'done' },
            },
        });
        const transfer = dataTransfer();

        fireEvent.dragStart(document.getElementById('flow-step-ask_email') as HTMLElement, { dataTransfer: transfer });
        const first = screen.getAllByText('Drop here')[0].parentElement as HTMLElement;
        fireEvent.drop(first, { dataTransfer: transfer });

        expect(stepIds()).toEqual(['ask_email', 'welcome', 'ask_name', 'done']);
    });

    it('adds a step from the + menu in a gap', async () => {
        const user = userEvent.setup();
        renderBuilder();

        await user.click(screen.getAllByRole('button', { name: 'Add a step here' })[1]);
        await user.click(await screen.findByRole('menuitem', { name: /Phone/ }));

        expect(stepIds()).toEqual(['welcome', 'ask_phone', 'ask_name', 'done']);
    });

    it('switches a contact detail between required and optional right on its card', () => {
        renderBuilder();
        const card = document.getElementById('flow-step-ask_name') as HTMLElement;
        const toggle = within(card).getByRole('switch', { name: 'Required' });

        expect(toggle).toHaveTextContent('Required');
        fireEvent.click(toggle);

        expect(within(card).getByRole('switch', { name: 'Required' })).toHaveTextContent('Optional');
        expect(publishedFlow().nodes.ask_name.optional).toBe(true);
    });

    it('shows each answer’s path under the question', () => {
        renderBuilder(store);

        expect(screen.getAllByText('If they choose')).toHaveLength(2);
        const orderCard = document.getElementById('flow-step-order_email') as HTMLElement;
        expect(orderCard).toBeInTheDocument();
        expect(screen.getByText('Paths meet again here')).toBeInTheDocument();
    });

    it('undoes and redoes a change', () => {
        renderBuilder();
        fireEvent.click(within(document.getElementById('flow-step-ask_name') as HTMLElement).getByRole('switch', { name: 'Required' }));

        fireEvent.click(screen.getByRole('button', { name: 'Undo' }));
        expect(within(document.getElementById('flow-step-ask_name') as HTMLElement).getByRole('switch')).toHaveTextContent('Required');

        fireEvent.click(screen.getByRole('button', { name: 'Redo' }));
        expect(within(document.getElementById('flow-step-ask_name') as HTMLElement).getByRole('switch')).toHaveTextContent('Optional');
    });

    it('loads a template into the editor without publishing it, and undo brings the old one back', async () => {
        const user = userEvent.setup();
        renderBuilder();

        await user.click(screen.getByRole('button', { name: /Templates/ }));
        await user.click(await screen.findByRole('button', { name: 'Use this template' }));

        expect(stepIds()).toEqual(['q', 'order_email', 'thanks']);
        expect(put).not.toHaveBeenCalled();

        await act(async () => fireEvent.click(screen.getByRole('button', { name: 'Undo' })));
        expect(stepIds()).toEqual(['welcome', 'ask_name', 'done']);
    });

    it('asks before deleting a question whose answers lead to steps of their own', async () => {
        const user = userEvent.setup();
        renderBuilder(store);

        await user.click(within(document.getElementById('flow-step-q') as HTMLElement).getAllByRole('button')[0]);
        await user.click(screen.getByRole('button', { name: /Delete step/ }));

        const dialog = await screen.findByRole('dialog');
        expect(dialog).toHaveTextContent('Its answers lead to 2 steps');
        await user.click(within(dialog).getByRole('button', { name: 'Delete 3 steps' }));

        expect(stepIds()).toEqual([]);
    });
});
