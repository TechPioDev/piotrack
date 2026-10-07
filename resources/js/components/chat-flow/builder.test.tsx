import type { Flow } from '@/lib/flow-tree';
import FlowBuilder from '@/pages/chat/flow/edit';
import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
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
    window.localStorage.clear();
    document.body.style.overflow = '';
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

/** A step's card on the canvas. */
function card(id: string): HTMLElement {
    return document.getElementById(`flow-step-${id}`) as HTMLElement;
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
        // Every gap now offers itself; drop into the one before the End.
        const gaps = screen.getAllByText('Drop here');
        const beforeEnd = gaps[gaps.length - 1].parentElement as HTMLElement;
        fireEvent.dragOver(beforeEnd, { dataTransfer: transfer });
        fireEvent.drop(beforeEnd, { dataTransfer: transfer });

        expect(stepIds()).toEqual(['welcome', 'ask_name', 'ask_email', 'done']);
        const flow = publishedFlow();
        expect(flow.nodes.ask_name.next).toBe('ask_email');
        expect(flow.nodes.ask_email).toMatchObject({ type: 'input', input: 'email', field: 'email', optional: false, next: 'done' });
    });

    it('refuses to drop an End where the conversation carries on', () => {
        renderBuilder();

        fireEvent.dragStart(screen.getByRole('button', { name: /^End: New Lead/ }), { dataTransfer: dataTransfer() });

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

        // A switch that says what it is, and is on.
        expect(toggle).toHaveTextContent('Required');
        expect(toggle).toHaveAttribute('aria-checked', 'true');
        expect(card).toHaveTextContent('Visitors must answer');
        fireEvent.click(toggle);

        expect(within(card).getByRole('switch', { name: 'Required' })).toHaveAttribute('aria-checked', 'false');
        expect(card).toHaveTextContent('Visitors may skip');
        expect(publishedFlow().nodes.ask_name.optional).toBe(true);
    });

    it('fans each answer out into its own labelled path, and the paths meet again at the shared step', () => {
        renderBuilder(store);

        // Each answer shows once on the question card and once as its path's label.
        expect(screen.getAllByText('Where is my order?')).toHaveLength(2);
        expect(screen.getAllByText('A product question')).toHaveLength(2);
        expect(document.getElementById('flow-step-order_email')).toBeInTheDocument();
        // Both paths draw a line down into "thanks", which sits once below them.
        expect(document.querySelectorAll('[data-join-tail="thanks"]')).toHaveLength(2);
        expect(stepIds()).toEqual(['q', 'order_email', 'thanks']);
    });

    it('turns a message into a question with Quick Replies, each reply its own path', async () => {
        const user = userEvent.setup();
        renderBuilder();

        await user.click(within(document.getElementById('flow-step-welcome') as HTMLElement).getAllByRole('button')[0]);
        await user.click(screen.getByRole('switch', { name: 'Quick replies' }));
        await user.clear(screen.getByRole('textbox', { name: 'Reply 1' }));
        await user.type(screen.getByRole('textbox', { name: 'Reply 1' }), 'Book a demo');

        const welcome = publishedFlow().nodes.welcome;
        expect(welcome.type).toBe('choice');
        expect(welcome.options?.map((o) => o.label)).toEqual(['Book a demo', 'Option 2']);
        // Both replies carry on to the step the message led to.
        expect(welcome.options?.every((o) => (o.next ?? welcome.next) === 'ask_name')).toBe(true);
    });

    it('undoes and redoes a change', () => {
        renderBuilder();
        fireEvent.click(within(document.getElementById('flow-step-ask_name') as HTMLElement).getByRole('switch', { name: 'Required' }));

        fireEvent.click(screen.getByRole('button', { name: 'Undo' }));
        expect(within(document.getElementById('flow-step-ask_name') as HTMLElement).getByRole('switch')).toHaveAttribute('aria-checked', 'true');

        fireEvent.click(screen.getByRole('button', { name: 'Redo' }));
        expect(within(document.getElementById('flow-step-ask_name') as HTMLElement).getByRole('switch')).toHaveAttribute('aria-checked', 'false');
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

    it('edits a step’s text right on its card; Esc puts it back', async () => {
        const user = userEvent.setup();
        renderBuilder();

        await user.click(within(card('welcome')).getByRole('button', { name: /^Message text: Hi there!/ }));
        await user.keyboard('{Escape}');
        expect(within(card('welcome')).getByText('Hi there!')).toBeInTheDocument();

        await user.click(within(card('welcome')).getByRole('button', { name: /^Message text/ }));
        const box = within(card('welcome')).getByRole('textbox', { name: 'Message text' });
        await user.clear(box);
        await user.type(box, 'Hello and welcome!{Enter}');

        expect(within(card('welcome')).getByText('Hello and welcome!')).toBeInTheDocument();
        expect(publishedFlow().nodes.welcome.text).toBe('Hello and welcome!');
    });

    it('adds, names and removes replies right on a question’s card', async () => {
        const user = userEvent.setup();
        renderBuilder(store);

        await user.click(within(card('q')).getByRole('button', { name: 'Add reply' }));
        const box = within(card('q')).getByRole('textbox', { name: 'Reply “Option 3”' });
        await user.clear(box);
        await user.type(box, 'Returns{Enter}');
        await user.click(within(card('q')).getByRole('button', { name: 'Remove the reply “A product question”' }));

        const q = publishedFlow().nodes.q;
        expect(q.options?.map((o) => o.label)).toEqual(['Where is my order?', 'Returns']);
        // The new reply carries on where the paths met again.
        expect(q.options?.[1].next).toBe('thanks');
    });

    it('places a picked step with a click, where it is allowed', async () => {
        const user = userEvent.setup();
        renderBuilder();

        await user.click(screen.getByRole('button', { name: /^Email/ }));
        expect(screen.getByText(/Click a highlighted place for/)).toHaveTextContent('Email');
        const spots = screen.getAllByRole('button', { name: 'Place here' });
        await user.click(spots[spots.length - 1]);

        expect(stepIds()).toEqual(['welcome', 'ask_name', 'ask_email', 'done']);
        expect(screen.queryByText(/Click a highlighted place/)).toBeNull();

        // Esc puts a picked step down again.
        await user.click(screen.getByRole('button', { name: /^Phone/ }));
        await user.keyboard('{Escape}');
        expect(screen.queryAllByRole('button', { name: 'Place here' })).toHaveLength(0);
    });

    it('duplicates a step from its menu, and Delete removes the selected step', async () => {
        const user = userEvent.setup();
        renderBuilder();

        await user.click(within(card('welcome')).getByRole('button', { name: /actions$/ }));
        await user.click(await screen.findByRole('menuitem', { name: /Duplicate/ }));

        const copy = stepIds()[1];
        expect(stepIds()).toEqual(['welcome', copy, 'ask_name', 'done']);
        expect(publishedFlow().nodes[copy]).toMatchObject({ type: 'message', text: 'Hi there!', next: 'ask_name' });

        // The copy is selected; Delete takes it out again.
        await user.keyboard('{Delete}');
        expect(stepIds()).toEqual(['welcome', 'ask_name', 'done']);
    });

    it('folds a question’s paths away and opens them again', async () => {
        const user = userEvent.setup();
        renderBuilder(store);

        await user.click(within(card('q')).getByRole('button', { name: /actions$/ }));
        await user.click(await screen.findByRole('menuitem', { name: /Fold its paths away/ }));

        expect(card('order_email')).toBeNull();
        expect(card('thanks')).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: /2 paths, 1 step folded away/ }));
        expect(card('order_email')).toBeInTheDocument();
    });

    it('hides the side panels for room, keeps steps draggable in a strip, and remembers it', async () => {
        const user = userEvent.setup();
        const { unmount } = renderBuilder();

        await user.click(screen.getAllByRole('button', { name: 'Hide steps panel' })[0]);
        await user.click(screen.getAllByRole('button', { name: 'Hide settings panel' })[0]);

        expect(screen.queryByRole('textbox', { name: 'Search steps' })).toBeNull();
        expect(screen.queryByText('How to build')).toBeNull();
        expect(screen.getByRole('button', { name: 'Email' })).toHaveAttribute('draggable', 'true');

        unmount();
        renderBuilder();
        expect(screen.queryByRole('textbox', { name: 'Search steps' })).toBeNull();
        await user.click(screen.getAllByRole('button', { name: 'Show steps panel' })[0]);
        expect(screen.getByRole('textbox', { name: 'Search steps' })).toBeInTheDocument();
    });

    it('focus mode covers the app around the builder, and Esc leaves it', async () => {
        const user = userEvent.setup();
        renderBuilder();

        await user.click(screen.getByRole('button', { name: 'Focus mode' }));
        expect(screen.getByRole('button', { name: 'Exit focus' })).toHaveAttribute('aria-pressed', 'true');
        expect(document.body.style.overflow).toBe('hidden');

        await user.keyboard('{Escape}');
        expect(screen.getByRole('button', { name: 'Focus mode' })).toHaveAttribute('aria-pressed', 'false');
        expect(document.body.style.overflow).toBe('');
    });

    it('gives a meeting ending a time-picker in front of it, so nobody is told to book with nothing', async () => {
        const user = userEvent.setup();
        // A conversation that has not been finished off yet: the End blocks are
        // only allowed where nothing follows.
        renderBuilder({
            start: 'welcome',
            nodes: {
                welcome: { type: 'message', text: 'Hi there!', next: 'ask_name' },
                ask_name: { type: 'input', input: 'text', field: 'first_name', text: 'What is your first name?' },
            },
        });

        await user.click(screen.getAllByRole('button', { name: 'Add a step' })[0]);
        await user.click(await screen.findByRole('menuitem', { name: /End: Book a Meeting/ }));

        const flow = publishedFlow();
        const picker = Object.entries(flow.nodes).find(([, node]) => node.type === 'booking');
        const ending = Object.entries(flow.nodes).find(([, node]) => node.type === 'end' && node.outcome === 'meeting');

        expect(picker).toBeDefined();
        expect(ending).toBeDefined();
        // Times first, the ending after them - and the ending is also where a
        // tenant with no booking page lands instead.
        expect(flow.nodes.ask_name.next).toBe(picker?.[0]);
        expect(picker?.[1].next).toBe(ending?.[0]);
        expect(picker?.[1].fallback).toBe(ending?.[0]);
    });

    /** Open a step's settings the way a person does: by its card. */
    const openStep = (id: string) => fireEvent.click(card(id).querySelector('button[aria-pressed]') as HTMLElement);

    it('lets a support ending say who gets the ticket, and asks nothing of the other endings', () => {
        renderBuilder({
            start: 'welcome',
            nodes: {
                welcome: { type: 'message', text: 'Hi there!', next: 'done' },
                done: { type: 'end', outcome: 'support', text: 'We have opened a ticket.' },
            },
        });

        openStep('done');
        expect(screen.getByText('Who gets the ticket')).toBeInTheDocument();
        expect(screen.getByRole('combobox', { name: 'Who gets the ticket' })).toHaveTextContent('Whoever this chat normally goes to');
    });

    it('does not ask who gets a ticket on an ending that opens none', () => {
        renderBuilder();

        openStep('done');
        expect(screen.getByText('What happens at the end')).toBeInTheDocument();
        expect(screen.queryByText('Who gets the ticket')).not.toBeInTheDocument();
    });

    it('calls the hand-over step what it is, since it routes tickets as well as leads', () => {
        renderBuilder({
            start: 'route',
            nodes: {
                route: { type: 'assign', assignee_id: null, next: 'done' },
                done: { type: 'end', outcome: 'support', text: 'We have opened a ticket.' },
            },
        });

        expect(within(card('route')).getByText('Assign to a Teammate')).toBeInTheDocument();
        openStep('route');
        expect(screen.getByText('Hand the conversation to')).toBeInTheDocument();
        expect(screen.getByText(/billing questions to one person and technical ones to another/)).toBeInTheDocument();
    });

    /*
     * Reported from a live workspace: "we cannot edit, rename or swap steps".
     * A question's menu offered only Edit, Fold and Delete - and a template is
     * mostly questions.
     */

    /** Two questions and a name, in a row: the shape of the top of every template. */
    const questions: Flow = {
        start: 'q_need',
        nodes: {
            q_need: {
                type: 'choice',
                text: 'What are you looking for?',
                field: 'need',
                options: [
                    { id: 'audit', label: 'An audit', next: 'q_size' },
                    { id: 'help', label: 'General help', next: 'q_size' },
                ],
            },
            q_size: {
                type: 'choice',
                text: 'How many employees?',
                field: 'size',
                options: [
                    { id: 'small', label: '1-10', next: 'ask_name' },
                    { id: 'large', label: '11+', next: 'ask_name' },
                ],
            },
            ask_name: { type: 'input', input: 'text', field: 'first_name', text: 'Your first name?', next: 'done' },
            done: { type: 'end', outcome: 'lead', text: 'Thanks!' },
        },
    };

    const openMenu = async (user: ReturnType<typeof userEvent.setup>, id: string) =>
        user.click(within(card(id)).getByRole('button', { name: /actions$/ }));

    it('gives a question the whole menu: edit, rename, move, copy, delete', async () => {
        const user = userEvent.setup();
        renderBuilder(questions);

        await openMenu(user, 'q_need');

        for (const name of ['Edit', 'Rename', 'Move up', 'Move down', 'Move to another place…', 'Duplicate', 'Delete']) {
            expect(await screen.findByRole('menuitem', { name })).toBeInTheDocument();
        }
        // First in the conversation: nowhere further up to go, but it can go down.
        expect(screen.getByRole('menuitem', { name: 'Move up' })).toHaveAttribute('aria-disabled', 'true');
        expect(screen.getByRole('menuitem', { name: 'Move down' })).not.toHaveAttribute('aria-disabled', 'true');
    });

    it('swaps two questions from the menu', async () => {
        const user = userEvent.setup();
        renderBuilder(questions);
        expect(stepIds()).toEqual(['q_need', 'q_size', 'ask_name', 'done']);

        await openMenu(user, 'q_need');
        await user.click(await screen.findByRole('menuitem', { name: 'Move down' }));

        expect(stepIds()).toEqual(['q_size', 'q_need', 'ask_name', 'done']);
        const flow = publishedFlow();
        expect(flow.start).toBe('q_size');
        expect(flow.nodes.q_size.options?.every((o) => o.next === 'q_need')).toBe(true);
        expect(flow.nodes.q_need.options?.every((o) => o.next === 'ask_name')).toBe(true);
    });

    it('copies a question with its replies', async () => {
        const user = userEvent.setup();
        renderBuilder(questions);

        await openMenu(user, 'q_size');
        await user.click(await screen.findByRole('menuitem', { name: 'Duplicate' }));

        const ids = stepIds();
        expect(ids).toHaveLength(5);
        const copy = publishedFlow().nodes[ids[2]];
        expect(copy.type).toBe('choice');
        expect(copy.options?.map((o) => o.label)).toEqual(['1-10', '11+']);
    });

    it('renames a step from its menu, and still says what kind of step it is', async () => {
        const user = userEvent.setup();
        renderBuilder(questions);

        await openMenu(user, 'q_size');
        await user.click(await screen.findByRole('menuitem', { name: 'Rename' }));

        // The settings open with the cursor already in the name.
        const name = screen.getByRole('textbox', { name: 'Step name' });
        await waitFor(() => expect(name).toHaveFocus());
        await user.type(name, 'Company size');

        const title = within(card('q_size'));
        expect(title.getByText('Company size')).toBeInTheDocument();
        expect(title.getByText('Ask a Question')).toBeInTheDocument();
        // The other question keeps its plain title.
        expect(within(card('q_need')).queryByText('Company size')).toBeNull();
        expect(publishedFlow().nodes.q_size.name).toBe('Company size');
    });

    it('lets a typed answer be made optional from its menu as well as its switch', async () => {
        const user = userEvent.setup();
        renderBuilder(questions);

        await openMenu(user, 'ask_name');
        await user.click(await screen.findByRole('menuitem', { name: 'Make it optional' }));

        expect(within(card('ask_name')).getByRole('switch', { name: 'Required' })).toHaveAttribute('aria-checked', 'false');
        expect(publishedFlow().nodes.ask_name.optional).toBe(true);
    });

    it('says how to edit, in words, above the conversation', () => {
        renderBuilder();

        const hint = screen.getByText((_, el) => el?.tagName === 'P' && (el.textContent ?? '').startsWith('Click any text to change it'));
        expect(hint).toHaveTextContent('Click any text to change it · drag a step to move it · ⋯ to rename, copy or delete');
    });

    it('names every step under its icon when the steps panel is folded away', async () => {
        const user = userEvent.setup();
        renderBuilder();

        await user.click(screen.getAllByRole('button', { name: 'Hide steps panel' })[0]);

        // Fifteen unlabelled icons were not something anyone could read.
        const strip = screen.getByRole('button', { name: 'Text Field' });
        expect(strip).toHaveTextContent('Text');
        expect(screen.getByRole('button', { name: 'Ask a Question' })).toHaveTextContent('Question');
        expect(screen.getByRole('button', { name: 'Email' })).toHaveTextContent('Email');
    });

    it('finds the text field by the words people use for it', async () => {
        const user = userEvent.setup();
        renderBuilder();

        const search = screen.getByRole('textbox', { name: 'Search steps' });
        const library = within(search.closest('[role="tabpanel"]') as HTMLElement);
        expect(library.getByRole('button', { name: /Send Message/ })).toBeInTheDocument();

        await user.type(search, 'input box');

        expect(library.getByRole('button', { name: /Text Field/ })).toBeInTheDocument();
        expect(library.queryByRole('button', { name: /Send Message/ })).toBeNull();
    });

    it('shows what an ending reached after booking does, instead of an empty box', () => {
        renderBuilder({
            start: 'welcome',
            nodes: {
                welcome: { type: 'message', text: 'Hi there!', next: 'done' },
                done: { type: 'end', outcome: 'booked', text: 'You are booked in.' },
            },
        });

        expect(within(card('done')).getByText('Saves the lead - a time is booked')).toBeInTheDocument();
        openStep('done');
        expect(screen.getByRole('combobox', { name: 'What happens at the end' })).toHaveTextContent('Save the lead (a time was just booked)');
    });

    /*
     * A condition, a link button and booking by the owner's own link (CHAT-091..094).
     * Reported from a live workspace: a Condition dropped into an empty
     * conversation offered "Choose a question…" and nothing else.
     */

    /** A conversation with a condition and nothing for it to check. */
    const bare: Flow = {
        start: 'check',
        nodes: {
            check: { type: 'condition', field: '', operator: 'equals', value: '', next: 'done', otherwise: 'done' },
            done: { type: 'end', outcome: 'lead', text: 'Thanks!' },
        },
    };

    it('offers to add the question a condition needs, and points the condition at it', async () => {
        const user = userEvent.setup();
        renderBuilder(bare);

        openStep('check');
        expect(screen.getByText('First, ask a question')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Question with replies' }));

        // The question goes just above the condition, which now checks its first reply.
        expect(stepIds()).toEqual(['question_1', 'check', 'done']);
        expect(screen.queryByText('First, ask a question')).not.toBeInTheDocument();
        expect(screen.getByRole('combobox', { name: 'Question to check' })).toHaveTextContent('What can we help you with?');
        expect(screen.getByRole('combobox', { name: 'Reply to compare with' })).toHaveTextContent('First answer');
        // Said on the card in the words a visitor saw, not the names they are stored under.
        expect(within(card('check')).getByText('If “What can we help you with?” is “First answer”')).toBeInTheDocument();

        const flow = publishedFlow();
        expect(flow.nodes.check).toMatchObject({ field: 'question_1', operator: 'equals', value: 'answer_1' });
        expect(flow.nodes.question_1.options?.every((o) => o.next === 'check')).toBe(true);
    });

    it('checks whether a text field was answered when that is the question added', async () => {
        const user = userEvent.setup();
        renderBuilder(bare);

        openStep('check');
        await user.click(screen.getByRole('button', { name: 'Text field' }));

        expect(within(card('check')).getByText('If “Tell us a little about what you need.” was answered')).toBeInTheDocument();
        const flow = publishedFlow();
        const asked = Object.entries(flow.nodes).find(([, node]) => node.type === 'input');
        expect(flow.nodes.check).toMatchObject({ field: asked?.[1].field, operator: 'is_set' });
        expect(asked?.[1].next).toBe('check');
    });

    it('shows both ways out of a condition beside the check, and says when they lead the same way', async () => {
        const user = userEvent.setup();
        renderBuilder(bare);

        openStep('check');
        await user.click(screen.getByRole('button', { name: 'Question with replies' }));

        expect(screen.getByRole('combobox', { name: 'When it matches' })).toBeInTheDocument();
        expect(screen.getByRole('combobox', { name: 'Otherwise' })).toBeInTheDocument();
        expect(screen.getByText(/Both lead the same way/)).toBeInTheDocument();
    });

    it('leads from a condition to the question it checks, to reword it', async () => {
        const user = userEvent.setup();
        renderBuilder(bare);

        openStep('check');
        await user.click(screen.getByRole('button', { name: 'Question with replies' }));
        await user.click(screen.getByRole('button', { name: /Edit this question and its replies/ }));

        expect(screen.getByLabelText('Question Text')).toHaveValue('What can we help you with?');
        expect(screen.getByRole('textbox', { name: 'Reply 1' })).toHaveValue('First answer');
    });

    it('names the settings tabs for what they hold', () => {
        renderBuilder();

        openStep('welcome');
        expect(screen.getAllByRole('tab').map((tab) => tab.textContent)).toEqual(expect.arrayContaining(['Content', 'Paths', 'Advanced']));
    });

    it('puts a link button on a message, and says so on its card until it has somewhere to go', async () => {
        const user = userEvent.setup();
        renderBuilder();

        openStep('welcome');
        await user.click(screen.getByRole('switch', { name: 'Link button' }));

        expect(within(card('welcome')).getByText('· no link yet')).toBeInTheDocument();
        // A message has a link button or quick replies, not both.
        expect(screen.queryByRole('switch', { name: 'Quick replies' })).not.toBeInTheDocument();

        const link = screen.getByLabelText('Link it opens');
        await user.type(link, 'maps.google.com');
        expect(screen.getByText('A link must be a full address starting with https://')).toBeInTheDocument();

        await user.clear(link);
        await user.type(link, 'https://maps.google.com/?q=PioManage');
        await user.clear(screen.getByLabelText('Button text'));
        await user.type(screen.getByLabelText('Button text'), 'Open the map');

        expect(within(card('welcome')).getByText('Open the map')).toBeInTheDocument();
        expect(within(card('welcome')).queryByText('· no link yet')).not.toBeInTheDocument();
        expect(within(card('welcome')).getByText('Link Button')).toBeInTheDocument();
        expect(publishedFlow().nodes.welcome).toMatchObject({ type: 'message', button: 'Open the map', url: 'https://maps.google.com/?q=PioManage' });
    });

    it('takes the link button off again, leaving a plain message', async () => {
        const user = userEvent.setup();
        renderBuilder({
            start: 'welcome',
            nodes: {
                welcome: {
                    type: 'message',
                    text: 'Find us here.',
                    button: 'Open the map',
                    url: 'https://maps.google.com/?q=PioManage',
                    next: 'done',
                },
                done: { type: 'end', outcome: 'lead', text: 'Thanks!' },
            },
        });

        openStep('welcome');
        await user.click(screen.getByRole('switch', { name: 'Link button' }));

        const welcome = publishedFlow().nodes.welcome;
        expect(welcome.button).toBeUndefined();
        expect(welcome.url).toBeUndefined();
        expect(within(card('welcome')).getByText('Send Message')).toBeInTheDocument();
    });

    it('has a Link Button step in the library, found by what it is for', async () => {
        const user = userEvent.setup();
        renderBuilder();

        const search = screen.getByRole('textbox', { name: 'Search steps' });
        const library = within(search.closest('[role="tabpanel"]') as HTMLElement);
        await user.type(search, 'google maps');

        expect(library.getByRole('button', { name: /Link Button/ })).toBeInTheDocument();
    });

    it('lets a booking step open a booking page instead of offering times', async () => {
        const user = userEvent.setup();
        renderBuilder({
            start: 'book',
            nodes: {
                book: { type: 'booking', text: 'Pick a time that suits you:', next: 'done', fallback: null },
                done: { type: 'end', outcome: 'lead', text: 'Thanks!' },
            },
        });

        openStep('book');
        expect(screen.getByRole('radio', { name: /Pick a time in the chat/ })).toHaveAttribute('aria-checked', 'true');

        await user.click(screen.getByRole('radio', { name: /Open a booking page/ }));

        expect(screen.getByRole('combobox', { name: 'Booking page the button opens' })).toHaveTextContent('Your booking form here');
        expect(screen.getByLabelText('Text Above the Button')).toBeInTheDocument();
        expect(within(card('book')).getByText('Choose a time')).toBeInTheDocument();
        expect(publishedFlow().nodes.book).toMatchObject({ type: 'booking', mode: 'link' });
    });

    it('shows the owner’s own booking link on a booking step, with the button in their words', () => {
        renderBuilder({
            start: 'book',
            nodes: {
                book: {
                    type: 'booking',
                    mode: 'link',
                    link_to: 'custom',
                    url: 'https://outlook.office.com/book/PioManage@piomanage.test/',
                    button: 'Book on Teams',
                    text: 'Book a call with us:',
                    next: 'done',
                    fallback: null,
                },
                done: { type: 'end', outcome: 'lead', text: 'Thanks!' },
            },
        });

        expect(within(card('book')).getByText('Book on Teams')).toBeInTheDocument();
        openStep('book');
        expect(screen.getByRole('combobox', { name: 'Booking page the button opens' })).toHaveTextContent('Your own booking link');
        expect(screen.getByLabelText('Your booking link')).toHaveValue('https://outlook.office.com/book/PioManage@piomanage.test/');
        expect(screen.getByLabelText('Button text')).toHaveValue('Book on Teams');
    });

    it('asks a meeting ending which booking page its button opens, and flags an own link that is missing', () => {
        renderBuilder({
            start: 'welcome',
            nodes: {
                welcome: { type: 'message', text: 'Hi there!', next: 'done' },
                done: { type: 'end', outcome: 'meeting', link_to: 'custom', url: '', text: 'Pick a time below.' },
            },
        });

        expect(within(card('done')).getByText('· no link yet')).toBeInTheDocument();
        openStep('done');
        expect(screen.getByRole('combobox', { name: 'Booking page the button opens' })).toHaveTextContent('Your own booking link');
        expect(screen.getByLabelText('Your booking link')).toHaveValue('');
    });

    it('keeps a step’s own name when quick replies are turned on', async () => {
        const user = userEvent.setup();
        renderBuilder({
            start: 'welcome',
            nodes: {
                welcome: { type: 'message', name: 'Greeting', text: 'Hi there!', next: 'done' },
                done: { type: 'end', outcome: 'lead', text: 'Thanks!' },
            },
        });

        openStep('welcome');
        await user.click(screen.getByRole('switch', { name: 'Quick replies' }));

        expect(publishedFlow().nodes.welcome).toMatchObject({ type: 'choice', name: 'Greeting' });
    });
});
