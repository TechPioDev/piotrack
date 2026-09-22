import { describe, expect, it } from 'vitest';

import { buildTree, canInsert, type Flow, type FlowNode, insertStep, moveStep, reachable, removeStep, targetOf, type TreeBranch } from './flow-tree';

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
        expect(straight.end).toEqual({ kind: 'joins' });
        // A path with its own questions shows them, then re-joins.
        expect(ids(security)).toEqual(['q_security']);
        expect(security.end).toEqual({ kind: 'joins' });
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

    it('leaves questions where they are, since their answers carry paths', () => {
        expect(moveStep(msp, 'q_size', { kind: 'start' })).toBe(msp);
    });

    it('keeps every step reachable after a move', () => {
        const flow = moveStep(msp, 'ask_name', { kind: 'exit', from: 'welcome', exit: 'next' });

        expect(targetOf(flow, { kind: 'exit', from: 'welcome', exit: 'next' })).toBe('ask_name');
        expect(flow.nodes.ask_name.next).toBe('q_service');
        expect(reachable(flow).size).toBe(Object.keys(flow.nodes).length);
    });
});
