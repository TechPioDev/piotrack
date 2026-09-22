import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { BLOCK_GROUPS, blockByKey, BLOCKS, outcomeLabel, stepKind } from '@/lib/flow-blocks';
import {
    buildTree,
    canInsert,
    describe,
    type Flow,
    isMovable,
    moveStep,
    type Slot,
    stepsInside,
    type TreeBranch,
    type TreeStep,
} from '@/lib/flow-tree';
import {
    AlertCircle,
    AlertTriangle,
    ArrowDown,
    ArrowUp,
    ChevronsDownUp,
    ChevronsUpDown,
    Circle,
    Copy,
    CornerDownRight,
    Map as MapIcon,
    MessageSquarePlus,
    MoreHorizontal,
    Move,
    Pencil,
    Plus,
    ScanLine,
    Trash2,
    X,
    ZoomIn,
    ZoomOut,
} from 'lucide-react';
import { createContext, Fragment, type ReactNode, useCallback, useContext, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { blockVisual, NEUTRAL_TONE, StepIcon, stepVisual, type Visual } from './step-visuals';

/**
 * What is being placed: a new step from the library, or a step already on the
 * canvas. `pick` is the click route - pick a step, then click where it goes -
 * as against a drag.
 */
export type Dragging = { kind: 'block'; key: string; pick?: boolean } | { kind: 'step'; id: string; pick?: boolean } | null;

export type Issue = { level: 'error' | 'warning'; message: string };

type Editor = {
    flow: Flow;
    selected: string | null;
    select: (id: string | null) => void;
    /** Select a step and bring its settings into view. */
    openSettings: (id: string) => void;
    dragging: Dragging;
    setDragging: (dragging: Dragging) => void;
    /** Put what is being placed into a place. */
    place: (slot: Slot) => void;
    insertBlock: (slot: Slot, key: string) => void;
    move: (id: string, direction: 'up' | 'down') => void;
    canMove: (id: string) => { up: boolean; down: boolean };
    duplicate: (id: string) => void;
    toggleRequired: (id: string) => void;
    setText: (id: string, text: string) => void;
    renameReply: (id: string, optionId: string, label: string) => void;
    /** Add a reply to a question; returns the new reply's id. */
    addReply: (id: string) => string | null;
    removeReply: (id: string, optionId: string) => void;
    addQuickReplies: (id: string) => void;
    remove: (id: string) => void;
    collapsed: Set<string>;
    toggleCollapsed: (id: string) => void;
    issues: Record<string, Issue[]>;
};

export const FlowEditorContext = createContext<Editor | null>(null);

function useEditor(): Editor {
    const editor = useContext(FlowEditorContext);
    if (!editor) throw new Error('FlowEditorContext missing');
    return editor;
}

/** Steps whose card carries text the visitor sees, edited right on the card. */
const TEXT_TYPES = ['message', 'choice', 'input', 'booking', 'ai', 'end'];

const LINE = 'bg-indigo-300 dark:bg-indigo-400/60';

function VLine({ className = 'h-5' }: { className?: string }) {
    return <span className={`block w-[1.5px] shrink-0 ${LINE} ${className}`} aria-hidden />;
}

function ArrowHead() {
    return (
        <svg width="10" height="7" viewBox="0 0 10 7" className="-mt-px block shrink-0 text-indigo-300 dark:text-indigo-400/60" aria-hidden>
            <path d="M0 0L5 7L10 0Z" fill="currentColor" />
        </svg>
    );
}

/** What is being placed, as a name and an icon: shown in the place it would land. */
function placing(flow: Flow, dragging: Dragging): { label: string; visual: Visual } | null {
    if (!dragging) return null;
    if (dragging.kind === 'block') {
        const block = blockByKey(dragging.key);
        return block ? { label: block.label, visual: blockVisual(block.key) } : null;
    }
    const node = flow.nodes[dragging.id];
    return node ? { label: stepKind(node), visual: stepVisual(node) } : null;
}

/** Every step, grouped, as a menu: the click-and-keyboard way to add a step. */
function AddMenu({ slot, trigger }: { slot: Slot; trigger: ReactNode }) {
    const { flow, insertBlock } = useEditor();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>{trigger}</DropdownMenuTrigger>
            <DropdownMenuContent
                align="center"
                collisionPadding={12}
                className="max-h-[min(70vh,var(--radix-dropdown-menu-content-available-height))] w-72 overflow-y-auto"
            >
                {BLOCK_GROUPS.map((group, i) => (
                    <Fragment key={group}>
                        {i > 0 && <DropdownMenuSeparator />}
                        <DropdownMenuLabel className="text-muted-foreground text-[11px] tracking-wide uppercase">{group}</DropdownMenuLabel>
                        <DropdownMenuGroup>
                            {BLOCKS.filter((b) => b.group === group).map((block) => {
                                const allowed = canInsert(flow, slot, block.make().type);
                                return (
                                    <DropdownMenuItem
                                        key={block.key}
                                        disabled={!allowed}
                                        onSelect={() => insertBlock(slot, block.key)}
                                        className="gap-2.5"
                                    >
                                        <StepIcon visual={blockVisual(block.key)} className="size-6" />
                                        <span className="min-w-0">
                                            <span className="block text-sm">{block.label}</span>
                                            <span className="text-muted-foreground block text-xs">
                                                {allowed ? block.hint : 'Only where nothing follows'}
                                            </span>
                                        </span>
                                    </DropdownMenuItem>
                                );
                            })}
                        </DropdownMenuGroup>
                    </Fragment>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/** Whether what is being placed may go into a place, and the handlers that take it. */
function useDropTarget(slot: Slot) {
    const { flow, dragging, place } = useEditor();
    const [over, setOver] = useState(false);

    const accepts = useMemo(() => {
        if (!dragging) return false;
        if (dragging.kind === 'block') {
            const block = blockByKey(dragging.key);
            return block !== undefined && canInsert(flow, slot, block.make().type);
        }
        return moveStep(flow, dragging.id, slot) !== flow;
    }, [dragging, flow, slot]);

    // Nothing is highlighted once the placing is over.
    useEffect(() => {
        if (!dragging) setOver(false);
    }, [dragging]);

    const handlers = {
        onDragOver: (e: React.DragEvent) => {
            if (!accepts) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = dragging?.kind === 'step' ? 'move' : 'copy';
            if (!over) setOver(true);
        },
        onDragLeave: (e: React.DragEvent) => {
            // Moving between the parts of one place is not leaving it.
            if (!e.currentTarget.contains(e.relatedTarget as Node | null)) setOver(false);
        },
        onDrop: (e: React.DragEvent) => {
            e.preventDefault();
            setOver(false);
            if (accepts) place(slot);
        },
    };

    return { accepts, over, setOver, handlers, dragging, place: () => place(slot), preview: placing(flow, dragging) };
}

/**
 * A place something being placed can go: a wide target that says so, and
 * shows what would land there when the pointer is over it. It is a button,
 * so the pick-then-click route and the keyboard use the same place.
 */
function DropZone({
    over,
    pick,
    preview,
    onPlace,
    onHover,
    wide = false,
}: {
    over: boolean;
    pick: boolean;
    preview: { label: string; visual: Visual } | null;
    onPlace: () => void;
    onHover: (over: boolean) => void;
    wide?: boolean;
}) {
    return (
        <button
            type="button"
            onClick={onPlace}
            onMouseEnter={() => pick && onHover(true)}
            onMouseLeave={() => pick && onHover(false)}
            onFocus={() => onHover(true)}
            onBlur={() => onHover(false)}
            className={`my-1 flex items-center justify-center gap-2 rounded-xl border-2 border-dashed px-3 text-xs font-medium transition-all motion-reduce:transition-none ${
                wide ? 'h-16 w-60' : 'h-12 w-56'
            } ${
                over
                    ? 'scale-[1.03] border-indigo-500 bg-indigo-50 text-indigo-800 shadow-md dark:bg-indigo-500/20 dark:text-indigo-200'
                    : 'border-indigo-300 bg-indigo-50/60 text-indigo-600 hover:border-indigo-400 dark:border-indigo-500/50 dark:bg-indigo-500/10 dark:text-indigo-300'
            }`}
        >
            {over && preview ? (
                <>
                    <StepIcon visual={preview.visual} className="size-6" />
                    <span className="truncate">{preview.label}</span>
                </>
            ) : (
                <>
                    <Plus className="size-3.5 shrink-0" aria-hidden />
                    {pick ? 'Place here' : 'Drop here'}
                </>
            )}
        </button>
    );
}

/**
 * The line between two steps. On hover it shows a "+" for adding a step
 * there; while a step is being placed it opens into a wide target - only
 * where that step may go.
 */
function Gap({ slot, arrow = true }: { slot: Slot; arrow?: boolean }) {
    const { accepts, over, setOver, handlers, dragging, place, preview } = useDropTarget(slot);

    return (
        <div {...handlers} className="group/gap flex w-56 flex-col items-center">
            <VLine className="h-2.5" />
            {dragging ? (
                accepts ? (
                    <DropZone over={over} pick={Boolean(dragging.pick)} preview={preview} onPlace={place} onHover={setOver} />
                ) : (
                    <VLine className="h-5" />
                )
            ) : (
                <AddMenu
                    slot={slot}
                    trigger={
                        <button
                            type="button"
                            aria-label="Add a step here"
                            title="Add a step here"
                            className="bg-card text-muted-foreground pointer-fine:opacity-0 pointer-fine:group-hover/gap:opacity-100 pointer-fine:focus-visible:opacity-100 flex size-5 items-center justify-center rounded-full border border-indigo-200 shadow-xs transition hover:scale-110 hover:border-indigo-500 hover:text-indigo-600 focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none data-[state=open]:opacity-100 dark:border-indigo-500/40"
                        >
                            <Plus className="size-3" aria-hidden />
                        </button>
                    }
                />
            )}
            <VLine className="h-2.5" />
            {arrow && <ArrowHead />}
        </div>
    );
}

/** Where a path has nothing after it yet: add the next step, or place one here. */
function OpenEnd({ slot }: { slot: Slot }) {
    const { accepts, over, setOver, handlers, dragging, place, preview } = useDropTarget(slot);

    return (
        <div {...handlers} className="flex flex-col items-center">
            <VLine className="h-4" />
            <ArrowHead />
            {dragging && accepts ? (
                <DropZone over={over} pick={Boolean(dragging.pick)} preview={preview} onPlace={place} onHover={setOver} wide />
            ) : (
                <div className="border-border bg-card/60 mt-1 w-56 rounded-xl border-2 border-dashed p-3 text-center">
                    <AddMenu
                        slot={slot}
                        trigger={
                            <Button size="sm" variant="outline" className="h-7">
                                <Plus className="size-3.5" aria-hidden /> Add a step
                            </Button>
                        }
                    />
                    <p className="text-muted-foreground mt-1.5 text-[11px]">or drag a step here</p>
                </div>
            )}
        </div>
    );
}

/**
 * Text edited where it is shown: click it, type, and Enter or a click away
 * keeps it (Shift+Enter starts a new line; Esc puts it back). One edit is one
 * change to undo, however much was typed.
 */
function InlineText({
    value,
    label,
    placeholder,
    onCommit,
    onStart,
    onEditing,
    autoEdit = false,
    multiline = true,
    className = '',
}: {
    value: string;
    label: string;
    placeholder: string;
    onCommit: (text: string) => void;
    onStart?: () => void;
    onEditing?: (editing: boolean) => void;
    autoEdit?: boolean;
    multiline?: boolean;
    className?: string;
}) {
    const [editing, setEditing] = useState(autoEdit);
    const [draft, setDraft] = useState(value);
    const field = useRef<HTMLTextAreaElement>(null);

    useEffect(() => {
        onEditing?.(editing);
        // Only a change of state is news to the card.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [editing]);

    useLayoutEffect(() => {
        if (!editing) return;
        field.current?.focus();
        field.current?.select();
    }, [editing]);

    const commit = () => {
        setEditing(false);
        const text = draft.trim();
        if (text && text !== value) onCommit(text);
    };

    if (editing) {
        return (
            <textarea
                ref={field}
                value={draft}
                aria-label={label}
                maxLength={500}
                rows={multiline ? 2 : 1}
                onChange={(e) => setDraft(e.target.value)}
                onBlur={commit}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' && !(multiline && e.shiftKey)) {
                        e.preventDefault();
                        commit();
                    } else if (e.key === 'Escape') {
                        e.preventDefault();
                        e.stopPropagation();
                        setDraft(value);
                        setEditing(false);
                    }
                }}
                className={`bg-background text-foreground block [field-sizing:content] w-full resize-none rounded-md border border-indigo-400 px-1.5 py-1 leading-snug outline-none focus:ring-2 focus:ring-indigo-500/30 ${className}`}
            />
        );
    }

    return (
        <button
            type="button"
            onClick={() => {
                onStart?.();
                setDraft(value);
                setEditing(true);
            }}
            aria-label={`${label}: ${value || 'empty'}. Click to edit.`}
            title="Click to edit"
            className={`hover:bg-muted/70 focus-visible:ring-ring block w-full cursor-text rounded-md px-1.5 py-1 text-left leading-snug break-words focus-visible:ring-2 focus-visible:outline-none ${
                value ? 'text-foreground' : 'text-muted-foreground italic'
            } ${className}`}
        >
            {value || placeholder}
        </button>
    );
}

/** Required or optional, switched right on the card of a question the visitor types into. */
function RequiredToggle({ id, optional }: { id: string; optional: boolean }) {
    const { toggleRequired } = useEditor();
    return (
        <button
            type="button"
            role="switch"
            aria-checked={!optional}
            aria-label="Required"
            title={
                optional ? 'Optional: visitors can skip it. Click to make it required.' : 'Required: visitors must answer. Click to make it optional.'
            }
            onClick={() => toggleRequired(id)}
            className={`rounded-full border px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase transition-colors ${
                optional ? 'bg-card text-muted-foreground border-slate-300 hover:border-indigo-400' : 'border-rose-500 bg-rose-500 text-white'
            }`}
        >
            {optional ? 'Optional' : 'Required'}
        </button>
    );
}

function NodeMenu({ step }: { step: TreeStep }) {
    const { flow, openSettings, setDragging, move, canMove, duplicate, remove, collapsed, toggleCollapsed } = useEditor();
    const id = step.id;
    const movable = isMovable(flow.nodes[id]);
    const can = movable ? canMove(id) : { up: false, down: false };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label={`${stepKind(flow.nodes[id])} actions`}
                    className="text-muted-foreground hover:text-foreground rounded-md p-1 hover:bg-black/5 dark:hover:bg-white/10"
                >
                    <MoreHorizontal className="size-4" aria-hidden />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-52">
                <DropdownMenuItem onSelect={() => openSettings(id)}>
                    <Pencil className="size-3.5" aria-hidden /> Edit settings
                </DropdownMenuItem>
                {movable && (
                    <>
                        <DropdownMenuItem onSelect={() => setDragging({ kind: 'step', id, pick: true })}>
                            <Move className="size-3.5" aria-hidden /> Move to…
                        </DropdownMenuItem>
                        <DropdownMenuItem disabled={!can.up} onSelect={() => move(id, 'up')}>
                            <ArrowUp className="size-3.5" aria-hidden /> Move up
                        </DropdownMenuItem>
                        <DropdownMenuItem disabled={!can.down} onSelect={() => move(id, 'down')}>
                            <ArrowDown className="size-3.5" aria-hidden /> Move down
                        </DropdownMenuItem>
                        <DropdownMenuItem onSelect={() => duplicate(id)}>
                            <Copy className="size-3.5" aria-hidden /> Duplicate
                        </DropdownMenuItem>
                    </>
                )}
                {step.branches.length > 0 && (
                    <DropdownMenuItem onSelect={() => toggleCollapsed(id)}>
                        {collapsed.has(id) ? (
                            <>
                                <ChevronsUpDown className="size-3.5" aria-hidden /> Show its paths
                            </>
                        ) : (
                            <>
                                <ChevronsDownUp className="size-3.5" aria-hidden /> Fold its paths away
                            </>
                        )}
                    </DropdownMenuItem>
                )}
                <DropdownMenuSeparator />
                <DropdownMenuItem onSelect={() => remove(id)} className="text-red-600 focus:text-red-600 dark:text-red-400">
                    <Trash2 className="size-3.5" aria-hidden /> Delete
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function textLabel(type: string): { label: string; placeholder: string } {
    switch (type) {
        case 'message':
            return { label: 'Message text', placeholder: 'Write the message…' };
        case 'end':
            return { label: 'Closing message', placeholder: 'Write the closing message…' };
        default:
            return { label: 'Question text', placeholder: 'Write the question…' };
    }
}

/**
 * One step on the canvas. Its header opens the settings; the text and the
 * replies are edited right on the card; replies are added and removed here
 * too. Steps that simply lead on can be dragged by the card.
 */
function NodeCard({ step }: { step: TreeStep }) {
    const { selected, select, openSettings, setDragging, issues, setText, renameReply, addReply, removeReply, addQuickReplies } = useEditor();
    const node = step.node;
    const visual = stepVisual(node);
    const movable = isMovable(node);
    const problems = issues[step.id] ?? [];
    const errors = problems.filter((p) => p.level === 'error');
    const isSelected = selected === step.id;
    const [editing, setEditing] = useState(false);
    const [newReply, setNewReply] = useState<string | null>(null);
    const text = textLabel(node.type);
    const options = node.options ?? [];

    return (
        <div
            id={`flow-step-${step.id}`}
            data-flow-node={visual.tone.dot}
            // Not while typing: a drag would steal the text selection.
            draggable={movable && !editing}
            onDragStart={(e) => {
                e.dataTransfer.setData('text/plain', step.id);
                e.dataTransfer.effectAllowed = 'move';
                setDragging({ kind: 'step', id: step.id });
            }}
            onDragEnd={() => setDragging(null)}
            className={`group/card bg-card w-60 rounded-xl border shadow-sm transition-shadow hover:shadow-md ${visual.tone.edge} ${
                isSelected ? 'ring-2 ring-indigo-500 ring-offset-2 ring-offset-transparent' : errors.length > 0 ? 'ring-2 ring-red-400/70' : ''
            }`}
        >
            <div
                className={`flex items-center gap-2 rounded-t-xl px-3 py-2 ${visual.tone.head} ${movable ? 'cursor-grab active:cursor-grabbing' : ''}`}
            >
                <button
                    type="button"
                    onClick={() => openSettings(step.id)}
                    aria-pressed={isSelected}
                    title="Open this step's settings"
                    className="flex min-w-0 flex-1 items-center gap-2.5 text-left"
                >
                    <StepIcon visual={visual} className="size-8" />
                    <span className="min-w-0 flex-1">
                        <span className="text-foreground block truncate text-[13px] leading-tight font-semibold">{stepKind(node)}</span>
                        {node.type === 'end' ? (
                            <span className="text-muted-foreground block truncate text-[11px]">{outcomeLabel(node.outcome)}</span>
                        ) : (
                            !TEXT_TYPES.includes(node.type) && <span className="text-muted-foreground block truncate text-xs">{describe(node)}</span>
                        )}
                    </span>
                </button>
                <NodeMenu step={step} />
            </div>

            {TEXT_TYPES.includes(node.type) && (
                <div className="px-1.5 pt-1.5 pb-2">
                    <InlineText
                        value={node.text ?? ''}
                        label={text.label}
                        placeholder={text.placeholder}
                        onCommit={(value) => setText(step.id, value)}
                        onStart={() => select(step.id)}
                        onEditing={setEditing}
                        className="text-[13px]"
                    />
                </div>
            )}

            {node.type === 'choice' && (
                <div className="space-y-1 px-3 pb-3">
                    <ul className="space-y-1">
                        {options.map((o) => (
                            <li
                                key={o.id}
                                className="group/reply flex items-center gap-1 rounded-md border border-blue-100 bg-blue-50/50 pl-1.5 dark:border-blue-500/20 dark:bg-blue-500/5"
                            >
                                <Circle className="size-3 shrink-0 text-blue-400" aria-hidden />
                                <div className="min-w-0 flex-1">
                                    <InlineText
                                        value={o.label}
                                        label={`Reply “${o.label}”`}
                                        placeholder="Reply text"
                                        onCommit={(value) => renameReply(step.id, o.id, value)}
                                        onStart={() => select(step.id)}
                                        onEditing={(on) => {
                                            setEditing(on);
                                            if (!on && newReply === o.id) setNewReply(null);
                                        }}
                                        autoEdit={newReply === o.id}
                                        multiline={false}
                                        className="text-xs"
                                    />
                                </div>
                                {o.score ? (
                                    <span className="text-[11px] font-semibold text-blue-700 tabular-nums dark:text-blue-300">+{o.score}</span>
                                ) : null}
                                <button
                                    type="button"
                                    onClick={() => removeReply(step.id, o.id)}
                                    disabled={options.length <= 1}
                                    aria-label={`Remove the reply “${o.label}”`}
                                    title="Remove this reply"
                                    className="text-muted-foreground pointer-fine:opacity-0 pointer-fine:group-hover/reply:opacity-100 rounded p-1 hover:text-red-600 focus-visible:opacity-100 disabled:hidden"
                                >
                                    <X className="size-3" aria-hidden />
                                </button>
                            </li>
                        ))}
                    </ul>
                    <button
                        type="button"
                        onClick={() => setNewReply(addReply(step.id))}
                        className="flex w-full items-center gap-1 rounded-md px-1.5 py-1 text-xs font-medium text-blue-700 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-blue-500/10"
                    >
                        <Plus className="size-3" aria-hidden /> Add reply
                    </button>
                </div>
            )}

            {node.type === 'message' && (
                <div className="px-3 pb-2">
                    <button
                        type="button"
                        onClick={() => addQuickReplies(step.id)}
                        className="text-muted-foreground pointer-fine:opacity-0 pointer-fine:group-hover/card:opacity-100 flex items-center gap-1 rounded-md px-1.5 py-1 text-xs hover:bg-violet-50 hover:text-violet-700 focus-visible:opacity-100 dark:hover:bg-violet-500/10 dark:hover:text-violet-300"
                    >
                        <MessageSquarePlus className="size-3.5" aria-hidden /> Add quick replies
                    </button>
                </div>
            )}

            {node.type === 'input' && (
                <div className="flex items-center justify-between gap-2 border-t px-3 py-2">
                    <span className="text-muted-foreground text-[11px]">{node.optional ? 'Visitors may skip' : 'Visitors must answer'}</span>
                    <RequiredToggle id={step.id} optional={Boolean(node.optional)} />
                </div>
            )}

            {problems.length > 0 && (
                <p
                    className={`flex items-start gap-1 border-t px-3 py-2 text-[11px] leading-snug ${
                        errors.length > 0
                            ? 'border-red-200 text-red-600 dark:border-red-500/30 dark:text-red-400'
                            : 'border-amber-200 text-amber-700 dark:border-amber-500/30 dark:text-amber-400'
                    }`}
                >
                    {errors.length > 0 ? (
                        <AlertCircle className="mt-px size-3 shrink-0" aria-hidden />
                    ) : (
                        <AlertTriangle className="mt-px size-3 shrink-0" aria-hidden />
                    )}
                    {problems[0].message}
                </p>
            )}
        </div>
    );
}

/** The label on the line into a branch: the answers that lead there, tinted like the step they reach. */
function BranchPill({ branch, parent }: { branch: TreeBranch; parent: TreeStep }) {
    const tone = branch.steps[0] ? stepVisual(branch.steps[0].node).tone : NEUTRAL_TONE;
    const text =
        branch.slot.kind === 'answers'
            ? (parent.node.options ?? [])
                  .filter((o) => (branch.slot.kind === 'answers' ? branch.slot.answers : []).includes(o.id))
                  .map((o) => o.label || 'Untitled answer')
                  .join(' · ')
            : (branch.label ?? '');

    return (
        <span className={`max-w-52 truncate rounded-full border px-2.5 py-0.5 text-[11px] font-medium ${tone.pill}`} title={text}>
            {text}
        </span>
    );
}

/**
 * A step whose answers lead different ways: the paths fan out side by side,
 * each labelled, and where they meet again the lines come back together.
 * The joining lines are measured from where the columns actually land, so
 * they stay true however long the text in each branch is.
 */
function FanOut({ step }: { step: TreeStep }) {
    const row = useRef<HTMLDivElement>(null);
    const columns = useRef<(HTMLDivElement | null)[]>([]);
    const [bus, setBus] = useState<{ top: [number, number] | null; bottom: [number, number] | null }>({ top: null, bottom: null });

    useLayoutEffect(() => {
        const measure = () => {
            const el = row.current;
            if (!el) return;
            const centers = step.branches.map((_, i) => {
                const c = columns.current[i];
                return c ? c.offsetLeft + c.offsetWidth / 2 : 0;
            });
            const middle = el.offsetWidth / 2;
            // Every line heading for this split's meeting point, however deep
            // inside a branch it starts - a path from a question inside a
            // branch rejoins here too. Positions come from the screen, so they
            // are scaled back by the canvas zoom.
            const box = el.getBoundingClientRect();
            const scale = el.offsetWidth > 0 ? box.width / el.offsetWidth : 1;
            const joined = step.join
                ? [...el.querySelectorAll<HTMLElement>('[data-join-tail]')]
                      .filter((t) => t.dataset.joinTail === step.join)
                      .map((t) => {
                          const r = t.getBoundingClientRect();
                          return (r.left + r.width / 2 - box.left) / scale;
                      })
                : [];
            const next = {
                top: centers.length > 1 ? ([centers[0], centers[centers.length - 1]] as [number, number]) : null,
                bottom: step.join && joined.length > 0 ? ([Math.min(middle, ...joined), Math.max(middle, ...joined)] as [number, number]) : null,
            };
            setBus((prev) => (JSON.stringify(prev) === JSON.stringify(next) ? prev : next));
        };
        measure();
        const observer = new ResizeObserver(measure);
        if (row.current) observer.observe(row.current);
        columns.current.forEach((c) => c && observer.observe(c));
        return () => observer.disconnect();
    }, [step]);

    return (
        <>
            <VLine className="h-4" />
            {/* Without a meeting point below, the branches take the rest of the
                column's height, so lines inside them can reach an outer one. */}
            <div ref={row} className={`relative flex items-stretch ${step.join ? '' : 'flex-1'}`}>
                {bus.top && (
                    <span className={`absolute top-0 h-[1.5px] ${LINE}`} style={{ left: bus.top[0], width: bus.top[1] - bus.top[0] }} aria-hidden />
                )}
                {step.branches.map((branch, i) => (
                    <div
                        key={i}
                        ref={(el) => {
                            columns.current[i] = el;
                        }}
                        className="flex flex-col items-center px-3"
                    >
                        <VLine className="h-4" />
                        <BranchPill branch={branch} parent={step} />
                        <BranchColumn branch={branch} />
                    </div>
                ))}
                {bus.bottom && (
                    <span
                        className={`absolute bottom-0 h-[1.5px] ${LINE}`}
                        style={{ left: bus.bottom[0], width: Math.max(1.5, bus.bottom[1] - bus.bottom[0]) }}
                        aria-hidden
                    />
                )}
            </div>
        </>
    );
}

/**
 * A question whose paths are folded away: one line saying how much is
 * hidden, which opens them again. Lines from inside the fold that meet a path
 * further out still reach it.
 */
function FoldedPaths({ step }: { step: TreeStep }) {
    const { toggleCollapsed } = useEditor();
    const hidden = stepsInside(step);

    const inside = new Set<string>();
    const ends: string[] = [];
    const walk = (s: TreeStep) =>
        s.branches.forEach((b) => {
            b.steps.forEach((inner) => {
                inside.add(inner.id);
                walk(inner);
            });
            if (b.end.kind === 'joins') ends.push(b.end.to);
        });
    walk(step);
    const outer = [...new Set(ends)].filter((to) => to !== step.join && !inside.has(to));

    return (
        <>
            <VLine className="h-4" />
            <button
                type="button"
                onClick={() => toggleCollapsed(step.id)}
                className="bg-card text-muted-foreground hover:text-foreground flex items-center gap-1.5 rounded-full border border-dashed border-indigo-300 px-3 py-1 text-xs hover:border-indigo-500"
            >
                <ChevronsUpDown className="size-3.5" aria-hidden />
                {step.branches.length} paths, {hidden} {hidden === 1 ? 'step' : 'steps'} folded away · Show
            </button>
            {outer.map((to) => (
                <span key={to} data-join-tail={to} className={`block min-h-3 w-[1.5px] flex-1 ${LINE}`} aria-hidden />
            ))}
        </>
    );
}

function ColumnEnd({ branch }: { branch: TreeBranch }) {
    const { flow, select } = useEditor();
    const end = branch.end;
    if (!branch.endSlot) return null;

    if (end.kind === 'open') return <OpenEnd slot={branch.endSlot} />;
    if (end.kind === 'joins') {
        // The line down to where the paths meet again: it stretches to the
        // bottom of the branch, and the split's bus finds it by its target.
        return (
            <>
                <Gap slot={branch.endSlot} arrow={false} />
                <span data-join-tail={end.to} className={`block min-h-3 w-[1.5px] flex-1 ${LINE}`} aria-hidden />
            </>
        );
    }

    // A path that leads back to a step already drawn.
    return (
        <>
            <Gap slot={branch.endSlot} arrow={false} />
            {end.kind === 'jump' && (
                <button
                    type="button"
                    onClick={() => {
                        select(end.to);
                        document.getElementById(`flow-step-${end.to}`)?.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
                    }}
                    className="bg-card text-muted-foreground hover:text-foreground flex max-w-52 items-center gap-1.5 rounded-full border border-dashed border-indigo-300 px-2.5 py-1 text-[11px]"
                >
                    <CornerDownRight className="size-3 shrink-0" aria-hidden />
                    <span className="truncate">Continues at “{describe(flow.nodes[end.to])}”</span>
                </button>
            )}
        </>
    );
}

function BranchColumn({ branch }: { branch: TreeBranch }) {
    const { collapsed } = useEditor();
    return (
        <div className="flex flex-1 flex-col items-center">
            {branch.steps.map((step) => (
                <Fragment key={step.id}>
                    <Gap slot={step.via} />
                    <NodeCard step={step} />
                    {step.branches.length > 0 && (collapsed.has(step.id) ? <FoldedPaths step={step} /> : <FanOut step={step} />)}
                </Fragment>
            ))}
            <ColumnEnd branch={branch} />
        </div>
    );
}

type MiniView = {
    boxes: { x: number; y: number; w: number; h: number; color: string }[];
    frame: { x: number; y: number; w: number; h: number };
    scale: number;
    /** Whether the conversation is bigger than the view, so the map helps. */
    overflows: boolean;
};

/** A small overview of the whole canvas; click to jump there. */
function MiniMap({
    scroller,
    content,
    tick,
    onClose,
}: {
    scroller: React.RefObject<HTMLDivElement | null>;
    content: React.RefObject<HTMLDivElement | null>;
    tick: number;
    onClose: () => void;
}) {
    const W = 148;
    const H = 84;
    const [view, setView] = useState<MiniView | null>(null);

    useLayoutEffect(() => {
        const s = scroller.current;
        const c = content.current;
        if (!s || !c) return;
        const area = c.getBoundingClientRect();
        if (area.width === 0 || area.height === 0) return;
        const scale = Math.min(W / area.width, H / area.height);
        const boxes = [...c.querySelectorAll<HTMLElement>('[data-flow-node]')].map((n) => {
            const r = n.getBoundingClientRect();
            return {
                x: (r.left - area.left) * scale,
                y: (r.top - area.top) * scale,
                w: Math.max(3, r.width * scale),
                h: Math.max(2, r.height * scale),
                color: n.dataset.flowNode ?? '#94a3b8',
            };
        });
        const port = s.getBoundingClientRect();
        setView({
            boxes,
            scale,
            frame: { x: (port.left - area.left) * scale, y: (port.top - area.top) * scale, w: port.width * scale, h: port.height * scale },
            overflows: s.scrollWidth > s.clientWidth + 4 || s.scrollHeight > s.clientHeight + 4,
        });
    }, [tick, scroller, content]);

    if (!view || !view.overflows) return null;

    return (
        <div className="bg-card/95 absolute right-3 bottom-3 hidden rounded-lg border p-2 shadow-sm sm:block">
            <div className="mb-1 flex items-center justify-between">
                <p className="text-muted-foreground text-[11px] font-medium">Mini map</p>
                <button
                    type="button"
                    onClick={onClose}
                    aria-label="Hide the mini map"
                    className="text-muted-foreground hover:text-foreground -mr-1 rounded p-0.5"
                >
                    <X className="size-3" aria-hidden />
                </button>
            </div>
            <svg
                width={W}
                height={H}
                role="img"
                aria-label="Mini map of the conversation. Click to move the view."
                className="cursor-pointer"
                onClick={(e) => {
                    const s = scroller.current;
                    const c = content.current;
                    if (!s || !c) return;
                    const rect = e.currentTarget.getBoundingClientRect();
                    const x = (e.clientX - rect.left) / view.scale;
                    const y = (e.clientY - rect.top) / view.scale;
                    const area = c.getBoundingClientRect();
                    const port = s.getBoundingClientRect();
                    s.scrollBy({
                        left: area.left - port.left + x - port.width / 2,
                        top: area.top - port.top + y - port.height / 2,
                        behavior: 'smooth',
                    });
                }}
            >
                {view.boxes.map((b, i) => (
                    <rect key={i} x={b.x} y={b.y} width={b.w} height={b.h} rx={1.5} fill={b.color} opacity={0.75} />
                ))}
                <rect
                    x={Math.max(0, view.frame.x)}
                    y={Math.max(0, view.frame.y)}
                    width={Math.min(W, view.frame.w)}
                    height={Math.min(H, view.frame.h)}
                    fill="none"
                    stroke="currentColor"
                    className="text-indigo-500"
                    strokeWidth={1}
                    rx={2}
                />
            </svg>
        </div>
    );
}

/** Steps nothing leads to: shown, so they can be put back in or removed. */
function Unreachable({ ids }: { ids: string[] }) {
    const { flow, openSettings, setDragging, remove } = useEditor();
    if (ids.length === 0) return null;

    return (
        <section className="bg-card/80 mt-10 w-full max-w-3xl rounded-xl border border-dashed p-3">
            <h3 className="text-foreground text-sm font-semibold">Not in the conversation</h3>
            <p className="text-muted-foreground mb-2 text-xs">
                Nothing leads to these, so visitors never see them. Drag one onto a line, or remove it.
            </p>
            <div className="flex flex-wrap gap-2">
                {ids.map((id) => {
                    const node = flow.nodes[id];
                    return (
                        <div
                            key={id}
                            draggable={isMovable(node)}
                            onDragStart={(e) => {
                                e.dataTransfer.setData('text/plain', id);
                                setDragging({ kind: 'step', id });
                            }}
                            onDragEnd={() => setDragging(null)}
                            className={`flex items-center gap-2 rounded-lg border p-1.5 pr-1 ${stepVisual(node).tone.card}`}
                        >
                            <button type="button" onClick={() => openSettings(id)} className="flex max-w-64 min-w-0 items-center gap-2 text-left">
                                <StepIcon visual={stepVisual(node)} className="size-6" />
                                <span className="min-w-0 truncate text-xs">
                                    <span className="font-semibold">{stepKind(node)}:</span> {describe(node)}
                                </span>
                            </button>
                            <Button size="sm" variant="ghost" className="h-7 px-2" onClick={() => remove(id)} aria-label={`Remove ${stepKind(node)}`}>
                                <Trash2 className="size-3.5" aria-hidden />
                            </Button>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}

/**
 * The canvas: the conversation drawn as a tree from Start, on a dotted board
 * that scrolls and zooms, with a mini map when it outgrows the view. While a
 * step is being placed, a banner says so and the board scrolls by itself
 * near its edges.
 */
export function FlowCanvas({ className = 'h-[calc(100vh-16rem)] min-h-[560px]', toolbar }: { className?: string; toolbar?: ReactNode }) {
    const { flow, dragging, setDragging } = useEditor();
    const { root, unreachable } = useMemo(() => buildTree(flow), [flow]);
    const scroller = useRef<HTMLDivElement>(null);
    const content = useRef<HTMLDivElement>(null);
    const [zoom, setZoom] = useState(1);
    const [tick, setTick] = useState(0);
    const [showMap, setShowMap] = useState(true);
    const bump = useCallback(() => requestAnimationFrame(() => setTick((t) => t + 1)), []);

    useEffect(() => {
        bump();
    }, [flow, zoom, bump]);

    // Open with the whole conversation in view where it fits at a readable
    // size (never below 85%); a wider tree scrolls sideways, centred on Start...
    const fitted = useRef(false);
    useLayoutEffect(() => {
        if (fitted.current) return;
        const s = scroller.current;
        const c = content.current;
        if (!s || !c) return;
        fitted.current = true;
        const natural = c.getBoundingClientRect().width;
        if (natural > s.clientWidth && s.clientWidth > 0) setZoom(Math.max(0.85, Math.floor(((s.clientWidth - 32) / natural) * 20) / 20));
    }, []);

    // ...and keep it centred on Start whenever the zoom or the room changes.
    const centre = useCallback(() => {
        const s = scroller.current;
        if (s) s.scrollLeft = (s.scrollWidth - s.clientWidth) / 2;
    }, []);
    useEffect(centre, [zoom, centre]);

    useEffect(() => {
        const s = scroller.current;
        if (!s) return;
        let width = s.clientWidth;
        const observer = new ResizeObserver(() => {
            bump();
            // A panel opened or closed: the view keeps Start in the middle.
            if (Math.abs(s.clientWidth - width) > 40) centre();
            width = s.clientWidth;
        });
        observer.observe(s);
        return () => observer.disconnect();
    }, [bump, centre]);

    const zoomBy = (delta: number) => setZoom((z) => Math.min(1.5, Math.max(0.4, Math.round((z + delta) * 10) / 10)));
    const fit = () => {
        const s = scroller.current;
        const c = content.current;
        if (!s || !c) return;
        const natural = c.getBoundingClientRect().width / zoom;
        setZoom(Math.min(1, Math.max(0.4, Math.floor(((s.clientWidth - 32) / natural) * 20) / 20)));
    };

    const startVisual = blockVisual('start');
    const picked = dragging?.pick ? placing(flow, dragging) : null;

    return (
        <div className={`bg-muted/30 relative overflow-hidden rounded-xl border ${className}`}>
            <div
                ref={scroller}
                onScroll={bump}
                onWheel={(e) => {
                    if (!e.ctrlKey) return;
                    e.preventDefault();
                    zoomBy(e.deltaY < 0 ? 0.1 : -0.1);
                }}
                onDragOver={(e) => {
                    // Near an edge while dragging, the board scrolls to show more.
                    const s = scroller.current;
                    if (!s || !dragging) return;
                    const r = s.getBoundingClientRect();
                    const edge = 64;
                    const step = 16;
                    if (e.clientY < r.top + edge) s.scrollTop -= step;
                    else if (e.clientY > r.bottom - edge) s.scrollTop += step;
                    if (e.clientX < r.left + edge) s.scrollLeft -= step;
                    else if (e.clientX > r.right - edge) s.scrollLeft += step;
                }}
                className="h-full w-full overflow-auto overscroll-contain"
                style={{
                    backgroundImage: 'radial-gradient(circle, color-mix(in oklab, var(--foreground) 14%, transparent) 1px, transparent 1.3px)',
                    backgroundSize: '20px 20px',
                }}
            >
                <div ref={content} style={{ zoom }} className="mx-auto flex w-max flex-col items-center px-12 pt-20 pb-28">
                    <div
                        data-flow-node={startVisual.tone.dot}
                        className={`bg-card flex w-60 items-center gap-2.5 rounded-xl border px-3 py-2.5 shadow-sm ${startVisual.tone.edge}`}
                    >
                        <StepIcon visual={startVisual} className="size-8" />
                        <span>
                            <span className="text-foreground block text-[13px] font-semibold">Start</span>
                            <span className="text-muted-foreground block text-xs">Conversation starts here</span>
                        </span>
                    </div>
                    <BranchColumn branch={root} />
                    <Unreachable ids={unreachable} />
                </div>
            </div>

            {picked && (
                <div
                    role="status"
                    className="absolute top-3 left-1/2 z-10 flex max-w-[calc(100%-1.5rem)] -translate-x-1/2 items-center gap-2 rounded-full bg-indigo-600 py-1 pr-1 pl-1.5 text-sm text-white shadow-lg"
                >
                    <StepIcon visual={picked.visual} className="size-6" />
                    <span className="truncate">
                        Click a highlighted place for <strong className="font-semibold">{picked.label}</strong>
                    </span>
                    <button
                        type="button"
                        onClick={() => setDragging(null)}
                        className="shrink-0 rounded-full bg-white/20 px-2.5 py-0.5 text-xs font-medium hover:bg-white/30"
                    >
                        Cancel
                    </button>
                </div>
            )}

            {toolbar && !picked && <div className="absolute top-3 right-3 z-10">{toolbar}</div>}

            <div className="bg-card absolute bottom-3 left-3 flex items-center gap-0.5 rounded-lg border p-1 shadow-sm">
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    onClick={() => zoomBy(-0.1)}
                    aria-label="Zoom out"
                    title="Zoom out (Ctrl + scroll)"
                >
                    <ZoomOut className="size-4" aria-hidden />
                </Button>
                <button
                    type="button"
                    onClick={() => setZoom(1)}
                    className="text-muted-foreground hover:text-foreground w-12 text-center text-xs tabular-nums"
                    title="Reset to 100%"
                >
                    {Math.round(zoom * 100)}%
                </button>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    onClick={() => zoomBy(0.1)}
                    aria-label="Zoom in"
                    title="Zoom in (Ctrl + scroll)"
                >
                    <ZoomIn className="size-4" aria-hidden />
                </Button>
                <span className="bg-border mx-1 h-5 w-px" aria-hidden />
                <Button variant="ghost" size="icon" className="size-8" onClick={fit} aria-label="Fit to screen" title="Fit to screen">
                    <ScanLine className="size-4" aria-hidden />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    className={`size-8 ${showMap ? 'text-indigo-600 dark:text-indigo-300' : ''}`}
                    onClick={() => setShowMap((on) => !on)}
                    aria-label={showMap ? 'Hide the mini map' : 'Show the mini map'}
                    aria-pressed={showMap}
                    title="Mini map"
                >
                    <MapIcon className="size-4" aria-hidden />
                </Button>
            </div>

            {showMap && <MiniMap scroller={scroller} content={content} tick={tick} onClose={() => setShowMap(false)} />}
        </div>
    );
}
