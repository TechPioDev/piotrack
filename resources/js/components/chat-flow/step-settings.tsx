import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { CONTACT_FIELDS, stepKind } from '@/lib/flow-blocks';
import {
    addOption,
    describe,
    type Flow,
    type FlowNode,
    type FlowOption,
    insertStep,
    isMovable,
    locate,
    mainExit,
    reachable,
    removeOption,
    type Slot,
    type TreeBranch,
    withoutStranded,
    withQuickReplies,
    withTarget,
} from '@/lib/flow-tree';
import { ArrowDown, ArrowUp, Flame, GripVertical, PanelRightClose, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { StepIcon, stepVisual } from './step-visuals';

type Assignee = { id: number; name: string };
type Tab = 'content' | 'advanced' | 'condition';

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
const MAX_TEXT = 500;

/** One line under the step's name saying what it does. */
function purpose(node: FlowNode): string {
    switch (node.type) {
        case 'message':
            return 'Sends a text message to the visitor.';
        case 'choice':
            return 'Asks a question with replies to tap.';
        case 'input':
            if (node.field && CONTACT_FIELDS[node.field])
                return `Asks for their ${CONTACT_FIELDS[node.field].toLowerCase()} and saves it to the lead.`;
            return node.input === 'number' ? 'Asks for a number.' : 'Asks a question they answer in their own words.';
        case 'booking':
            return 'Offers free times from your booking page.';
        case 'handoff':
            return 'Connects the visitor to someone from your team.';
        case 'ai':
            return 'Lets AI answer typed questions about your business.';
        case 'score':
            return 'Raises the lead score of visitors who reach it.';
        case 'tag':
            return 'Labels the conversation.';
        case 'assign':
            return 'Chooses who follows up the lead.';
        case 'condition':
            return 'Sends visitors one way or another by an earlier answer.';
        case 'end':
            return 'Ends the conversation.';
        default:
            return '';
    }
}

/**
 * Where one way out of a step leads: on with the rest of the conversation, a
 * path of its own (started with a message to edit, so it has a branch on the
 * canvas), or straight to another step - the "go to" that loops or skips.
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
            <SelectTrigger aria-label={label} className="h-9 text-xs">
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
                        Go to {stepKind(flow.nodes[id])}: {describe(flow.nodes[id]).slice(0, 32)}
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

function Switch({ checked, onChange, label }: { checked: boolean; onChange: (checked: boolean) => void; label: string }) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            aria-label={label}
            onClick={() => onChange(!checked)}
            className={`relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none ${
                checked ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-600'
            }`}
        >
            <span
                className={`inline-block size-4 rounded-full bg-white shadow transition-transform ${checked ? 'translate-x-4.5' : 'translate-x-0.5'}`}
            />
        </button>
    );
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
    onHide,
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
    /** Hide the whole panel, where it sits beside the canvas. */
    onHide?: () => void;
}) {
    const [tab, setTab] = useState<Tab>('content');
    const [confirmPlain, setConfirmPlain] = useState(false);
    const [dragFrom, setDragFrom] = useState<number | null>(null);
    const node = flow.nodes[id];
    if (!node) return null;

    const place = locate(root, id);
    const canMoveUp = isMovable(node) && place !== null && place.index > 0 && isMovable(place.branch.steps[place.index - 1].node);
    const canMoveDown =
        isMovable(node) && place !== null && place.index < place.branch.steps.length - 1 && isMovable(place.branch.steps[place.index + 1].node);

    // Where this step's answers carry on when they do not go their own way.
    const main = mainExit(root, flow, id);
    const mainLabel = main ? `Carry on: ${describe(flow.nodes[main] ?? { type: 'message' }).slice(0, 30)}` : 'Carry on (nothing follows yet)';
    const options = node.options ?? [];
    const answerPaths = new Set(options.map((o) => o.next ?? null));
    const fields = collectFields(flow, id);
    const hasText = ['message', 'choice', 'input', 'end', 'booking', 'ai'].includes(node.type);

    /** Quick replies on: the message becomes a question, and every reply carries on where it went. */
    const quickRepliesOn = () => onApply(withQuickReplies(flow, id));

    /** Quick replies off: back to a plain message, carrying on where the replies met. */
    const quickRepliesOff = () => {
        setConfirmPlain(false);
        onApply(withoutStranded(flow, { ...flow, nodes: { ...flow.nodes, [id]: { type: 'message', text: node.text, next: main } } }));
    };

    const reorder = (from: number, to: number) => {
        if (from === to) return;
        const next = [...options];
        const [moved] = next.splice(from, 1);
        next.splice(to, 0, moved);
        onPatch({ options: next });
    };

    return (
        <div className="flex h-full min-h-0 flex-col">
            <div className="flex items-center justify-between border-b px-4 py-3">
                <h2 className="text-foreground text-sm font-semibold">Step Settings</h2>
                <div className="flex items-center gap-0.5">
                    {onHide && (
                        <Button
                            size="icon"
                            variant="ghost"
                            className="size-7"
                            onClick={onHide}
                            aria-label="Hide settings panel"
                            title="Hide this panel"
                        >
                            <PanelRightClose className="size-4" aria-hidden />
                        </Button>
                    )}
                    <Button size="icon" variant="ghost" className="size-7" onClick={onClose} aria-label="Close step settings">
                        <X className="size-4" aria-hidden />
                    </Button>
                </div>
            </div>

            <div className="flex items-start gap-3 px-4 pt-4">
                <StepIcon visual={stepVisual(node)} className="size-10" />
                <div className="min-w-0">
                    <p className="text-foreground text-sm font-semibold">{stepKind(node)}</p>
                    <p className="text-muted-foreground text-xs">{purpose(node)}</p>
                </div>
            </div>

            <div role="tablist" aria-label="Step settings" className="mt-4 grid grid-cols-3 border-b px-4">
                {(['content', 'advanced', 'condition'] as const).map((t) => (
                    <button
                        key={t}
                        type="button"
                        role="tab"
                        aria-selected={tab === t}
                        onClick={() => setTab(t)}
                        className={`-mb-px border-b-2 py-2 text-sm capitalize transition-colors ${
                            tab === t
                                ? 'border-indigo-500 font-medium text-indigo-700 dark:text-indigo-300'
                                : 'text-muted-foreground hover:text-foreground border-transparent'
                        }`}
                    >
                        {t}
                    </button>
                ))}
            </div>

            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-4" role="tabpanel">
                {tab === 'content' && (
                    <>
                        {hasText && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="step-text">
                                    {node.type === 'end' ? 'Closing Message' : node.type === 'message' ? 'Message Text' : 'Question Text'}
                                </Label>
                                <textarea
                                    id="step-text"
                                    rows={4}
                                    maxLength={MAX_TEXT}
                                    className="border-input bg-background w-full resize-y rounded-lg border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                                    value={node.text ?? ''}
                                    onChange={(e) => onPatch({ text: e.target.value }, `${id}:text`)}
                                />
                                <p className="text-muted-foreground text-right text-[11px] tabular-nums">
                                    {(node.text ?? '').length}/{MAX_TEXT}
                                </p>
                                {fields.length > 0 && (
                                    <div className="flex flex-wrap items-center gap-1">
                                        <span className="text-muted-foreground text-[11px]">Use an answer:</span>
                                        {fields.slice(0, 6).map((f) => (
                                            <button
                                                key={f.field}
                                                type="button"
                                                title={`Insert what they answered for ${f.label}`}
                                                onClick={() => {
                                                    const box = document.getElementById('step-text') as HTMLTextAreaElement | null;
                                                    const text = node.text ?? '';
                                                    const at = box?.selectionStart ?? text.length;
                                                    const token = `{{${f.field}}}`;
                                                    onPatch({ text: `${text.slice(0, at)}${token}${text.slice(at)}` });
                                                    window.setTimeout(() => {
                                                        box?.focus();
                                                        box?.setSelectionRange(at + token.length, at + token.length);
                                                    }, 0);
                                                }}
                                                className="rounded-full border px-2 py-0.5 text-[11px] hover:border-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300"
                                            >
                                                {f.label}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}

                        {(node.type === 'message' || node.type === 'choice') && (
                            <div className="space-y-2">
                                <div className="flex items-center justify-between gap-3">
                                    <Label>Quick Replies</Label>
                                    <Switch
                                        label="Quick replies"
                                        checked={node.type === 'choice'}
                                        onChange={(on) => {
                                            if (on) quickRepliesOn();
                                            else if (answerPaths.size > 1) setConfirmPlain(true);
                                            else quickRepliesOff();
                                        }}
                                    />
                                </div>
                                {confirmPlain && (
                                    <div className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs dark:border-amber-500/40 dark:bg-amber-500/10">
                                        <p>
                                            The replies lead different ways. Turning them off keeps only the path after the question and removes the
                                            others.
                                        </p>
                                        <div className="mt-2 flex gap-2">
                                            <Button size="sm" variant="destructive" className="h-7" onClick={quickRepliesOff}>
                                                Remove their paths
                                            </Button>
                                            <Button size="sm" variant="outline" className="h-7" onClick={() => setConfirmPlain(false)}>
                                                Keep the replies
                                            </Button>
                                        </div>
                                    </div>
                                )}
                                {node.type === 'message' && (
                                    <p className="text-muted-foreground text-xs">Add buttons the visitor can tap to reply.</p>
                                )}
                                {node.type === 'choice' && (
                                    <div className="space-y-1.5">
                                        {options.map((option, index) => (
                                            <div
                                                key={option.id}
                                                onDragOver={(e) => dragFrom !== null && e.preventDefault()}
                                                onDrop={() => {
                                                    if (dragFrom !== null) reorder(dragFrom, index);
                                                    setDragFrom(null);
                                                }}
                                                className={`flex items-center gap-1.5 ${dragFrom === index ? 'opacity-50' : ''}`}
                                            >
                                                <span
                                                    draggable
                                                    onDragStart={(e) => {
                                                        e.dataTransfer.setData('text/plain', String(index));
                                                        setDragFrom(index);
                                                    }}
                                                    onDragEnd={() => setDragFrom(null)}
                                                    className="text-muted-foreground cursor-grab rounded p-1 hover:bg-black/5 active:cursor-grabbing dark:hover:bg-white/10"
                                                    title="Drag to reorder"
                                                    aria-hidden
                                                >
                                                    <GripVertical className="size-4" />
                                                </span>
                                                <Input
                                                    value={option.label}
                                                    onChange={(e) =>
                                                        onPatch(
                                                            { options: options.map((o, i) => (i === index ? { ...o, label: e.target.value } : o)) },
                                                            `${id}:option:${option.id}:label`,
                                                        )
                                                    }
                                                    aria-label={`Reply ${index + 1}`}
                                                    className="h-9"
                                                />
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    className="size-8 shrink-0"
                                                    disabled={options.length <= 1}
                                                    aria-label={`Remove reply ${option.label}`}
                                                    onClick={() => onApply(removeOption(flow, id, option.id))}
                                                >
                                                    <X className="size-4" aria-hidden />
                                                </Button>
                                            </div>
                                        ))}
                                        <Button size="sm" variant="outline" onClick={() => onApply(addOption(flow, root, id).flow)}>
                                            <Plus className="size-3.5" aria-hidden /> Add Option
                                        </Button>
                                    </div>
                                )}
                            </div>
                        )}

                        {node.type === 'input' && (
                            <div className="flex items-center justify-between gap-3 rounded-lg border p-3">
                                <span>
                                    <span className="text-foreground block text-sm font-medium">Required</span>
                                    <span className="text-muted-foreground block text-xs">
                                        {node.optional ? 'Visitors see a “Skip this” button.' : 'Visitors must answer to carry on.'}
                                    </span>
                                </span>
                                <Switch label="Required" checked={!node.optional} onChange={(on) => onPatch({ optional: !on })} />
                            </div>
                        )}

                        {node.type === 'input' && node.field && CONTACT_FIELDS[node.field] && (
                            <p className="text-muted-foreground text-xs">
                                Saved to the lead as <span className="text-foreground font-medium">{CONTACT_FIELDS[node.field]}</span>
                                {node.input === 'email'
                                    ? ', and checked as a real email address.'
                                    : node.input === 'phone'
                                      ? ', and checked as a phone number.'
                                      : '.'}
                            </p>
                        )}

                        {(node.type === 'booking' || node.type === 'ai' || node.type === 'handoff') && (
                            <p className="text-muted-foreground text-sm">
                                {node.type === 'booking'
                                    ? 'Shows the next free times from your booking page as buttons and books the one they pick. Put it after the Email step, so the confirmation has somewhere to go.'
                                    : node.type === 'ai'
                                      ? 'Visitors type a question and the AI answers from your company details, never inventing prices or promises. Uses your plan’s AI credits.'
                                      : 'If someone from your team is online and it is within business hours, the visitor is connected to them. Otherwise they are told when to expect a reply, and the next steps carry on collecting their details.'}
                            </p>
                        )}

                        {node.type === 'score' && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="points">Points to add</Label>
                                <Input
                                    id="points"
                                    type="number"
                                    value={node.points ?? 0}
                                    onChange={(e) => onPatch({ points: Number(e.target.value) }, `${id}:points`)}
                                />
                                <p className="text-muted-foreground text-xs">
                                    Visitors who reach this step score higher, so hot leads rise to the top.
                                </p>
                            </div>
                        )}

                        {node.type === 'tag' && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="tag">Tag</Label>
                                <Input
                                    id="tag"
                                    value={node.tag ?? ''}
                                    onChange={(e) => onPatch({ tag: e.target.value }, `${id}:tag`)}
                                    placeholder="interested"
                                />
                            </div>
                        )}

                        {node.type === 'assign' && (
                            <div className="grid gap-1.5">
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

                        {node.type === 'condition' && <ConditionFields node={node} id={id} fields={fields} onPatch={onPatch} />}

                        {node.type === 'end' && (
                            <div className="grid gap-1.5">
                                <Label>What happens at the end</Label>
                                <Select value={node.outcome ?? 'lead'} onValueChange={(v) => onPatch({ outcome: v })}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="lead">Save them as a new lead</SelectItem>
                                        <SelectItem value="meeting">Save the lead and offer a meeting</SelectItem>
                                        <SelectItem value="support">Open a support ticket (existing customer)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        {node.type !== 'end' && (
                            <div className="grid gap-1.5">
                                <Label>Next Step</Label>
                                <div className="bg-muted/40 text-muted-foreground rounded-lg border px-3 py-2 text-xs">
                                    {node.type === 'choice'
                                        ? 'Follows the visitor’s reply. Choose where each reply leads under Condition.'
                                        : node.type === 'condition'
                                          ? 'Follows the check. Choose where each outcome leads under Condition.'
                                          : node.next && flow.nodes[node.next]
                                            ? `Continues to “${describe(flow.nodes[node.next]).slice(0, 48)}”.`
                                            : 'Nothing follows yet: add a step below it on the canvas.'}
                                </div>
                            </div>
                        )}
                    </>
                )}

                {tab === 'advanced' && (
                    <>
                        {node.type === 'message' && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="step-delay">Pause before this message</Label>
                                <div className="flex items-center gap-2">
                                    <Input
                                        id="step-delay"
                                        type="number"
                                        min={0}
                                        max={10}
                                        step={0.5}
                                        className="h-9 w-24"
                                        value={node.delay ?? 0}
                                        onChange={(e) => onPatch({ delay: Math.min(10, Math.max(0, Number(e.target.value) || 0)) }, `${id}:delay`)}
                                    />
                                    <span className="text-muted-foreground text-xs">seconds</span>
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    The visitor sees typing dots for this long first, so a run of messages arrives the way a person types them.
                                </p>
                            </div>
                        )}

                        {node.type === 'choice' && (
                            <div className="space-y-2">
                                <Label>Lead score and urgency per reply</Label>
                                {options.map((option, index) => (
                                    <ScoreRow
                                        key={option.id}
                                        option={option}
                                        onChange={(patch) =>
                                            onPatch(
                                                { options: options.map((o, i) => (i === index ? { ...o, ...patch } : o)) },
                                                `${id}:option:${option.id}:${Object.keys(patch).join(',')}`,
                                            )
                                        }
                                    />
                                ))}
                            </div>
                        )}

                        {node.type === 'input' && !(node.field && CONTACT_FIELDS[node.field]) && (
                            <div className="grid gap-1.5">
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
                        )}

                        {(node.type === 'choice' || node.type === 'input') && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="saved-as">Save the answer as</Label>
                                <Input
                                    id="saved-as"
                                    value={node.field ?? ''}
                                    onChange={(e) => onPatch({ field: e.target.value.replace(/[^a-z0-9_]+/gi, '_').toLowerCase() }, `${id}:field`)}
                                    className="font-mono text-xs"
                                />
                                <p className="text-muted-foreground text-xs">Shown with the lead in the inbox, and usable in a Condition step.</p>
                            </div>
                        )}

                        <div className="grid gap-1.5">
                            <Label>Step ID</Label>
                            <p className="bg-muted/40 rounded-lg border px-3 py-2 font-mono text-xs">{id}</p>
                        </div>

                        <div className="flex flex-wrap items-center gap-2 border-t pt-3">
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
                        </div>
                    </>
                )}

                {tab === 'condition' && (
                    <>
                        {node.type === 'choice' && (
                            <div className="space-y-3">
                                <p className="text-muted-foreground text-xs">
                                    Where each reply leads. On the canvas, replies that lead different ways fan out as branches.
                                </p>
                                {options.map((option) => (
                                    <div key={option.id} className="grid gap-1.5">
                                        <Label className="text-xs">When they choose “{option.label || 'Untitled answer'}”</Label>
                                        <PathSelect
                                            flow={flow}
                                            self={id}
                                            value={option.next ?? null}
                                            main={main}
                                            mainLabel={mainLabel}
                                            label={`When they choose ${option.label}`}
                                            onChoose={(choice) =>
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
                                        />
                                    </div>
                                ))}
                            </div>
                        )}

                        {node.type === 'condition' && (
                            <div className="grid gap-1.5">
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
                        )}

                        {(node.type === 'booking' || node.type === 'ai') && (
                            <div className="grid gap-1.5">
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
                        )}

                        {!['choice', 'condition', 'booking', 'ai'].includes(node.type) && (
                            <p className="text-muted-foreground text-sm">
                                This step always continues to the next one. To send visitors different ways, turn on Quick Replies on a message, or
                                add a Condition step.
                            </p>
                        )}
                    </>
                )}
            </div>

            <div className="border-t p-3">
                <Button size="sm" variant="outline" className="w-full text-red-600 dark:text-red-400" onClick={onDelete}>
                    <Trash2 className="size-3.5" aria-hidden /> Delete step
                </Button>
            </div>
        </div>
    );
}

function ScoreRow({ option, onChange }: { option: FlowOption; onChange: (patch: Partial<FlowOption>) => void }) {
    const urgent = option.priority === 'high';
    return (
        <div className="flex items-center gap-1.5 rounded-lg border p-2">
            <span className="min-w-0 flex-1 truncate text-sm">{option.label || 'Untitled answer'}</span>
            <Input
                type="number"
                value={option.score ?? 0}
                onChange={(e) => onChange({ score: Number(e.target.value) })}
                aria-label={`Lead score points for ${option.label}`}
                title="Lead score points"
                className="h-8 w-16 tabular-nums"
            />
            <Button
                size="sm"
                variant={urgent ? 'default' : 'outline'}
                className="h-8 px-2"
                aria-pressed={urgent}
                aria-label={`Urgent: flag ${option.label} conversations as a priority`}
                title="Urgent: flag the conversation as a priority"
                onClick={() => onChange({ priority: urgent ? undefined : 'high' })}
            >
                <Flame className="size-3.5" aria-hidden />
            </Button>
        </div>
    );
}

type FieldChoice = { field: string; label: string; answers?: { id: string; label: string }[] };

function ConditionFields({
    node,
    id,
    fields,
    onPatch,
}: {
    node: FlowNode;
    id: string;
    fields: FieldChoice[];
    onPatch: (patch: Partial<FlowNode>, mergeKey?: string) => void;
}) {
    const chosen = fields.find((f) => f.field === node.field);
    return (
        <div className="space-y-3">
            <div className="grid gap-1.5">
                <Label>Check the answer to</Label>
                <Select value={node.field || '__none'} onValueChange={(v) => onPatch({ field: v === '__none' ? '' : v, value: '' })}>
                    <SelectTrigger>
                        <SelectValue />
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
            <div className="grid grid-cols-2 gap-2">
                <div className="grid gap-1.5">
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
                    <div className="grid gap-1.5">
                        <Label htmlFor="cond-value">Value</Label>
                        {chosen?.answers ? (
                            <Select value={node.value || '__none'} onValueChange={(v) => onPatch({ value: v === '__none' ? '' : v })}>
                                <SelectTrigger id="cond-value">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__none">Choose an answer…</SelectItem>
                                    {chosen.answers.map((a) => (
                                        <SelectItem key={a.id} value={a.id}>
                                            {a.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        ) : (
                            <Input id="cond-value" value={node.value ?? ''} onChange={(e) => onPatch({ value: e.target.value }, `${id}:value`)} />
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

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
