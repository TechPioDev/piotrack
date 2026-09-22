import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { CONTACT_FIELDS, stepKind } from '@/lib/flow-blocks';
import {
    describe,
    type Flow,
    type FlowNode,
    type FlowOption,
    insertStep,
    isMovable,
    locate,
    reachable,
    type Slot,
    type TreeBranch,
    withoutStranded,
    withTarget,
} from '@/lib/flow-tree';
import { ArrowDown, ArrowUp, Flame, Plus, Trash2, X } from 'lucide-react';
import { StepIcon, stepVisual } from './step-visuals';

type Assignee = { id: number; name: string };

const OPERATORS = [
    { id: 'equals', label: 'is' },
    { id: 'not_equals', label: 'is not' },
    { id: 'contains', label: 'contains' },
    { id: 'is_set', label: 'was answered' },
    { id: 'gte', label: 'is at least' },
    { id: 'lte', label: 'is at most' },
];

const MAIN = '__main';
const OWN = '__own';

/**
 * Where one way out of a step leads: on with the rest of the conversation, a
 * path of its own (started with a message to edit, so each has a branch in
 * the tree), or straight to another step - the "go to" that loops or skips.
 */
function PathSelect({
    flow,
    self,
    value,
    main,
    mainLabel,
    onChoose,
    label,
}: {
    flow: Flow;
    self: string;
    value: string | null;
    main: string | null;
    mainLabel: string;
    onChoose: (choice: string) => void;
    label: string;
}) {
    const current = value === main ? MAIN : value;
    const others = [...reachable(flow)].filter((id) => id !== self && id !== main && id !== value);

    return (
        <Select value={current ?? MAIN} onValueChange={onChoose}>
            <SelectTrigger aria-label={label} className="h-8 text-xs">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={MAIN}>{mainLabel}</SelectItem>
                {current !== MAIN && current !== null && (
                    <SelectItem value={current}>Its own path: {describe(flow.nodes[current] ?? { type: 'message' }).slice(0, 36)}</SelectItem>
                )}
                <SelectItem value={OWN}>A new path of its own</SelectItem>
                {others.map((id) => (
                    <SelectItem key={id} value={id}>
                        Go to {stepKind(flow.nodes[id]).toLowerCase()}: {describe(flow.nodes[id]).slice(0, 36)}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

/** Apply a path choice to one way out of a step, tidying any path it cut off. */
function choosePath(flow: Flow, slot: Slot, choice: string, main: string | null, placeholder: string): Flow {
    if (choice === OWN) return withoutStranded(flow, insertStep(withTarget(flow, slot, main), slot, { type: 'message', text: placeholder }).flow);
    return withoutStranded(flow, withTarget(flow, slot, choice === MAIN ? main : choice));
}

export function StepSettings({
    flow,
    id,
    root,
    assignees,
    onPatch,
    onApply,
    onDelete,
    onMove,
    onClose,
}: {
    flow: Flow;
    id: string;
    root: TreeBranch;
    assignees: Assignee[];
    onPatch: (patch: Partial<FlowNode>, mergeKey?: string) => void;
    onApply: (next: Flow) => void;
    onDelete: () => void;
    onMove: (direction: 'up' | 'down') => void;
    onClose: () => void;
}) {
    const node = flow.nodes[id];
    if (!node) return null;

    const place = locate(root, id);
    const step = place ? place.branch.steps[place.index] : null;
    const canMoveUp = isMovable(node) && place !== null && place.index > 0 && isMovable(place.branch.steps[place.index - 1].node);
    const canMoveDown =
        isMovable(node) && place !== null && place.index < place.branch.steps.length - 1 && isMovable(place.branch.steps[place.index + 1].node);

    // Where this step's answers carry on when they do not go their own way.
    const main = step?.branches.length ? step.join : (node.options?.[0]?.next ?? node.next ?? null);
    const mainLabel = main ? `Carry on: ${describe(flow.nodes[main] ?? { type: 'message' }).slice(0, 30)}` : 'Carry on (nothing follows yet)';

    const fields = collectFields(flow, id);

    return (
        <div className="space-y-4">
            <div className="flex items-start gap-3">
                <StepIcon visual={stepVisual(node)} />
                <div className="min-w-0 flex-1">
                    <h2 className="text-foreground text-sm font-semibold">{stepKind(node)}</h2>
                    <p className="text-muted-foreground text-xs">Changes show in the tree straight away.</p>
                </div>
                <Button size="sm" variant="ghost" onClick={onClose} aria-label="Close step settings">
                    <X className="size-4" aria-hidden />
                </Button>
            </div>

            {['message', 'choice', 'input', 'end', 'booking', 'ai'].includes(node.type) && (
                <div className="grid gap-1">
                    <Label htmlFor="step-text">
                        {node.type === 'end' ? 'Closing message' : node.type === 'message' ? 'Message' : 'What the chat asks'}
                    </Label>
                    <textarea
                        id="step-text"
                        rows={node.type === 'message' ? 3 : 2}
                        className="border-input bg-background focus-visible:ring-brand w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                        value={node.text ?? ''}
                        onChange={(e) => onPatch({ text: e.target.value }, `${id}:text`)}
                    />
                </div>
            )}

            {node.type === 'input' && (
                <div className="space-y-3">
                    <label className="flex items-center justify-between gap-3 rounded-lg border p-3">
                        <span>
                            <span className="text-foreground block text-sm font-medium">Required</span>
                            <span className="text-muted-foreground block text-xs">
                                {node.optional ? 'Visitors see a “Skip this” button.' : 'Visitors must answer to carry on.'}
                            </span>
                        </span>
                        <input
                            type="checkbox"
                            role="switch"
                            className="size-4 accent-[var(--brand)]"
                            checked={!node.optional}
                            onChange={(e) => onPatch({ optional: !e.target.checked })}
                        />
                    </label>
                    {node.field && CONTACT_FIELDS[node.field] ? (
                        <p className="text-muted-foreground text-xs">
                            Saved to the lead as <span className="text-foreground font-medium">{CONTACT_FIELDS[node.field]}</span>
                            {node.input === 'email'
                                ? ', and checked as a real email address.'
                                : node.input === 'phone'
                                  ? ', and checked as a phone number.'
                                  : '.'}
                        </p>
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="grid gap-1">
                                <Label>Answer type</Label>
                                <Select value={node.input ?? 'text'} onValueChange={(v) => onPatch({ input: v })}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="text">Text</SelectItem>
                                        <SelectItem value="number">Number</SelectItem>
                                        <SelectItem value="email">Email address</SelectItem>
                                        <SelectItem value="phone">Phone number</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <SavedAs id={id} node={node} onPatch={onPatch} />
                        </div>
                    )}
                </div>
            )}

            {node.type === 'choice' && (
                <div className="space-y-2">
                    <Label>Answers</Label>
                    {(node.options ?? []).map((option, index) => (
                        <AnswerRow
                            key={option.id}
                            flow={flow}
                            stepId={id}
                            option={option}
                            main={main}
                            mainLabel={mainLabel}
                            removable={(node.options ?? []).length > 1}
                            onChange={(patch) =>
                                onPatch(
                                    { options: (node.options ?? []).map((o, i) => (i === index ? { ...o, ...patch } : o)) },
                                    `${id}:option:${option.id}:${Object.keys(patch).join(',')}`,
                                )
                            }
                            onPath={(choice) =>
                                onApply(
                                    choosePath(
                                        flow,
                                        { kind: 'answers', from: id, answers: [option.id] },
                                        choice,
                                        main,
                                        `Say something to people who choose “${option.label}”.`,
                                    ),
                                )
                            }
                            onRemove={() =>
                                onApply(
                                    withoutStranded(flow, {
                                        ...flow,
                                        nodes: { ...flow.nodes, [id]: { ...node, options: (node.options ?? []).filter((o) => o.id !== option.id) } },
                                    }),
                                )
                            }
                        />
                    ))}
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => {
                            const taken = new Set((node.options ?? []).map((o) => o.id));
                            let n = (node.options ?? []).length + 1;
                            while (taken.has(`answer_${n}`)) n += 1;
                            onPatch({ options: [...(node.options ?? []), { id: `answer_${n}`, label: `Answer ${n}`, score: 0, next: main }] });
                        }}
                    >
                        <Plus className="size-3.5" aria-hidden /> Add answer
                    </Button>
                    <SavedAs id={id} node={node} onPatch={onPatch} />
                </div>
            )}

            {node.type === 'condition' && (
                <div className="space-y-3">
                    <div className="grid gap-1">
                        <Label>Check the answer to</Label>
                        <Select value={node.field || '__none'} onValueChange={(v) => onPatch({ field: v === '__none' ? '' : v, value: '' })}>
                            <SelectTrigger>
                                <SelectValue placeholder="Choose a question" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none">Choose a question…</SelectItem>
                                {fields.map((f) => (
                                    <SelectItem key={f.field} value={f.field}>
                                        {f.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="grid gap-1">
                            <Label>Test</Label>
                            <Select value={node.operator ?? 'equals'} onValueChange={(v) => onPatch({ operator: v })}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {OPERATORS.map((o) => (
                                        <SelectItem key={o.id} value={o.id}>
                                            {o.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        {node.operator !== 'is_set' && (
                            <div className="grid gap-1">
                                <Label htmlFor="cond-value">Value</Label>
                                {fields.find((f) => f.field === node.field)?.answers ? (
                                    <Select value={node.value || '__none'} onValueChange={(v) => onPatch({ value: v === '__none' ? '' : v })}>
                                        <SelectTrigger id="cond-value">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="__none">Choose an answer…</SelectItem>
                                            {fields
                                                .find((f) => f.field === node.field)
                                                ?.answers?.map((a) => (
                                                    <SelectItem key={a.id} value={a.id}>
                                                        {a.label}
                                                    </SelectItem>
                                                ))}
                                        </SelectContent>
                                    </Select>
                                ) : (
                                    <Input
                                        id="cond-value"
                                        value={node.value ?? ''}
                                        onChange={(e) => onPatch({ value: e.target.value }, `${id}:value`)}
                                    />
                                )}
                            </div>
                        )}
                    </div>
                    <div className="grid gap-1">
                        <Label>When it does not match</Label>
                        <PathSelect
                            flow={flow}
                            self={id}
                            value={node.otherwise ?? null}
                            main={node.next ?? null}
                            mainLabel="Carry on the same way"
                            label="When it does not match"
                            onChoose={(choice) =>
                                onApply(
                                    choosePath(
                                        flow,
                                        { kind: 'exit', from: id, exit: 'otherwise' },
                                        choice,
                                        node.next ?? null,
                                        'Say something for this case.',
                                    ),
                                )
                            }
                        />
                    </div>
                </div>
            )}

            {(node.type === 'booking' || node.type === 'ai') && (
                <div className="space-y-2">
                    <p className="text-muted-foreground text-sm">
                        {node.type === 'booking'
                            ? 'Shows the next free times from your booking page as buttons and books the one they pick. Put it after the Email step, so the confirmation has somewhere to go.'
                            : 'Visitors type a question and the AI answers from your company details, never inventing prices or promises. Uses your plan’s AI credits.'}
                    </p>
                    <div className="grid gap-1">
                        <Label>{node.type === 'booking' ? 'If no time works for them' : 'If the AI cannot answer'}</Label>
                        <PathSelect
                            flow={flow}
                            self={id}
                            value={node.fallback ?? node.next ?? null}
                            main={node.next ?? null}
                            mainLabel="Carry on as normal"
                            label={node.type === 'booking' ? 'If no time works for them' : 'If the AI cannot answer'}
                            onChoose={(choice) => {
                                if (choice === MAIN) {
                                    onApply(withoutStranded(flow, { ...flow, nodes: { ...flow.nodes, [id]: { ...node, fallback: null } } }));
                                    return;
                                }
                                onApply(
                                    choosePath(
                                        flow,
                                        { kind: 'exit', from: id, exit: 'fallback' },
                                        choice,
                                        node.next ?? null,
                                        node.type === 'booking'
                                            ? 'No problem, you can also book on our website.'
                                            : 'Let me take your details and a person will reply.',
                                    ),
                                );
                            }}
                        />
                    </div>
                </div>
            )}

            {node.type === 'handoff' && (
                <p className="text-muted-foreground text-sm">
                    If someone from your team is online and it is within business hours, the visitor is connected to them. Otherwise they are told
                    when to expect a reply, and the steps below carry on collecting their details.
                </p>
            )}

            {node.type === 'score' && (
                <div className="grid gap-1 sm:max-w-xs">
                    <Label htmlFor="points">Points to add</Label>
                    <Input
                        id="points"
                        type="number"
                        value={node.points ?? 0}
                        onChange={(e) => onPatch({ points: Number(e.target.value) }, `${id}:points`)}
                    />
                    <p className="text-muted-foreground text-xs">Visitors who reach this step score higher, so hot leads rise to the top.</p>
                </div>
            )}

            {node.type === 'tag' && (
                <div className="grid gap-1 sm:max-w-sm">
                    <Label htmlFor="tag">Tag</Label>
                    <Input id="tag" value={node.tag ?? ''} onChange={(e) => onPatch({ tag: e.target.value }, `${id}:tag`)} placeholder="interested" />
                </div>
            )}

            {node.type === 'assign' && (
                <div className="grid gap-1 sm:max-w-sm">
                    <Label>Send the lead to</Label>
                    <Select
                        value={node.assignee_id ? String(node.assignee_id) : '__none'}
                        onValueChange={(v) => onPatch({ assignee_id: v === '__none' ? null : Number(v) })}
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__none">Whoever your routing rules pick</SelectItem>
                            {assignees.map((a) => (
                                <SelectItem key={a.id} value={String(a.id)}>
                                    {a.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            )}

            {node.type === 'end' && (
                <div className="grid gap-1 sm:max-w-sm">
                    <Label>What happens at the end</Label>
                    <Select value={node.outcome ?? 'lead'} onValueChange={(v) => onPatch({ outcome: v })}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="lead">Save them as a new lead</SelectItem>
                            <SelectItem value="meeting">Save the lead and offer a meeting</SelectItem>
                            <SelectItem value="support">Open a support ticket (existing customer, no lead)</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            )}

            <div className="border-border flex flex-wrap items-center gap-2 border-t pt-3">
                {isMovable(node) && (
                    <>
                        <Button size="sm" variant="outline" onClick={() => onMove('up')} disabled={!canMoveUp}>
                            <ArrowUp className="size-3.5" aria-hidden /> Move up
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => onMove('down')} disabled={!canMoveDown}>
                            <ArrowDown className="size-3.5" aria-hidden /> Move down
                        </Button>
                    </>
                )}
                <Button size="sm" variant="outline" className="ml-auto text-red-600 dark:text-red-400" onClick={onDelete}>
                    <Trash2 className="size-3.5" aria-hidden /> Delete step
                </Button>
            </div>
        </div>
    );
}

function AnswerRow({
    flow,
    stepId,
    option,
    main,
    mainLabel,
    removable,
    onChange,
    onPath,
    onRemove,
}: {
    flow: Flow;
    stepId: string;
    option: FlowOption;
    main: string | null;
    mainLabel: string;
    removable: boolean;
    onChange: (patch: Partial<FlowOption>) => void;
    onPath: (choice: string) => void;
    onRemove: () => void;
}) {
    const urgent = option.priority === 'high';

    return (
        <div className="border-border space-y-2 rounded-lg border p-2.5">
            <div className="flex items-center gap-1.5">
                <Input
                    value={option.label}
                    onChange={(e) => onChange({ label: e.target.value })}
                    placeholder="Answer text"
                    aria-label="Answer text"
                    className="h-8"
                />
                <Input
                    type="number"
                    value={option.score ?? 0}
                    onChange={(e) => onChange({ score: Number(e.target.value) })}
                    aria-label="Lead score points for this answer"
                    title="Lead score points for this answer"
                    className="h-8 w-16 tabular-nums"
                />
                <Button
                    size="sm"
                    variant={urgent ? 'default' : 'outline'}
                    className="h-8 px-2"
                    aria-pressed={urgent}
                    aria-label="Urgent: flag the conversation as a priority"
                    title="Urgent: flag the conversation as a priority"
                    onClick={() => onChange({ priority: urgent ? undefined : 'high' })}
                >
                    <Flame className="size-3.5" aria-hidden />
                </Button>
                <Button
                    size="sm"
                    variant="ghost"
                    className="h-8 px-2"
                    onClick={onRemove}
                    disabled={!removable}
                    aria-label={`Remove answer ${option.label}`}
                >
                    <Trash2 className="size-3.5" aria-hidden />
                </Button>
            </div>
            <PathSelect
                flow={flow}
                self={stepId}
                value={option.next ?? null}
                main={main}
                mainLabel={mainLabel}
                label={`Then, for “${option.label}”`}
                onChoose={onPath}
            />
        </div>
    );
}

/** The name an answer is saved under - tucked away, since the builder picks one itself. */
function SavedAs({ id, node, onPatch }: { id: string; node: FlowNode; onPatch: (patch: Partial<FlowNode>, mergeKey?: string) => void }) {
    return (
        <details className="text-xs">
            <summary className="text-muted-foreground cursor-pointer select-none">Advanced: where the answer is saved</summary>
            <div className="mt-2 grid gap-1">
                <Label htmlFor="saved-as" className="text-xs">
                    Saved on the conversation as
                </Label>
                <Input
                    id="saved-as"
                    value={node.field ?? ''}
                    onChange={(e) => onPatch({ field: e.target.value.replace(/[^a-z0-9_]+/gi, '_').toLowerCase() }, `${id}:field`)}
                    className="h-8 font-mono text-xs"
                />
                <p className="text-muted-foreground">Shown with the lead in the inbox, and usable in an “If an earlier answer…” step.</p>
            </div>
        </details>
    );
}

type FieldChoice = { field: string; label: string; answers?: { id: string; label: string }[] };

/** Answers a condition can check: every question asked in the conversation, by what it asks. */
function collectFields(flow: Flow, self: string): FieldChoice[] {
    const seen = new Set<string>();
    const out: FieldChoice[] = [];
    for (const [id, node] of Object.entries(flow.nodes)) {
        if (id === self || !node.field || seen.has(node.field)) continue;
        if (node.type !== 'choice' && node.type !== 'input') continue;
        seen.add(node.field);
        out.push({
            field: node.field,
            label: CONTACT_FIELDS[node.field] ?? (node.text?.trim() || node.field).slice(0, 48),
            answers: node.type === 'choice' ? (node.options ?? []).map((o) => ({ id: o.id, label: o.label })) : undefined,
        });
    }
    return out;
}
