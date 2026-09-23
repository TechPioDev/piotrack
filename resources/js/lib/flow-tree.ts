/**
 * The conversation builder's model. A conversation is saved as a graph - steps
 * that each point at the step that comes next - because that is what the chat
 * engine runs. People do not think in graphs, so the builder shows it as a tree
 * that reads top to bottom, the way the chat itself unfolds:
 *
 *   - steps follow each other down the page;
 *   - a question whose answers lead different ways splits into one branch per
 *     path, drawn under it; answers that lead the same way share a branch;
 *   - where branches meet again, the tree re-joins below them;
 *   - a path that leads back to a step already drawn shows "continues at".
 *
 * Every edit here - insert, move, delete - works on the graph directly, so the
 * owner never wires a "Then go to" by hand, and whatever the tree shows is
 * exactly what the engine will run. No React in this file: it is the part
 * worth testing to the last case.
 */

export type FlowOption = { id: string; label: string; score?: number; next?: string | null; priority?: string };
export type FlowNode = {
    type: string;
    text?: string;
    next?: string | null;
    otherwise?: string | null;
    field?: string;
    input?: string;
    optional?: boolean;
    options?: FlowOption[];
    points?: number;
    tag?: string;
    assignee_id?: number | null;
    outcome?: string;
    operator?: string;
    value?: string;
    /** booking/ai steps: where to go when the step cannot run (none: carry on as normal). */
    fallback?: string | null;
    /** message steps: seconds of typing dots before the line appears (0-10). */
    delay?: number;
};
export type Flow = { start: string | null; nodes: Record<string, FlowNode> };

/**
 * A place in the conversation a step can be put: the very start, one exit of a
 * step, a group of a question's answers, or - below a split - every path from
 * that split that re-joins at `target`.
 */
export type Slot =
    | { kind: 'start' }
    | { kind: 'exit'; from: string; exit: 'next' | 'otherwise' | 'fallback' }
    | { kind: 'answers'; from: string; answers: string[] }
    | { kind: 'join'; region: string[]; target: string | null };

export type TreeEnd =
    | { kind: 'open' }
    | { kind: 'finished' }
    /** Meets the other paths again at `to`, drawn below the split. */
    | { kind: 'joins'; to: string }
    | { kind: 'branched' }
    | { kind: 'jump'; to: string };

export type TreeStep = {
    id: string;
    node: FlowNode;
    /** The place this step sits in: dropping here puts a new step just before it. */
    via: Slot;
    /** For a step that splits the conversation: one branch per distinct path. */
    branches: TreeBranch[];
    /** Where those branches meet again, if they do. */
    join: string | null;
};

export type TreeBranch = {
    slot: Slot;
    /** For a branch under a question: the answers that lead here. */
    label: string | null;
    steps: TreeStep[];
    end: TreeEnd;
    /** Where a step dropped at the end of this branch goes (none once the branch has split or finished). */
    endSlot: Slot | null;
};

/** Steps that simply lead on to one next step, and so can be moved anywhere. */
export const LINEAR_TYPES = ['message', 'input', 'score', 'tag', 'assign', 'handoff'];

type Edge = { key: string; target: string | null };

function edges(node: FlowNode): Edge[] {
    switch (node.type) {
        case 'end':
            return [];
        case 'choice':
            return (node.options ?? []).map((o) => ({ key: `option:${o.id}`, target: o.next ?? null }));
        case 'condition':
            return [
                { key: 'next', target: node.next ?? null },
                { key: 'otherwise', target: node.otherwise ?? null },
            ];
        case 'booking':
        case 'ai':
            return [
                { key: 'next', target: node.next ?? null },
                { key: 'fallback', target: node.fallback ?? null },
            ];
        default:
            return [{ key: 'next', target: node.next ?? null }];
    }
}

function withEdge(node: FlowNode, key: string, target: string | null): FlowNode {
    if (key.startsWith('option:')) {
        const id = key.slice('option:'.length);
        return { ...node, options: (node.options ?? []).map((o) => (o.id === id ? { ...o, next: target } : o)) };
    }
    return { ...node, [key]: target };
}

/** Every step the conversation can actually reach from its start. */
export function reachable(flow: Flow): Set<string> {
    const seen = new Set<string>();
    const queue = flow.start && flow.nodes[flow.start] ? [flow.start] : [];
    while (queue.length > 0) {
        const id = queue.shift() as string;
        if (seen.has(id)) continue;
        seen.add(id);
        for (const edge of edges(flow.nodes[id])) {
            if (edge.target && flow.nodes[edge.target] && !seen.has(edge.target)) queue.push(edge.target);
        }
    }
    return seen;
}

/** The step a place currently leads to. */
export function targetOf(flow: Flow, slot: Slot): string | null {
    switch (slot.kind) {
        case 'start':
            return flow.start;
        case 'exit':
            return flow.nodes[slot.from]?.[slot.exit] ?? null;
        case 'answers': {
            const option = (flow.nodes[slot.from]?.options ?? []).find((o) => slot.answers.includes(o.id));
            return option?.next ?? null;
        }
        case 'join':
            return slot.target;
    }
}

/** Point a place at a different step. */
export function withTarget(flow: Flow, slot: Slot, target: string | null): Flow {
    switch (slot.kind) {
        case 'start':
            return { ...flow, start: target };
        case 'exit': {
            const node = flow.nodes[slot.from];
            return node ? { ...flow, nodes: { ...flow.nodes, [slot.from]: { ...node, [slot.exit]: target } } } : flow;
        }
        case 'answers': {
            const node = flow.nodes[slot.from];
            if (!node) return flow;
            const options = (node.options ?? []).map((o) => (slot.answers.includes(o.id) ? { ...o, next: target } : o));
            return { ...flow, nodes: { ...flow.nodes, [slot.from]: { ...node, options } } };
        }
        case 'join': {
            // Every path out of the split that met at the old target now meets at the new one.
            const nodes = { ...flow.nodes };
            for (const id of slot.region) {
                let node = nodes[id];
                if (!node) continue;
                for (const edge of edges(node)) {
                    if (edge.target === slot.target) node = withEdge(node, edge.key, target);
                }
                nodes[id] = node;
            }
            return { ...flow, nodes };
        }
    }
}

type Path = { slot: Slot; label: string | null; target: string | null };

/** The distinct ways a step can continue. One path means it simply leads on. */
function pathsOf(id: string, node: FlowNode): Path[] {
    if (node.type === 'end') return [];

    if (node.type === 'choice') {
        const groups = new Map<string, FlowOption[]>();
        for (const option of node.options ?? []) {
            const key = option.next ?? '';
            groups.set(key, [...(groups.get(key) ?? []), option]);
        }
        return [...groups.entries()].map(([key, options]) => ({
            slot: { kind: 'answers', from: id, answers: options.map((o) => o.id) },
            label: options.map((o) => o.label).join(' · '),
            target: key === '' ? null : key,
        }));
    }

    if (node.type === 'condition') {
        const yes = node.next ?? null;
        const no = node.otherwise ?? null;
        if (yes === no) return [{ slot: { kind: 'join', region: [id], target: yes }, label: null, target: yes }];
        return [
            { slot: { kind: 'exit', from: id, exit: 'next' }, label: 'If it matches', target: yes },
            { slot: { kind: 'exit', from: id, exit: 'otherwise' }, label: 'Otherwise', target: no },
        ];
    }

    if (node.type === 'booking' || node.type === 'ai') {
        const next = node.next ?? null;
        const fallback = node.fallback ?? null;
        // No fallback means "carry on as normal" to the engine: one path.
        if (fallback === null) return [{ slot: { kind: 'exit', from: id, exit: 'next' }, label: null, target: next }];
        if (fallback === next) return [{ slot: { kind: 'join', region: [id], target: next }, label: null, target: next }];
        return [
            { slot: { kind: 'exit', from: id, exit: 'next' }, label: node.type === 'booking' ? 'After booking' : 'After answering', target: next },
            {
                slot: { kind: 'exit', from: id, exit: 'fallback' },
                label: node.type === 'booking' ? 'If no time works' : 'If the AI cannot answer',
                target: fallback,
            },
        ];
    }

    return [{ slot: { kind: 'exit', from: id, exit: 'next' }, label: null, target: node.next ?? null }];
}

function distances(flow: Flow, start: string | null, blocked: Set<string>): Map<string, number> {
    const dist = new Map<string, number>();
    if (start === null || !flow.nodes[start] || blocked.has(start)) return dist;
    const queue: string[] = [start];
    dist.set(start, 0);
    while (queue.length > 0) {
        const id = queue.shift() as string;
        for (const edge of edges(flow.nodes[id])) {
            const t = edge.target;
            if (t && flow.nodes[t] && !blocked.has(t) && !dist.has(t)) {
                dist.set(t, (dist.get(id) ?? 0) + 1);
                queue.push(t);
            }
        }
    }
    return dist;
}

/**
 * Where the branches of a split meet again: the step reached by the most
 * branches (at least two), nearest first. Branches that never get there -
 * a support path that ends on its own - simply finish inside the split.
 */
function findJoin(flow: Flow, starts: (string | null)[], blocked: Set<string>): string | null {
    const tally = new Map<string, { count: number; max: number; sum: number }>();
    for (const start of starts) {
        for (const [id, d] of distances(flow, start, blocked)) {
            const t = tally.get(id) ?? { count: 0, max: 0, sum: 0 };
            tally.set(id, { count: t.count + 1, max: Math.max(t.max, d), sum: t.sum + d });
        }
    }
    let best: string | null = null;
    let score: { count: number; max: number; sum: number } | null = null;
    for (const [id, t] of tally) {
        if (t.count < 2) continue;
        if (!score || t.count > score.count || (t.count === score.count && (t.max < score.max || (t.max === score.max && t.sum < score.sum)))) {
            best = id;
            score = t;
        }
    }
    return best;
}

/** The conversation as a tree, plus any steps nothing leads to. */
export function buildTree(flow: Flow): { root: TreeBranch; unreachable: string[] } {
    const placed = new Set<string>();

    const walk = (slot: Slot, label: string | null, stops: Set<string>): TreeBranch => {
        const steps: TreeStep[] = [];
        let via: Slot = slot;
        let target = targetOf(flow, slot);
        let end: TreeEnd;

        for (;;) {
            if (target === null || !flow.nodes[target]) {
                end = { kind: 'open' };
                break;
            }
            if (stops.has(target)) {
                end = { kind: 'joins', to: target };
                break;
            }
            if (placed.has(target)) {
                end = { kind: 'jump', to: target };
                break;
            }

            const id: string = target;
            const node = flow.nodes[id];
            placed.add(id);

            if (node.type === 'end') {
                steps.push({ id, node, via, branches: [], join: null });
                end = { kind: 'finished' };
                break;
            }

            const paths = pathsOf(id, node);
            if (paths.length <= 1) {
                steps.push({ id, node, via, branches: [], join: null });
                if (paths.length === 0) {
                    // A question with no answers yet: nothing can follow it.
                    end = { kind: 'branched' };
                    break;
                }
                via = paths[0].slot;
                target = paths[0].target;
                continue;
            }

            const join = findJoin(
                flow,
                paths.map((p) => p.target),
                new Set([...stops, ...placed]),
            );
            const inner = join ? new Set([...stops, join]) : stops;
            const before = new Set(placed);
            const branches = paths.map((p) => walk(p.slot, p.label, inner));
            steps.push({ id, node, via, branches, join });

            if (!join) {
                end = { kind: 'branched' };
                break;
            }
            const region = [id, ...[...placed].filter((n) => !before.has(n))];
            via = { kind: 'join', region, target: join };
            target = join;
        }

        const openEnded = end.kind === 'open' || end.kind === 'joins' || end.kind === 'jump';
        return { slot, label, steps, end, endSlot: openEnded ? via : null };
    };

    const root = walk({ kind: 'start' }, null, new Set());
    const unreachable = Object.keys(flow.nodes).filter((id) => !placed.has(id));
    return { root, unreachable };
}

/** A readable, unique id for a new step, so the saved graph stays legible. */
export function newStepId(node: FlowNode, nodes: Record<string, FlowNode>): string {
    const base =
        node.type === 'input' && node.field
            ? `ask_${node.field.replace(/[^a-z0-9_]+/gi, '_').toLowerCase()}`
            : node.type === 'choice'
              ? 'question'
              : node.type;
    if (!nodes[base] && node.type === 'input') return base;
    let n = 1;
    while (nodes[`${base}_${n}`]) n += 1;
    return `${base}_${n}`;
}

/** Whether a step of this type can go here: a Finish only where nothing follows. */
export function canInsert(flow: Flow, slot: Slot, type: string): boolean {
    return type !== 'end' || targetOf(flow, slot) === null;
}

/**
 * Put a new step in a place. Whatever that place led to now follows the new
 * step instead - every answer of a new question leads on to it, and a new
 * booking or AI step carries on to it too.
 */
export function insertStep(flow: Flow, slot: Slot, block: FlowNode): { flow: Flow; id: string } {
    const id = newStepId(block, flow.nodes);
    const next = targetOf(flow, slot);
    let node: FlowNode = { ...block };
    // A question saves its answer under its own name unless it is a contact
    // field, so nobody has to invent one - and the answer reaches the lead.
    if ((node.type === 'choice' || node.type === 'input') && !node.field) node = { ...node, field: id };

    switch (node.type) {
        case 'end':
            break;
        case 'choice':
            node = { ...node, options: (node.options ?? []).map((o) => ({ ...o, next })) };
            break;
        case 'condition':
            node = { ...node, next, otherwise: next };
            break;
        case 'booking':
        case 'ai':
            node = { ...node, next, fallback: null };
            break;
        default:
            node = { ...node, next };
    }

    const added: Flow = { ...flow, nodes: { ...flow.nodes, [id]: node } };
    return { flow: withTarget(added, slot, id), id };
}

/** Every exit anywhere that led to `from` now leads to `to`. */
function repoint(flow: Flow, from: string, to: string | null): Flow {
    const nodes: Record<string, FlowNode> = {};
    for (const [id, node] of Object.entries(flow.nodes)) {
        let updated = node;
        for (const edge of edges(node)) {
            if (edge.target === from) updated = withEdge(updated, edge.key, to);
        }
        nodes[id] = updated;
    }
    return { start: flow.start === from ? to : flow.start, nodes };
}

/** Where the conversation should carry on once a step is taken out, if anywhere. */
function continuation(node: FlowNode): string | null {
    if (node.type === 'end') return null;
    const targets = [...new Set(edges(node).map((e) => e.target))];
    if (node.type === 'booking' || node.type === 'ai') return node.next ?? null;
    return targets.length === 1 ? targets[0] : null;
}

/**
 * Take a step out. Its neighbours are joined up, so a step in the middle of a
 * path leaves no gap. A question whose answers led different ways takes the
 * steps only those answers led to with it - `removed` lists them all, so the
 * builder can say how many before it happens.
 */
export function removeStep(flow: Flow, id: string): { flow: Flow; removed: string[] } {
    const node = flow.nodes[id];
    if (!node) return { flow, removed: [] };

    const before = reachable(flow);
    const joined = repoint(flow, id, continuation(node));
    const nodes = { ...joined.nodes };
    delete nodes[id];
    let result: Flow = { ...joined, nodes };

    const after = reachable(result);
    const stranded = [...before].filter((n) => n !== id && !after.has(n));
    if (stranded.length > 0) {
        const kept = { ...result.nodes };
        for (const n of stranded) delete kept[n];
        result = { ...result, nodes: kept };
    }
    return { flow: result, removed: [id, ...stranded] };
}

/** Whether a step can be dragged somewhere else: the ones that simply lead on. */
export function isMovable(node: FlowNode | undefined): boolean {
    return node !== undefined && LINEAR_TYPES.includes(node.type);
}

/**
 * Move a step to another place: lifted out (its neighbours joined up), then
 * put in the new place as if dropped there. Dropping a step just before or
 * just after itself changes nothing.
 */
export function moveStep(flow: Flow, id: string, slot: Slot): Flow {
    const node = flow.nodes[id];
    if (!isMovable(node)) return flow;
    if (targetOf(flow, slot) === id) return flow;
    if ((slot.kind === 'exit' || slot.kind === 'answers') && slot.from === id) return flow;
    if (slot.kind === 'join' && (slot.target === id || (slot.region.length === 1 && slot.region[0] === id))) return flow;

    const lifted = repoint(flow, id, node.next ?? null);
    const place: Slot = slot.kind === 'join' ? { ...slot, region: slot.region.filter((n) => n !== id) } : slot;
    const next = targetOf(lifted, place);
    const moved: Flow = { ...lifted, nodes: { ...lifted.nodes, [id]: { ...node, next } } };
    return withTarget(moved, place, id);
}

/** Where a step is drawn: its branch, and its position in that branch. */
export function locate(branch: TreeBranch, id: string): { branch: TreeBranch; index: number } | null {
    for (let index = 0; index < branch.steps.length; index++) {
        const step = branch.steps[index];
        if (step.id === id) return { branch, index };
        for (const inner of step.branches) {
            const found = locate(inner, id);
            if (found) return found;
        }
    }
    return null;
}

/**
 * The place just after a step, for "add after the selected step". Below a
 * split that re-joins it is the re-join point; a Finish, or a split whose
 * paths never meet again, has nothing after it.
 */
export function afterSlot(root: TreeBranch, id: string): Slot | null {
    const found = locate(root, id);
    if (!found) return null;
    const { branch, index } = found;
    const step = branch.steps[index];
    if (step.node.type === 'end' || (step.branches.length > 0 && !step.join)) return null;
    return branch.steps[index + 1]?.via ?? branch.endSlot;
}

/** Drop the steps an edit cut off: reachable before it, unreachable after. */
export function withoutStranded(before: Flow, after: Flow): Flow {
    const was = reachable(before);
    const is = reachable(after);
    const nodes = { ...after.nodes };
    for (const id of was) {
        if (!is.has(id)) delete nodes[id];
    }
    return { ...after, nodes };
}

/** Where a step's answers carry on when they do not go a way of their own. */
export function mainExit(root: TreeBranch, flow: Flow, id: string): string | null {
    const node = flow.nodes[id];
    if (!node) return null;
    const place = locate(root, id);
    const step = place ? place.branch.steps[place.index] : null;
    if (step && step.branches.length > 0) return step.join;
    return node.options?.[0]?.next ?? node.next ?? null;
}

/** A new reply on a question, carrying on the main way. */
export function addOption(flow: Flow, root: TreeBranch, id: string): { flow: Flow; optionId: string } {
    const node = flow.nodes[id];
    const options = node.options ?? [];
    const taken = new Set(options.map((o) => o.id));
    let n = options.length + 1;
    while (taken.has(`answer_${n}`)) n += 1;
    const optionId = `answer_${n}`;
    const option: FlowOption = { id: optionId, label: `Option ${n}`, score: 0, next: mainExit(root, flow, id) };
    return { flow: { ...flow, nodes: { ...flow.nodes, [id]: { ...node, options: [...options, option] } } }, optionId };
}

/** Take a reply off a question, with the steps only it led to. A question keeps its last reply. */
export function removeOption(flow: Flow, id: string, optionId: string): Flow {
    const node = flow.nodes[id];
    const options = node?.options ?? [];
    if (options.length <= 1 || !options.some((o) => o.id === optionId)) return flow;
    return withoutStranded(flow, { ...flow, nodes: { ...flow.nodes, [id]: { ...node, options: options.filter((o) => o.id !== optionId) } } });
}

/** Quick replies on a message: it becomes a question whose replies all carry on where it went. */
export function withQuickReplies(flow: Flow, id: string): Flow {
    const node = flow.nodes[id];
    if (node?.type !== 'message') return flow;
    const next = node.next ?? null;
    return {
        ...flow,
        nodes: {
            ...flow.nodes,
            [id]: {
                type: 'choice',
                text: node.text,
                field: node.field || id,
                options: [
                    { id: 'answer_1', label: 'Option 1', score: 0, next },
                    { id: 'answer_2', label: 'Option 2', score: 0, next },
                ],
            },
        },
    };
}

/** A copy of a step, just after it. Only steps that simply lead on can be copied. */
export function duplicateStep(flow: Flow, root: TreeBranch, id: string): { flow: Flow; id: string } | null {
    const node = flow.nodes[id];
    if (!isMovable(node)) return null;
    const slot = afterSlot(root, id);
    if (!slot) return null;
    const copy: FlowNode = { ...node };
    delete copy.next;
    // An answer saved under the step's own name gets the copy's name instead.
    if (copy.field === id) delete copy.field;
    return insertStep(flow, slot, copy);
}

/** How many steps sit inside a step's paths, however deep. */
export function stepsInside(step: TreeStep): number {
    return step.branches.reduce((sum, branch) => sum + branch.steps.reduce((n, inner) => n + 1 + stepsInside(inner), 0), 0);
}

/** Two places are the same place. Used to tell which gap a dragged step came from. */
export function sameSlot(a: Slot, b: Slot): boolean {
    return JSON.stringify(a) === JSON.stringify(b);
}

/** A short, readable line for a step: what the visitor sees, or what it does. */
export function describe(node: FlowNode): string {
    switch (node.type) {
        case 'score':
            return `${(node.points ?? 0) >= 0 ? '+' : ''}${node.points ?? 0} lead score`;
        case 'tag':
            return node.tag ? `Tag “${node.tag}”` : 'Tag (none set)';
        case 'assign':
            return node.assignee_id ? 'Route to a chosen salesperson' : 'Route automatically';
        case 'condition':
            return node.field
                ? `If “${node.field}” ${node.operator === 'is_set' ? 'is answered' : `${node.operator ?? 'is'} ${node.value ?? ''}`.trim()}`
                : 'If … (not set)';
        case 'handoff':
            return 'Connects the visitor to your team';
        default:
            return node.text?.trim() || 'No text yet';
    }
}
