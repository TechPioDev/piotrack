import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { blockByKey, CONTACT_FIELDS, stepKind, stepTitle } from '@/lib/flow-blocks';
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
import { ArrowDown, ArrowUp, Flame, GripVertical, PanelRightClose, Pencil, Plus, Trash2, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { StepIcon, stepVisual } from './step-visuals';

type Assignee = { id: number; name: string };
type Tab = 'content' | 'paths' | 'advanced';

/** Content is what the visitor sees, Paths is where it leads, Advanced is the rest. */
const TABS: { id: Tab; label: string }[] = [
    { id: 'content', label: 'Content' },
    { id: 'paths', label: 'Paths' },
    { id: 'advanced', label: 'Advanced' },
];

const OPERATORS = [
    { id: 'equals', label: 'is' },
    { id: 'not_equals', label: 'is not' },
    { id: 'contains', label: 'contains' },
    { id: 'gte', label: 'is at least' },
    { id: 'lte', label: 'is at most' },
    { id: 'is_set', label: 'was answered' },
];

/** The tests that make sense for each kind of answer: nobody needs "is at least" for a reply button. */
const OPERATORS_FOR: Record<AnswerKind, string[]> = {
    reply: ['equals', 'not_equals', 'is_set'],
    number: ['equals', 'not_equals', 'gte', 'lte', 'is_set'],
    text: ['contains', 'equals', 'not_equals', 'is_set'],
};

type AnswerKind = 'reply' | 'number' | 'text';

/** A link a visitor can be sent to: a full https address, nothing else. */
const isLink = (url: string | undefined): boolean => /^https:\/\/\S+$/i.test((url ?? '').trim());

const MAIN = '__main';
const OWN = '__own';
const MAX_TEXT = 500;

/** One line under the step's name saying what it does. */
function purpose(node: FlowNode): string {
    switch (node.type) {
        case 'message':
            return node.url !== undefined || node.button !== undefined
                ? 'Sends a message with a button that opens a link.'
                : 'Sends a text message to the visitor.';
        case 'choice':
            return 'Asks a question with replies to tap.';
        case 'input':
            if (node.field && CONTACT_FIELDS[node.field])
                return `Asks for their ${CONTACT_FIELDS[node.field].toLowerCase()} and saves it to the lead.`;
            return node.input === 'number' ? 'Asks for a number.' : 'Asks a question they answer in their own words.';
        case 'booking':
            return node.mode === 'link' ? 'Gives visitors a button that opens your booking page.' : 'Offers free times from your booking page.';
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
        case 'webhook':
            return 'Posts the answers to your own system while the visitor waits.';
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
    canMove,
    focusName = 0,
    onSelect,
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
    /** Whether the step has anywhere to go in each direction. */
    canMove: { up: boolean; down: boolean };
    /** Changes each time "Rename" is chosen for this step: the cursor goes to its name. */
    focusName?: number;
    /** Open another step's settings: a condition leads to the question it checks. */
    onSelect?: (id: string) => void;
    onClose: () => void;
    /** Hide the whole panel, where it sits beside the canvas. */
    onHide?: () => void;
}) {
    const [tab, setTab] = useState<Tab>('content');
    const [confirmPlain, setConfirmPlain] = useState(false);
    const [dragFrom, setDragFrom] = useState<number | null>(null);
    const nameInput = useRef<HTMLInputElement>(null);
    // "Rename" was chosen on the card: put the cursor in the name, whatever tab is showing.
    useEffect(() => {
        if (focusName <= 0) return;
        // After the menu that asked for it has finished closing.
        const timer = window.setTimeout(() => {
            nameInput.current?.focus();
            nameInput.current?.select();
        }, 0);
        return () => window.clearTimeout(timer);
    }, [focusName]);

    const node = flow.nodes[id];
    if (!node) return null;

    const canMoveUp = canMove.up;
    const canMoveDown = canMove.down;

    // Where this step's answers carry on when they do not go their own way.
    const main = mainExit(root, flow, id);
    const mainLabel = main ? `Carry on: ${describe(flow.nodes[main] ?? { type: 'message' }).slice(0, 30)}` : 'Carry on (nothing follows yet)';
    const options = node.options ?? [];
    const answerPaths = new Set(options.map((o) => o.next ?? null));
    const fields = collectFields(flow, id);
    const hasText = ['message', 'choice', 'input', 'end', 'booking', 'ai'].includes(node.type);
    const hasButton = node.type === 'message' && (node.url !== undefined || node.button !== undefined);
    const byLink = node.type === 'booking' && node.mode === 'link';

    /** Quick replies on: the message becomes a question, and every reply carries on where it went. */
    const quickRepliesOn = () => onApply(withQuickReplies(flow, id));

    /** Quick replies off: back to a plain message, carrying on where the replies met. */
    const quickRepliesOff = () => {
        setConfirmPlain(false);
        const plain: FlowNode = { type: 'message', text: node.text, next: main };
        // The owner's own name for the step stays with it.
        if (node.name) plain.name = node.name;
        onApply(withoutStranded(flow, { ...flow, nodes: { ...flow.nodes, [id]: plain } }));
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
                    <p className="text-foreground truncate text-sm font-semibold">{stepTitle(node)}</p>
                    <p className="text-muted-foreground text-xs">{node.name?.trim() ? `${stepKind(node)} — ${purpose(node)}` : purpose(node)}</p>
                </div>
            </div>

            <div className="grid gap-1 px-4 pt-3">
                <Label htmlFor="step-name" className="text-xs">
                    Step name
                </Label>
                <Input
                    id="step-name"
                    ref={nameInput}
                    value={node.name ?? ''}
                    maxLength={60}
                    placeholder={stepKind(node)}
                    onChange={(e) => onPatch({ name: e.target.value }, `${id}:name`)}
                    className="h-8"
                />
                <p className="text-muted-foreground text-[11px]">Only you see this. It tells this step apart from others like it.</p>
            </div>

            <div role="tablist" aria-label="Step settings" className="mt-4 grid grid-cols-3 border-b px-4">
                {TABS.map((t) => (
                    <button
                        key={t.id}
                        type="button"
                        role="tab"
                        aria-selected={tab === t.id}
                        onClick={() => setTab(t.id)}
                        className={`-mb-px border-b-2 py-2 text-sm transition-colors ${
                            tab === t.id
                                ? 'border-indigo-500 font-medium text-indigo-700 dark:text-indigo-300'
                                : 'text-muted-foreground hover:text-foreground border-transparent'
                        }`}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-4" role="tabpanel">
                {tab === 'content' && (
                    <>
                        {hasText && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="step-text">
                                    {node.type === 'end'
                                        ? 'Closing Message'
                                        : node.type === 'message'
                                          ? 'Message Text'
                                          : node.type === 'booking'
                                            ? byLink
                                                ? 'Text Above the Button'
                                                : 'Text Above the Times'
                                            : 'Question Text'}
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

                        {node.type === 'message' && (
                            <div className="space-y-2">
                                <div className="flex items-center justify-between gap-3">
                                    <span>
                                        <Label>Link Button</Label>
                                        <span className="text-muted-foreground block text-xs">
                                            A button under the message that opens a page: a map, a Teams meeting, a form.
                                        </span>
                                    </span>
                                    <Switch
                                        label="Link button"
                                        checked={hasButton}
                                        onChange={(on) => onPatch(on ? { button: 'Open', url: '' } : { button: undefined, url: undefined })}
                                    />
                                </div>
                                {hasButton && (
                                    <div className="space-y-3 rounded-lg border p-3">
                                        <ButtonText node={node} id={id} fallback="Open" onPatch={onPatch} />
                                        <LinkField
                                            node={node}
                                            id={id}
                                            label="Link it opens"
                                            hint="Paste the full address: a Google Maps location, a Teams meeting, a form or any page. It opens in a new tab."
                                            onPatch={onPatch}
                                        />
                                    </div>
                                )}
                            </div>
                        )}

                        {((node.type === 'message' && !hasButton) || node.type === 'choice') && (
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

                        {node.type === 'webhook' && (
                            <div className="space-y-3">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="hook-url">Address to send to</Label>
                                    <Input
                                        id="hook-url"
                                        value={node.url ?? ''}
                                        placeholder="https://hooks.zapier.com/…"
                                        onChange={(e) => onPatch({ url: e.target.value }, `${id}:url`)}
                                    />
                                    <p className="text-muted-foreground text-xs">
                                        Everything the visitor has answered is posted here as JSON while they wait. It must start with https://. If it
                                        is slow, refused or broken the conversation carries on — set a path below for where it should go instead.
                                    </p>
                                </div>
                                <div className="grid grid-cols-2 gap-2">
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="hook-path">Keep this from the reply</Label>
                                        <Input
                                            id="hook-path"
                                            value={node.path ?? ''}
                                            placeholder="ticket.number"
                                            onChange={(e) => onPatch({ path: e.target.value }, `${id}:path`)}
                                        />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="hook-field">Save it as</Label>
                                        <Input
                                            id="hook-field"
                                            value={node.field ?? ''}
                                            placeholder="ticket_number"
                                            onChange={(e) => onPatch({ field: e.target.value }, `${id}:field`)}
                                        />
                                    </div>
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    A later step can then say it back with {'{{'}
                                    {node.field || 'ticket_number'}
                                    {'}}'}.
                                </p>
                            </div>
                        )}

                        {node.type === 'input' && !(node.field && CONTACT_FIELDS[node.field]) && (
                            <div className="grid gap-1.5">
                                <Label>What they type</Label>
                                <Select value={node.input ?? 'text'} onValueChange={(v) => onPatch({ input: v })}>
                                    <SelectTrigger aria-label="What they type">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="text">Text - anything they like</SelectItem>
                                        <SelectItem value="number">A number</SelectItem>
                                        <SelectItem value="email">An email address</SelectItem>
                                        <SelectItem value="phone">A phone number</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p className="text-muted-foreground text-xs">
                                    Numbers, email addresses and phone numbers are checked before the chat moves on.
                                </p>
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

                        {node.type === 'booking' && (
                            <div className="space-y-2">
                                <Label id="booking-mode">How visitors book</Label>
                                <div role="radiogroup" aria-labelledby="booking-mode" className="grid gap-2">
                                    <Choice
                                        checked={!byLink}
                                        title="Pick a time in the chat"
                                        hint="Your free times appear as buttons, and the one they tap is booked. Put this after the Email step, so the confirmation has somewhere to go."
                                        onChoose={() => onPatch({ mode: 'slots' })}
                                    />
                                    <Choice
                                        checked={byLink}
                                        title="Open a booking page"
                                        hint="A button opens a booking form in a new tab - yours here, or your own on Microsoft Bookings, Teams, Google or Calendly."
                                        onChoose={() => onPatch({ mode: 'link' })}
                                    />
                                </div>
                                {byLink && <BookingLink node={node} id={id} onPatch={onPatch} />}
                            </div>
                        )}

                        {(node.type === 'ai' || node.type === 'handoff') && (
                            <p className="text-muted-foreground text-sm">
                                {node.type === 'ai'
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
                                <Label>Hand the conversation to</Label>
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
                                <p className="text-muted-foreground text-xs">
                                    They own the lead - or the support ticket, if the conversation ends in one. Put this step on one answer’s path to
                                    send, say, billing questions to one person and technical ones to another.
                                </p>
                            </div>
                        )}

                        {node.type === 'condition' && (
                            <>
                                <ConditionFields
                                    flow={flow}
                                    root={root}
                                    node={node}
                                    id={id}
                                    fields={fields}
                                    onPatch={onPatch}
                                    onApply={onApply}
                                    onSelect={onSelect}
                                />
                                {node.field && <ConditionPaths flow={flow} id={id} node={node} main={main} mainLabel={mainLabel} onApply={onApply} />}
                            </>
                        )}

                        {node.type === 'end' && (
                            <div className="grid gap-1.5">
                                <Label>What happens at the end</Label>
                                <Select value={node.outcome ?? 'lead'} onValueChange={(v) => onPatch({ outcome: v })}>
                                    <SelectTrigger aria-label="What happens at the end">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="lead">Save them as a new lead</SelectItem>
                                        <SelectItem value="meeting">Save the lead and offer a meeting</SelectItem>
                                        {/* Reached after a time was booked in the chat: without it listed, this box showed blank. */}
                                        <SelectItem value="booked">Save the lead (a time was just booked)</SelectItem>
                                        <SelectItem value="support">Open a support ticket (existing customer)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        {node.type === 'end' && node.outcome === 'meeting' && <BookingLink node={node} id={id} onPatch={onPatch} />}

                        {node.type === 'end' && node.outcome === 'support' && (
                            <div className="grid gap-1.5">
                                <Label>Who gets the ticket</Label>
                                <Select
                                    value={node.assignee_id ? String(node.assignee_id) : '__none'}
                                    onValueChange={(v) => onPatch({ assignee_id: v === '__none' ? null : Number(v) })}
                                >
                                    <SelectTrigger aria-label="Who gets the ticket">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__none">Whoever this chat normally goes to</SelectItem>
                                        {assignees.map((a) => (
                                            <SelectItem key={a.id} value={String(a.id)}>
                                                {a.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-muted-foreground text-xs">
                                    Someone already in the conversation keeps it - a teammate who stepped in, or an “Assign to a Teammate” step on the
                                    way here.
                                </p>
                            </div>
                        )}

                        {node.type !== 'end' && (
                            <div className="grid gap-1.5">
                                <Label>Next Step</Label>
                                <div className="bg-muted/40 text-muted-foreground rounded-lg border px-3 py-2 text-xs">
                                    {node.type === 'choice'
                                        ? 'Follows the visitor’s reply. Choose where each reply leads under Paths.'
                                        : node.type === 'condition'
                                          ? 'Follows the check: one way when it matches, another when it does not.'
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

                {tab === 'paths' && (
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
                            <ConditionPaths flow={flow} id={id} node={node} main={main} mainLabel={mainLabel} onApply={onApply} />
                        )}

                        {(node.type === 'booking' || node.type === 'ai') && (
                            <div className="grid gap-1.5">
                                <Label>{fallbackLabel(node)}</Label>
                                <PathSelect
                                    flow={flow}
                                    self={id}
                                    value={node.fallback ?? node.next ?? null}
                                    main={node.next ?? null}
                                    mainLabel="Carry on as normal"
                                    label={fallbackLabel(node)}
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
                                This step always continues to the next one. To send visitors different ways, add an “Ask a Question” step - each reply
                                can lead its own way - or a Condition step that checks an earlier answer.
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

function fallbackLabel(node: FlowNode): string {
    if (node.type === 'ai') return 'If the AI cannot answer';
    return node.mode === 'link' ? 'If there is no booking page to open' : 'If no time works for them';
}

/** One of a few ways a step can work, with a line saying what choosing it means. */
function Choice({ checked, title, hint, onChoose }: { checked: boolean; title: string; hint: string; onChoose: () => void }) {
    return (
        <button
            type="button"
            role="radio"
            aria-checked={checked}
            onClick={onChoose}
            className={`flex items-start gap-2.5 rounded-lg border p-3 text-left transition-colors focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none ${
                checked ? 'border-indigo-500 bg-indigo-50/60 dark:bg-indigo-500/10' : 'hover:border-indigo-300'
            }`}
        >
            <span
                className={`mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border ${
                    checked ? 'border-indigo-600' : 'border-slate-400'
                }`}
                aria-hidden
            >
                {checked && <span className="size-2 rounded-full bg-indigo-600" />}
            </span>
            <span>
                <span className="text-foreground block text-sm font-medium">{title}</span>
                <span className="text-muted-foreground block text-xs">{hint}</span>
            </span>
        </button>
    );
}

type Patch = (patch: Partial<FlowNode>, mergeKey?: string) => void;

/** The words on a step's button. */
function ButtonText({ node, id, fallback, onPatch }: { node: FlowNode; id: string; fallback: string; onPatch: Patch }) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor="step-button">Button text</Label>
            <Input
                id="step-button"
                value={node.button ?? ''}
                maxLength={40}
                placeholder={fallback}
                onChange={(e) => onPatch({ button: e.target.value }, `${id}:button`)}
                className="h-9"
            />
        </div>
    );
}

/** The address a button opens, said to be wrong while it is being typed rather than at publish. */
function LinkField({ node, id, label, hint, onPatch }: { node: FlowNode; id: string; label: string; hint: string; onPatch: Patch }) {
    const typed = (node.url ?? '').trim();
    const wrong = typed !== '' && !isLink(typed);
    return (
        <div className="grid gap-1.5">
            <Label htmlFor="step-link">{label}</Label>
            <Input
                id="step-link"
                type="url"
                inputMode="url"
                value={node.url ?? ''}
                maxLength={500}
                placeholder="https://"
                aria-invalid={wrong}
                aria-describedby="step-link-hint"
                onChange={(e) => onPatch({ url: e.target.value.trim() }, `${id}:url`)}
                className="h-9"
            />
            <p id="step-link-hint" className={`text-xs ${wrong ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground'}`}>
                {wrong ? 'A link must be a full address starting with https://' : typed === '' ? hint : 'Visitors open this in a new tab.'}
            </p>
        </div>
    );
}

/**
 * Where a "book a meeting" button sends someone: the booking form here, or the
 * owner's own booking page - Microsoft Bookings, a Teams or Google page, Calendly.
 */
function BookingLink({ node, id, onPatch }: { node: FlowNode; id: string; onPatch: Patch }) {
    const own = node.link_to === 'custom';
    return (
        <div className="space-y-3 rounded-lg border p-3">
            <div className="grid gap-1.5">
                <Label>Booking page the button opens</Label>
                <Select value={own ? 'custom' : 'page'} onValueChange={(v) => onPatch({ link_to: v })}>
                    <SelectTrigger aria-label="Booking page the button opens">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="page">Your booking form here</SelectItem>
                        <SelectItem value="custom">Your own booking link</SelectItem>
                    </SelectContent>
                </Select>
                {!own && (
                    <p className="text-muted-foreground text-xs">
                        The booking form you set up under Sales → Booking: visitors pick a free time and leave their details, and it lands in your
                        calendar.
                    </p>
                )}
            </div>
            {own && (
                <LinkField
                    node={node}
                    id={id}
                    label="Your booking link"
                    hint="Paste the link to your own booking page: Microsoft Bookings, a Teams or Google Calendar booking page, Calendly, or a form on your site."
                    onPatch={onPatch}
                />
            )}
            <ButtonText node={node} id={id} fallback="Choose a time" onPatch={onPatch} />
        </div>
    );
}

type FieldChoice = { id: string; field: string; label: string; kind: AnswerKind; answers?: { id: string; label: string }[] };

/**
 * What a condition checks. A condition is nothing without a question to check,
 * and an owner starting from an empty conversation has none - so it offers to
 * add one, just above itself, and points itself at the answer.
 */
function ConditionFields({
    flow,
    root,
    node,
    id,
    fields,
    onPatch,
    onApply,
    onSelect,
}: {
    flow: Flow;
    root: TreeBranch;
    node: FlowNode;
    id: string;
    fields: FieldChoice[];
    onPatch: Patch;
    onApply: (next: Flow) => void;
    onSelect?: (id: string) => void;
}) {
    const chosen = fields.find((f) => f.field === node.field);
    const kind: AnswerKind = chosen?.kind ?? 'text';
    const operator = node.operator ?? 'equals';
    // A test chosen before the question changed is still shown, so nothing is silently rewritten.
    const tests = OPERATORS.filter((o) => OPERATORS_FOR[kind].includes(o.id) || o.id === operator);

    /** Point the condition at a question, with a test that suits its kind of answer. */
    const aim = (field: FieldChoice): Partial<FlowNode> =>
        field.kind === 'reply'
            ? { field: field.field, operator: 'equals', value: field.answers?.[0]?.id ?? '' }
            : field.kind === 'number'
              ? { field: field.field, operator: 'gte', value: '' }
              : { field: field.field, operator: 'is_set', value: '' };

    /** A new question just above this step, already the one being checked. */
    const addQuestion = (key: 'question' | 'open') => {
        const place = locate(root, id);
        const block = blockByKey(key);
        if (!place || !block) return;
        const { flow: next, id: question } = insertStep(flow, place.branch.steps[place.index].via, block.make());
        const asked = next.nodes[question];
        const target: FieldChoice = {
            id: question,
            field: asked.field ?? question,
            label: '',
            kind: asked.type === 'choice' ? 'reply' : 'text',
            answers: asked.options?.map((o) => ({ id: o.id, label: o.label })),
        };
        onApply({ ...next, nodes: { ...next.nodes, [id]: { ...next.nodes[id], ...aim(target) } } });
    };

    const adders = (
        <div className="flex flex-wrap gap-2">
            <Button size="sm" variant="outline" onClick={() => addQuestion('question')}>
                <Plus className="size-3.5" aria-hidden /> Question with replies
            </Button>
            <Button size="sm" variant="outline" onClick={() => addQuestion('open')}>
                <Plus className="size-3.5" aria-hidden /> Text field
            </Button>
        </div>
    );

    if (fields.length === 0) {
        return (
            <div className="space-y-3 rounded-lg border border-dashed border-indigo-300 bg-indigo-50/50 p-3 dark:border-indigo-500/40 dark:bg-indigo-500/5">
                <div>
                    <p className="text-foreground text-sm font-semibold">First, ask a question</p>
                    <p className="text-muted-foreground mt-1 text-xs">
                        A condition sends visitors one way or another by an answer they gave. This conversation does not ask anything yet. Add the
                        question to check - it goes just above this step, and you can reword it and its replies there.
                    </p>
                </div>
                {adders}
            </div>
        );
    }

    return (
        <div className="space-y-3">
            <div className="grid gap-1.5">
                <Label>If the answer to</Label>
                <Select
                    value={node.field || '__none'}
                    onValueChange={(v) => {
                        const next = fields.find((f) => f.field === v);
                        onPatch(next ? aim(next) : { field: '', value: '' });
                    }}
                >
                    <SelectTrigger aria-label="Question to check">
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
                {chosen && onSelect && (
                    <button
                        type="button"
                        onClick={() => onSelect(chosen.id)}
                        className="flex items-center gap-1 justify-self-start text-xs font-medium text-indigo-700 hover:underline dark:text-indigo-300"
                    >
                        <Pencil className="size-3" aria-hidden /> Edit this question{chosen.answers ? ' and its replies' : ''}
                    </button>
                )}
            </div>

            {chosen && (
                <div className={`grid gap-2 ${operator === 'is_set' ? '' : 'grid-cols-2'}`}>
                    <div className="grid gap-1.5">
                        <Select value={operator} onValueChange={(v) => onPatch({ operator: v })}>
                            <SelectTrigger aria-label="How to compare the answer">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {tests.map((o) => (
                                    <SelectItem key={o.id} value={o.id}>
                                        {o.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    {operator !== 'is_set' && (
                        <div className="grid gap-1.5">
                            {chosen.answers ? (
                                <Select value={node.value || '__none'} onValueChange={(v) => onPatch({ value: v === '__none' ? '' : v })}>
                                    <SelectTrigger id="cond-value" aria-label="Reply to compare with">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__none">Choose a reply…</SelectItem>
                                        {chosen.answers.map((a) => (
                                            <SelectItem key={a.id} value={a.id}>
                                                {a.label || 'Untitled answer'}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            ) : (
                                <Input
                                    id="cond-value"
                                    type={kind === 'number' ? 'number' : 'text'}
                                    aria-label={kind === 'number' ? 'Number to compare with' : 'Words to compare with'}
                                    placeholder={kind === 'number' ? 'a number' : 'a word or phrase'}
                                    value={node.value ?? ''}
                                    onChange={(e) => onPatch({ value: e.target.value }, `${id}:value`)}
                                />
                            )}
                        </div>
                    )}
                </div>
            )}

            <details className="text-xs">
                <summary className="text-muted-foreground hover:text-foreground cursor-pointer">Check a question that is not asked yet</summary>
                <div className="mt-2 space-y-2">
                    <p className="text-muted-foreground">It is added just above this step.</p>
                    {adders}
                </div>
            </details>
        </div>
    );
}

/** Where each outcome of a condition leads: the two ways out, side by side with the check. */
function ConditionPaths({
    flow,
    id,
    node,
    main,
    mainLabel,
    onApply,
}: {
    flow: Flow;
    id: string;
    node: FlowNode;
    main: string | null;
    mainLabel: string;
    onApply: (next: Flow) => void;
}) {
    const same = (node.next ?? null) === (node.otherwise ?? null);
    const way = (exit: 'next' | 'otherwise', label: string, placeholder: string) => (
        <div className="grid gap-1.5">
            <Label className="text-xs">{label}</Label>
            <PathSelect
                flow={flow}
                self={id}
                value={(exit === 'next' ? node.next : node.otherwise) ?? null}
                main={main}
                mainLabel={mainLabel}
                label={label}
                onChoose={(choice) => onApply(choosePath(flow, { kind: 'exit', from: id, exit }, choice, main, placeholder))}
            />
        </div>
    );

    return (
        <div className="space-y-3 rounded-lg border p-3">
            {way('next', 'When it matches', 'Say something to people this matches.')}
            {way('otherwise', 'Otherwise', 'Say something to everyone else.')}
            {same && (
                <div className="space-y-2">
                    <p className="text-xs text-amber-700 dark:text-amber-400">
                        Both lead the same way, so the check changes nothing yet. Give one of them “A new path of its own”.
                    </p>
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            onApply(choosePath(flow, { kind: 'exit', from: id, exit: 'next' }, OWN, main, 'Say something to people this matches.'))
                        }
                    >
                        <Plus className="size-3.5" aria-hidden /> Start a path for when it matches
                    </Button>
                </div>
            )}
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
            id,
            field: node.field,
            label: (CONTACT_FIELDS[node.field] ?? (node.name?.trim() || node.text?.trim() || node.field)).slice(0, 48),
            kind: node.type === 'choice' ? 'reply' : node.input === 'number' ? 'number' : 'text',
            answers: node.type === 'choice' ? (node.options ?? []).map((o) => ({ id: o.id, label: o.label })) : undefined,
        });
    }
    return out;
}
