import { describe, expect, it } from 'vitest';

import {
    addOption,
    buildTree,
    canInsert,
    duplicateStep,
    type Flow,
    type FlowNode,
    insertStep,
    locate,
    mainExit,
    moveStep,
    reachable,
    removeOption,
    removeStep,
    type Slot,
    stepsInside,
    targetOf,
    type TreeBranch,
    withQuickReplies,
} from './flow-tree';

/**
 * The builder draws the saved conversation as a tree and edits it by dropping
 * blocks into place. Whatever it draws and whatever it saves must be the same
 * conversation, so these pin both: how the graph is drawn, and that every edit
 * leaves it connected.
 */

const message = (text: string, next: string | null = null): FlowNode => ({ type: 'message', text, next });
const ask = (field: string, next: string | null = null, input = 'text'): FlowNode => ({ type: 'input', text: `Your ${field}?`, field, input, next });
const finish = (text = 'Thanks!'): FlowNode => ({ type: 'end', outcome: 'lead', text });
const question = (text: string, answers: [string, string | null][]): FlowNode => ({
    type: 'choice',
    text,
    field: 'answer',
    options: answers.map(([id, next]) => ({ id, label: id.toUpperCase(), next })),
});

/** The ids of a branch's steps, top to bottom. */
const ids = (branch: TreeBranch) => branch.steps.map((s) => s.id);

/** A small version of the real MSP conversation: most answers re-join, one path ends alone. */
const msp: Flow = {
    start: 'welcome',
    nodes: {
        welcome: message('Hi!', 'q_service'),
        q_service: question('What can we help with?', [
            ['managed', 'q_size'],
            ['cloud', 'q_size'],
            ['security', 'q_security'],
            ['support', 'support_email'],
        ]),
        q_security: question('What do you need?', [
            ['audit', 'q_size'],
            ['mdr', 'q_size'],
        ]),
        support_email: ask('email', 'end_support', 'email'),
        end_support: { type: 'end', outcome: 'support', text: 'Ticket opened.' },
        q_size: question('How many staff?', [
            ['small', 'ask_name'],
            ['large', 'ask_name'],
        ]),
        ask_name: ask('first_name', 'ask_email'),
        ask_email: ask('email', 'done', 'email'),
        done: finish(),
    },
};

describe('buildTree', () => {
    it('draws a simple conversation as one path, top to bottom', () => {
        const flow: Flow = { start: 'a', nodes: { a: message('Hi', 'b'), b: ask('email', 'c', 'email'), c: finish() } };

        const { root, unreachable } = buildTree(flow);

        expect(ids(root)).toEqual(['a', 'b', 'c']);
        expect(root.end).toEqual({ kind: 'finished' });
        expect(unreachable).toEqual([]);
    });

    it('keeps a question whose answers all lead the same way on the main path', () => {
        const { root } = buildTree(msp);
        const sizeStep = root.steps.find((s) => s.id === 'q_size');

        expect(sizeStep?.branches).toEqual([]);
        expect(ids(root)).toEqual(['welcome', 'q_service', 'q_size', 'ask_name', 'ask_email', 'done']);
    });

    it('splits where answers lead different ways, and re-joins where they meet again', () => {
        const service = buildTree(msp).root.steps[1];

        expect(service.id).toBe('q_service');
        expect(service.join).toBe('q_size');
        expect(service.branches.map((b) => b.label)).toEqual(['MANAGED · CLOUD', 'SECURITY', 'SUPPORT']);

        const [straight, security, support] = service.branches;
        // Answers that go straight to the re-join point have nothing of their own.
        expect(ids(straight)).toEqual([]);
        expect(straight.end).toEqual({ kind: 'joins', to: 'q_size' });
        // A path with its own questions shows them, then re-joins.
        expect(ids(security)).toEqual(['q_security']);
        expect(security.end).toEqual({ kind: 'joins', to: 'q_size' });
        // A path that never meets the others finishes inside the split.
        expect(ids(support)).toEqual(['support_email', 'end_support']);
        expect(support.end).toEqual({ kind: 'finished' });
    });

    it('shows "continues at" for a path that leads back to a step already drawn', () => {
        const flow: Flow = {
            start: 'q',
            nodes: {
                q: question('Again?', [
                    ['yes', 'more'],
                    ['no', 'bye'],
                ]),
                more: message('Here is more', 'q'),
                bye: finish(),
            },
        };

        const q = buildTree(flow).root.steps[0];

        expect(ids(q.branches[0])).toEqual(['more']);
        expect(q.branches[0].end).toEqual({ kind: 'jump', to: 'q' });
    });

    it('lists steps nothing leads to, instead of silently dropping them', () => {
        const flow: Flow = { start: 'a', nodes: { a: message('Hi'), stray: message('Nobody sees me') } };

        expect(buildTree(flow).unreachable).toEqual(['stray']);
    });

    it('splits a booking step only when it has its own path for "no time works"', () => {
        const plain: Flow = { start: 'b', nodes: { b: { type: 'booking', text: 'Pick a time', next: 'done', fallback: null }, done: finish() } };
        expect(ids(buildTree(plain).root)).toEqual(['b', 'done']);

        const withFallback: Flow = {
            start: 'b',
            nodes: {
                b: { type: 'booking', text: 'Pick a time', next: 'done', fallback: 'sorry' },
                sorry: message('No problem', 'done'),
                done: finish(),
            },
        };
        const booking = buildTree(withFallback).root.steps[0];
        expect(booking.branches.map((b) => b.label)).toEqual(['After booking', 'If no time works']);
        expect(booking.join).toBe('done');
    });
});

describe('insertStep', () => {
    it('puts a step between two others, and the path runs through it', () => {
        const flow: Flow = { start: 'a', nodes: { a: message('Hi', 'c'), c: finish() } };
        const after = buildTree(flow).root.steps[0];

        const { flow: next, id } = insertStep(flow, { kind: 'exit', from: 'a', exit: 'next' }, ask('email', null, 'email'));

        expect(id).toBe('ask_email');
        expect(next.nodes.a.next).toBe('ask_email');
        expect(next.nodes.ask_email.next).toBe('c');
        expect(after.id).toBe('a');
        expect(ids(buildTree(next).root)).toEqual(['a', 'ask_email', 'c']);
    });

    it('can become the new first step', () => {
        const flow: Flow = { start: 'a', nodes: { a: finish() } };

        const { flow: next, id } = insertStep(flow, { kind: 'start' }, message('Welcome!'));

        expect(next.start).toBe(id);
        expect(next.nodes[id].next).toBe('a');
    });

    it('builds a conversation from nothing', () => {
        let flow: Flow = { start: null, nodes: {} };
        flow = insertStep(flow, { kind: 'start' }, message('Hi')).flow;
        const tail = buildTree(flow).root.endSlot;
        expect(tail).not.toBeNull();
        flow = insertStep(flow, tail!, finish()).flow;

        expect(buildTree(flow).root.end).toEqual({ kind: 'finished' });
    });

    it('leads every answer of a new question on to what came next', () => {
        const flow: Flow = { start: 'a', nodes: { a: message('Hi', 'c'), c: finish() } };

        const { flow: next, id } = insertStep(
            flow,
            { kind: 'exit', from: 'a', exit: 'next' },
            question('Which?', [
                ['x', null],
                ['y', null],
            ]),
        );

        expect(next.nodes[id].options?.map((o) => o.next)).toEqual(['c', 'c']);
    });

    it('adds a step to one path only, when dropped inside a branch', () => {
        const security = buildTree(msp).root.steps[1].branches[1];

        const { flow: next, id } = insertStep(msp, security.endSlot!, message('Security specialists will help.'));

        expect(next.nodes.q_security.options?.map((o) => o.next)).toEqual([id, id]);
        expect(next.nodes[id].next).toBe('q_size');
        // Other paths are untouched.
        expect(next.nodes.q_service.options?.find((o) => o.id === 'managed')?.next).toBe('q_size');
    });

    it('adds a step for every path at once, when dropped where they re-join', () => {
        const joinSlot = buildTree(msp).root.steps[2].via;

        const { flow: next, id } = insertStep(msp, joinSlot, message('Great, a couple of quick questions.'));

        expect(next.nodes[id].next).toBe('q_size');
        expect(next.nodes.q_service.options?.filter((o) => o.id === 'managed' || o.id === 'cloud').map((o) => o.next)).toEqual([id, id]);
        expect(next.nodes.q_security.options?.map((o) => o.next)).toEqual([id, id]);
        // The path that ended on its own is unaffected.
        expect(next.nodes.q_service.options?.find((o) => o.id === 'support')?.next).toBe('support_email');
    });

    it('allows a Finish only where nothing follows', () => {
        const flow: Flow = { start: 'a', nodes: { a: message('Hi', 'b'), b: message('Bye') } };

        expect(canInsert(flow, { kind: 'exit', from: 'a', exit: 'next' }, 'end')).toBe(false);
        expect(canInsert(flow, { kind: 'exit', from: 'b', exit: 'next' }, 'end')).toBe(true);
        expect(canInsert(flow, { kind: 'exit', from: 'a', exit: 'next' }, 'message')).toBe(true);
    });
});

describe('removeStep', () => {
    it('closes the gap a step leaves', () => {
        const { flow, removed } = removeStep(msp, 'ask_name');

        expect(removed).toEqual(['ask_name']);
        expect(flow.nodes.q_size.options?.map((o) => o.next)).toEqual(['ask_email', 'ask_email']);
        expect(flow.nodes.ask_name).toBeUndefined();
    });

    it('keeps the path when a question whose answers all lead one way is removed', () => {
        const { flow } = removeStep(msp, 'q_size');

        expect(flow.nodes.q_security.options?.map((o) => o.next)).toEqual(['ask_name', 'ask_name']);
        expect(reachable(flow).has('done')).toBe(true);
    });

    it('takes the steps only a removed question led to with it, and says which', () => {
        const { flow, removed } = removeStep(msp, 'q_service');

        expect(removed.sort()).toEqual(['q_service', 'q_security', 'support_email', 'end_support', 'q_size', 'ask_name', 'ask_email', 'done'].sort());
        expect(flow.nodes.welcome.next).toBeNull();
    });

    it('moves the start on when the first step is removed', () => {
        const { flow } = removeStep(msp, 'welcome');

        expect(flow.start).toBe('q_service');
    });
});

describe('moveStep', () => {
    it('reorders steps, joining up where it came from', () => {
        // Ask for email before the name.
        const flow = moveStep(msp, 'ask_email', { kind: 'answers', from: 'q_size', answers: ['small', 'large'] });

        expect(flow.nodes.q_size.options?.map((o) => o.next)).toEqual(['ask_email', 'ask_email']);
        expect(flow.nodes.ask_email.next).toBe('ask_name');
        expect(flow.nodes.ask_name.next).toBe('done');
    });

    it('changes nothing when a step is dropped where it already is', () => {
        expect(moveStep(msp, 'ask_name', { kind: 'answers', from: 'q_size', answers: ['small', 'large'] })).toBe(msp);
        expect(moveStep(msp, 'ask_name', { kind: 'exit', from: 'ask_name', exit: 'next' })).toBe(msp);
    });

    it('moves a question whose answers all lead the same way, like any other step', () => {
        // "How many staff?" first of all, before the welcome.
        const flow = moveStep(msp, 'q_size', { kind: 'start' });

        expect(flow.start).toBe('q_size');
        expect(flow.nodes.q_size.options?.map((o) => o.next)).toEqual(['welcome', 'welcome']);
        // Where it came from is joined up: the paths that met at it now meet at what followed it.
        expect(flow.nodes.q_service.options?.map((o) => o.next)).toEqual(['ask_name', 'ask_name', 'q_security', 'support_email']);
        expect(flow.nodes.q_security.options?.map((o) => o.next)).toEqual(['ask_name', 'ask_name']);
        expect(reachable(flow).size).toBe(Object.keys(flow.nodes).length);
    });

    it('moves a question that splits together with everything inside its paths', () => {
        // The whole "what can we help with" split goes after the name is asked.
        const flow = moveStep(msp, 'q_service', { kind: 'exit', from: 'ask_name', exit: 'next' });

        // Lifted out: the welcome now leads straight to where the split's paths used to meet.
        expect(flow.nodes.welcome.next).toBe('q_size');
        // Put down: the name leads into the split, and its paths meet at what the name used to lead to.
        expect(flow.nodes.ask_name.next).toBe('q_service');
        expect(flow.nodes.q_service.options?.map((o) => o.next)).toEqual(['ask_email', 'ask_email', 'q_security', 'support_email']);
        expect(flow.nodes.q_security.options?.map((o) => o.next)).toEqual(['ask_email', 'ask_email']);
        // The path that ends on its own went with it, untouched.
        expect(flow.nodes.support_email.next).toBe('end_support');
        expect(reachable(flow).size).toBe(Object.keys(flow.nodes).length);

        // And it still draws as one split that re-joins.
        const tree = buildTree(flow);
        expect(ids(tree.root)).toEqual(['welcome', 'q_size', 'ask_name', 'q_service', 'ask_email', 'done']);
    });

    it('will not put a step inside its own paths, or straight after itself', () => {
        // Into one of its own branches.
        expect(moveStep(msp, 'q_service', { kind: 'exit', from: 'support_email', exit: 'next' })).toBe(msp);
        expect(moveStep(msp, 'q_service', { kind: 'answers', from: 'q_security', answers: ['audit', 'mdr'] })).toBe(msp);
        // Where its own paths meet again is where it already is.
        const after = buildTree(msp).root.steps.find((s) => s.id === 'q_size')?.via;
        expect(after?.kind).toBe('join');
        expect(moveStep(msp, 'q_service', after as Slot)).toBe(msp);
    });

    it('moves a step out of a path to below where the paths meet', () => {
        // "What do you need?" sits on the security path; move it to after the split re-joins.
        const join = buildTree(msp).root.steps.find((s) => s.id === 'q_size')?.via as Slot;
        const flow = moveStep(msp, 'q_security', join);

        // The security answer now goes straight to it with everyone else...
        expect(flow.nodes.q_service.options?.map((o) => o.next)).toEqual(['q_security', 'q_security', 'q_security', 'support_email']);
        // ...and it leads on to what the paths used to meet at.
        expect(flow.nodes.q_security.options?.map((o) => o.next)).toEqual(['q_size', 'q_size']);
        expect(reachable(flow).size).toBe(Object.keys(flow.nodes).length);
    });

    it('only puts a step nothing can follow where nothing follows', () => {
        const flow: Flow = {
            start: 'hello',
            nodes: {
                hello: message('Hi!', 'pick'),
                pick: question('Which?', [
                    ['sales', 'end_sales'],
                    ['help', 'end_help'],
                ]),
                end_sales: finish('Sales will call.'),
                end_help: { type: 'end', outcome: 'support', text: 'Ticket opened.' },
            },
        };

        // Its two paths never meet again, so it cannot go in front of the greeting.
        expect(moveStep(flow, 'pick', { kind: 'start' })).toBe(flow);
    });

    it('keeps a booking step without a "no time works" path that way when it moves', () => {
        const flow: Flow = {
            start: 'hello',
            nodes: {
                hello: message('Hi!', 'ask'),
                ask: ask('email', 'book', 'email'),
                book: { type: 'booking', text: 'Pick a time:', next: 'done', fallback: null },
                done: finish(),
            },
        };

        const moved = moveStep(flow, 'book', { kind: 'exit', from: 'hello', exit: 'next' });

        expect(moved.nodes.hello.next).toBe('book');
        expect(moved.nodes.book).toMatchObject({ next: 'ask', fallback: null });
        expect(moved.nodes.ask.next).toBe('done');
    });

    it('keeps every step reachable after a move', () => {
        const flow = moveStep(msp, 'ask_name', { kind: 'exit', from: 'welcome', exit: 'next' });

        expect(targetOf(flow, { kind: 'exit', from: 'welcome', exit: 'next' })).toBe('ask_name');
        expect(flow.nodes.ask_name.next).toBe('q_service');
        expect(reachable(flow).size).toBe(Object.keys(flow.nodes).length);
    });
});

describe('editing on the card', () => {
    const root = buildTree(msp).root;

    it('knows the main way a question carries on: where its paths meet again', () => {
        expect(mainExit(root, msp, 'q_service')).toBe('q_size');
        expect(mainExit(root, msp, 'welcome')).toBe('q_service');
    });

    it('adds a reply that carries on the main way, with a name of its own', () => {
        const { flow, optionId } = addOption(msp, root, 'q_service');

        expect(optionId).toBe('answer_5');
        expect(flow.nodes.q_service.options?.at(-1)).toEqual({ id: 'answer_5', label: 'Option 5', score: 0, next: 'q_size' });
        expect(msp.nodes.q_service.options).toHaveLength(4);
    });

    it('removes a reply with the steps only it led to, and keeps a question’s last reply', () => {
        const flow = removeOption(msp, 'q_service', 'support');

        expect(flow.nodes.q_service.options?.map((o) => o.id)).toEqual(['managed', 'cloud', 'security']);
        expect(flow.nodes.support_email).toBeUndefined();
        expect(flow.nodes.end_support).toBeUndefined();

        const single: Flow = { start: 'q', nodes: { q: question('Sure?', [['yes', 'done']]), done: finish() } };
        expect(removeOption(single, 'q', 'yes')).toBe(single);
    });

    it('turns a message into a question whose two replies carry on where it went', () => {
        const flow = withQuickReplies(msp, 'welcome');

        expect(flow.nodes.welcome).toMatchObject({ type: 'choice', text: 'Hi!', field: 'welcome' });
        expect(flow.nodes.welcome.options?.map((o) => o.next)).toEqual(['q_service', 'q_service']);
        expect(withQuickReplies(msp, 'q_service')).toBe(msp);
    });

    it('duplicates a step just after itself', () => {
        const result = duplicateStep(msp, root, 'ask_name');
        if (!result) throw new Error('not duplicated');

        expect(result.flow.nodes.ask_name.next).toBe(result.id);
        expect(result.flow.nodes[result.id]).toMatchObject({ type: 'input', field: 'first_name', next: 'ask_email' });
        expect(reachable(result.flow).has(result.id)).toBe(true);
    });

    it('gives a copied question its own answer name, and never copies an ending', () => {
        const flow: Flow = { start: 'a', nodes: { a: { type: 'input', input: 'text', text: 'Why?', field: 'a', next: 'b' }, b: finish() } };
        const result = duplicateStep(flow, buildTree(flow).root, 'a');

        expect(result?.flow.nodes[result.id].field).toBe(result?.id);
        expect(duplicateStep(msp, root, 'done')).toBeNull();
    });

    it('copies a question with its replies but not its paths, below where they meet again', () => {
        const result = duplicateStep(msp, root, 'q_service');
        if (!result) throw new Error('not duplicated');

        const copy = result.flow.nodes[result.id];
        // Same replies, every one of them carrying on to what the paths met at.
        expect(copy.options?.map((o) => o.id)).toEqual(['managed', 'cloud', 'security', 'support']);
        expect(copy.options?.map((o) => o.next)).toEqual(['q_size', 'q_size', 'q_size', 'q_size']);
        // The original's paths now meet at the copy.
        expect(result.flow.nodes.q_service.options?.map((o) => o.next)).toEqual([result.id, result.id, 'q_security', 'support_email']);
        expect(reachable(result.flow).size).toBe(Object.keys(result.flow.nodes).length);
    });

    it('names a copy of a named step so the two can be told apart', () => {
        const flow: Flow = { start: 'a', nodes: { a: { ...message('Hi!', 'b'), name: 'Greeting' }, b: finish() } };
        const result = duplicateStep(flow, buildTree(flow).root, 'a');

        expect(result?.flow.nodes[result.id].name).toBe('Greeting (copy)');
    });

    it('counts the steps inside a question’s paths, however deep', () => {
        const place = locate(root, 'q_service');
        if (!place) throw new Error('not drawn');

        expect(stepsInside(place.branch.steps[place.index])).toBe(3);
    });
});
