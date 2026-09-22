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
import { BLOCK_GROUPS, blockByKey, BLOCKS, stepKind } from '@/lib/flow-blocks';
import { buildTree, canInsert, describe, type Flow, isMovable, moveStep, type Slot, type TreeBranch, type TreeStep } from '@/lib/flow-tree';
import { AlertCircle, AlertTriangle, CornerDownRight, GitMerge, GripVertical, Plus, Trash2 } from 'lucide-react';
import { createContext, Fragment, type ReactNode, useContext, useMemo, useState } from 'react';
import { blockVisual, StepIcon, stepVisual } from './step-visuals';

/** What is being dragged: a new block from the library, or a step already in the tree. */
export type Dragging = { kind: 'block'; key: string } | { kind: 'step'; id: string } | null;

export type Issue = { level: 'error' | 'warning'; message: string };

type Editor = {
    flow: Flow;
    selected: string | null;
    select: (id: string | null) => void;
    dragging: Dragging;
    setDragging: (dragging: Dragging) => void;
    insertBlock: (slot: Slot, key: string) => void;
    moveTo: (id: string, slot: Slot) => void;
    toggleRequired: (id: string) => void;
    remove: (id: string) => void;
    issues: Record<string, Issue[]>;
};

export const FlowEditorContext = createContext<Editor | null>(null);

function useEditor(): Editor {
    const editor = useContext(FlowEditorContext);
    if (!editor) throw new Error('FlowEditorContext missing');
    return editor;
}

/** The blocks, grouped, as a menu: the click-and-keyboard way to add a step to a gap. */
function AddMenu({ slot, trigger }: { slot: Slot; trigger: ReactNode }) {
    const { flow, insertBlock } = useEditor();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>{trigger}</DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="max-h-[70vh] w-72 overflow-y-auto">
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

/**
 * A gap in the conversation. While something is being dragged it becomes a
 * drop target (only where that block or step may go); otherwise it offers a
 * "+" that opens the same blocks as a menu.
 */
function DropZone({ slot, variant = 'between', children }: { slot: Slot; variant?: 'between' | 'end'; children?: ReactNode }) {
    const { flow, dragging, setDragging, insertBlock, moveTo } = useEditor();
    const [over, setOver] = useState(false);

    const accepts = useMemo(() => {
        if (!dragging) return false;
        if (dragging.kind === 'block') {
            const block = blockByKey(dragging.key);
            return block !== undefined && canInsert(flow, slot, block.make().type);
        }
        return moveStep(flow, dragging.id, slot) !== flow;
    }, [dragging, flow, slot]);

    const handlers = {
        onDragOver: (e: React.DragEvent) => {
            if (!accepts) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = dragging?.kind === 'step' ? 'move' : 'copy';
            if (!over) setOver(true);
        },
        onDragLeave: () => setOver(false),
        onDrop: (e: React.DragEvent) => {
            e.preventDefault();
            setOver(false);
            if (!accepts || !dragging) return;
            if (dragging.kind === 'block') insertBlock(slot, dragging.key);
            else moveTo(dragging.id, slot);
            setDragging(null);
        },
    };

    if (variant === 'end') {
        return (
            <div
                {...handlers}
                className={`rounded-xl border-2 border-dashed p-3 transition-colors ${
                    over ? 'border-brand bg-brand-soft' : accepts ? 'border-brand/50 bg-brand-soft/40' : 'border-border'
                }`}
            >
                <div className="flex flex-wrap items-center gap-2">
                    <AddMenu
                        slot={slot}
                        trigger={
                            <Button size="sm" variant="outline">
                                <Plus className="size-3.5" aria-hidden /> Add a step
                            </Button>
                        }
                    />
                    <span className="text-muted-foreground text-xs">{over ? 'Drop to add it here' : 'or drag a block here'}</span>
                </div>
                {children}
            </div>
        );
    }

    return (
        <div {...handlers} className="relative flex h-8 items-center">
            <span className="bg-border absolute inset-y-0 left-[27px] w-px" aria-hidden />
            {dragging ? (
                accepts && (
                    <span
                        className={`relative ml-2 rounded-md border-2 border-dashed px-3 py-0.5 text-xs font-medium transition-colors ${
                            over ? 'border-brand bg-brand-soft text-brand-strong' : 'border-brand/40 bg-card text-muted-foreground'
                        }`}
                    >
                        Drop here
                    </span>
                )
            ) : (
                <AddMenu
                    slot={slot}
                    trigger={
                        <button
                            type="button"
                            aria-label="Add a step here"
                            className="bg-card border-border text-muted-foreground hover:border-brand hover:text-brand-strong focus-visible:ring-brand relative ml-[17px] flex size-5 items-center justify-center rounded-full border opacity-60 transition hover:opacity-100 focus-visible:opacity-100 focus-visible:ring-2 focus-visible:outline-none"
                        >
                            <Plus className="size-3" aria-hidden />
                        </button>
                    }
                />
            )}
        </div>
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
            className={`shrink-0 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold transition-colors ${
                optional ? 'border-border text-muted-foreground hover:border-brand/50' : 'border-brand bg-brand text-brand-foreground'
            }`}
        >
            {optional ? 'Optional' : 'Required'}
        </button>
    );
}

function StepCard({ step }: { step: TreeStep }) {
    const { selected, select, setDragging, issues } = useEditor();
    const node = step.node;
    const movable = isMovable(node);
    const problems = issues[step.id] ?? [];
    const errors = problems.filter((p) => p.level === 'error');
    const isSelected = selected === step.id;

    return (
        <div
            id={`flow-step-${step.id}`}
            draggable={movable}
            onDragStart={(e) => {
                e.dataTransfer.setData('text/plain', step.id);
                e.dataTransfer.effectAllowed = 'move';
                setDragging({ kind: 'step', id: step.id });
            }}
            onDragEnd={() => setDragging(null)}
            className={`group bg-card relative flex items-start gap-2 rounded-xl border p-3 shadow-xs transition-colors ${
                isSelected ? 'border-brand ring-brand/25 ring-2' : errors.length > 0 ? 'border-red-400/70' : 'border-border hover:border-brand/50'
            } ${movable ? 'cursor-grab active:cursor-grabbing' : ''}`}
        >
            <button
                type="button"
                onClick={() => select(step.id)}
                aria-pressed={isSelected}
                className="flex min-w-0 flex-1 items-start gap-3 text-left"
            >
                <StepIcon visual={stepVisual(node)} />
                <span className="min-w-0 flex-1">
                    <span className="text-muted-foreground block text-[11px] font-semibold tracking-wide uppercase">{stepKind(node)}</span>
                    <span className="text-foreground mt-0.5 line-clamp-2 block text-sm">{describe(node)}</span>
                    {node.type === 'choice' && (node.options ?? []).length > 0 && (
                        <span className="mt-2 flex flex-wrap gap-1">
                            {(node.options ?? []).map((o) => (
                                <span key={o.id} className="border-border bg-background rounded-md border px-1.5 py-0.5 text-xs">
                                    {o.label || 'Untitled answer'}
                                    {o.score ? <span className="text-brand-strong ml-1 font-semibold tabular-nums">+{o.score}</span> : null}
                                </span>
                            ))}
                        </span>
                    )}
                    {problems.length > 0 && (
                        <span
                            className={`mt-1.5 flex items-start gap-1 text-xs ${errors.length > 0 ? 'text-red-600 dark:text-red-400' : 'text-amber-700 dark:text-amber-400'}`}
                        >
                            {errors.length > 0 ? (
                                <AlertCircle className="mt-px size-3.5 shrink-0" aria-hidden />
                            ) : (
                                <AlertTriangle className="mt-px size-3.5 shrink-0" aria-hidden />
                            )}
                            {problems[0].message}
                        </span>
                    )}
                </span>
            </button>
            {node.type === 'input' && <RequiredToggle id={step.id} optional={Boolean(node.optional)} />}
            {movable && (
                <GripVertical className="text-muted-foreground/60 mt-1 size-4 shrink-0 opacity-0 transition group-hover:opacity-100" aria-hidden />
            )}
        </div>
    );
}

/** Which answers lead down a branch, as the chips the visitor sees. */
function BranchHeader({ branch, parent }: { branch: TreeBranch; parent: TreeStep }) {
    if (branch.slot.kind === 'answers') {
        const chosen = new Set(branch.slot.answers);
        const labels = (parent.node.options ?? []).filter((o) => chosen.has(o.id)).map((o) => o.label || 'Untitled answer');
        return (
            <div className="mb-1 flex flex-wrap items-center gap-1 text-xs">
                <span className="text-muted-foreground">If they choose</span>
                {labels.map((label, i) => (
                    <span key={i} className="bg-brand-soft text-brand-strong rounded-md px-1.5 py-0.5 font-medium">
                        {label}
                    </span>
                ))}
            </div>
        );
    }
    return <div className="text-muted-foreground mb-1 text-xs font-medium">{branch.label}</div>;
}

function BranchEnd({ branch }: { branch: TreeBranch }) {
    const { flow, select } = useEditor();
    const end = branch.end;
    if (!branch.endSlot) return null;

    if (end.kind === 'open') {
        return (
            <>
                <div className="relative h-3" aria-hidden>
                    <span className="bg-border absolute inset-y-0 left-[27px] w-px" />
                </div>
                <DropZone slot={branch.endSlot} variant="end">
                    <p className="text-muted-foreground mt-1.5 text-xs">
                        Nothing happens after this yet. Add the next step, or a Finish step to end the chat here.
                    </p>
                </DropZone>
            </>
        );
    }

    return (
        <>
            <DropZone slot={branch.endSlot} />
            <p className="text-muted-foreground flex items-center gap-1.5 pl-3 text-xs">
                <CornerDownRight className="size-3.5" aria-hidden />
                {end.kind === 'jump' ? (
                    <>
                        Continues at
                        <button
                            type="button"
                            className="text-foreground underline underline-offset-2"
                            onClick={() => {
                                select(end.to);
                                document.getElementById(`flow-step-${end.to}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }}
                        >
                            “{describe(flow.nodes[end.to]).slice(0, 48)}”
                        </button>
                    </>
                ) : branch.steps.length === 0 ? (
                    'Goes straight on below'
                ) : (
                    'Then carries on below'
                )}
            </p>
        </>
    );
}

function BranchView({ branch }: { branch: TreeBranch }) {
    return (
        <div>
            {branch.steps.map((step) => (
                <Fragment key={step.id}>
                    <DropZone slot={step.via} />
                    <StepCard step={step} />
                    {step.branches.length > 0 && (
                        <div className="mt-3 space-y-3 pl-4 sm:pl-7">
                            {step.branches.map((b, i) => (
                                <section key={i} className="border-brand/40 bg-muted/25 rounded-r-xl border-l-2 py-2 pr-2 pl-3">
                                    <BranchHeader branch={b} parent={step} />
                                    <BranchView branch={b} />
                                </section>
                            ))}
                        </div>
                    )}
                    {step.branches.length > 0 && step.join && (
                        <p className="text-muted-foreground mt-2 flex items-center gap-1.5 pl-3 text-xs font-medium">
                            <GitMerge className="size-3.5" aria-hidden /> Paths meet again here
                        </p>
                    )}
                </Fragment>
            ))}
            <BranchEnd branch={branch} />
        </div>
    );
}

/** Steps nothing leads to: shown, so they can be dragged back in or removed. */
function Unreachable({ ids }: { ids: string[] }) {
    const { flow, select, selected, setDragging, remove } = useEditor();
    if (ids.length === 0) return null;

    return (
        <section className="border-border mt-8 rounded-xl border border-dashed p-3">
            <h3 className="text-foreground text-sm font-semibold">Not in the conversation</h3>
            <p className="text-muted-foreground mb-2 text-xs">
                Nothing leads to these, so visitors never see them. Drag one into the tree, or remove it.
            </p>
            <ul className="space-y-2">
                {ids.map((id) => {
                    const node = flow.nodes[id];
                    return (
                        <li
                            key={id}
                            draggable={isMovable(node)}
                            onDragStart={(e) => {
                                e.dataTransfer.setData('text/plain', id);
                                setDragging({ kind: 'step', id });
                            }}
                            onDragEnd={() => setDragging(null)}
                            className={`bg-card flex items-center gap-2 rounded-lg border p-2 ${selected === id ? 'border-brand' : 'border-border'}`}
                        >
                            <button type="button" onClick={() => select(id)} className="flex min-w-0 flex-1 items-center gap-2 text-left">
                                <StepIcon visual={stepVisual(node)} className="size-6" />
                                <span className="min-w-0 truncate text-sm">
                                    <span className="text-muted-foreground mr-1 text-xs">{stepKind(node)}:</span>
                                    {describe(node)}
                                </span>
                            </button>
                            <Button size="sm" variant="ghost" onClick={() => remove(id)} aria-label={`Remove ${stepKind(node)}`}>
                                <Trash2 className="size-3.5" aria-hidden />
                            </Button>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

/** The whole conversation, top to bottom, as the visitor would go through it. */
export function FlowCanvas() {
    const { flow } = useEditor();
    const { root, unreachable } = useMemo(() => buildTree(flow), [flow]);

    return (
        <div className="mx-auto w-full max-w-2xl">
            <p className="text-muted-foreground pl-3 text-xs font-semibold tracking-wide uppercase">Chat opens</p>
            <BranchView branch={root} />
            <Unreachable ids={unreachable} />
        </div>
    );
}
