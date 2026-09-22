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
import { buildTree, canInsert, describe, type Flow, isMovable, moveStep, type Slot, type TreeBranch, type TreeStep } from '@/lib/flow-tree';
import {
    AlertCircle,
    AlertTriangle,
    ArrowDown,
    ArrowUp,
    Circle,
    CornerDownRight,
    Expand,
    MoreHorizontal,
    Pencil,
    Plus,
    ScanLine,
    Trash2,
    ZoomIn,
    ZoomOut,
} from 'lucide-react';
import { createContext, Fragment, type ReactNode, useCallback, useContext, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { blockVisual, NEUTRAL_TONE, StepIcon, stepVisual } from './step-visuals';

/** What is being dragged: a new step from the library, or a step already on the canvas. */
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
    move: (id: string, direction: 'up' | 'down') => void;
    canMove: (id: string) => { up: boolean; down: boolean };
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

const LINE = 'bg-indigo-400 dark:bg-indigo-400/70';

function VLine({ className = 'h-5' }: { className?: string }) {
    return <span className={`block w-[1.5px] shrink-0 ${LINE} ${className}`} aria-hidden />;
}

function ArrowHead() {
    return (
        <svg width="10" height="7" viewBox="0 0 10 7" className="-mt-px block shrink-0 text-indigo-400 dark:text-indigo-400/70" aria-hidden>
            <path d="M0 0L5 7L10 0Z" fill="currentColor" />
        </svg>
    );
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

/** Whether what is being dragged may go into a place, and the handlers that take the drop. */
function useDropTarget(slot: Slot) {
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

    return { accepts, over, handlers, dragging };
}

/**
 * The line between two steps. It carries a small "+" for adding a step there,
 * and while something is dragged it becomes a drop target - only where that
 * step may go.
 */
function Gap({ slot, arrow = true }: { slot: Slot; arrow?: boolean }) {
    const { accepts, over, handlers, dragging } = useDropTarget(slot);

    return (
        <div {...handlers} className="group/gap flex w-48 flex-col items-center">
            <VLine className="h-2.5" />
            {dragging ? (
                accepts ? (
                    <span
                        className={`rounded-md border-2 border-dashed px-3 py-0.5 text-xs font-medium transition-colors ${
                            over
                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300'
                                : 'bg-card text-muted-foreground border-indigo-300'
                        }`}
                    >
                        Drop here
                    </span>
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
                            className="bg-card text-muted-foreground flex size-5 items-center justify-center rounded-full border border-indigo-200 opacity-50 shadow-xs transition group-hover/gap:opacity-100 hover:border-indigo-500 hover:text-indigo-600 focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none dark:border-indigo-500/40"
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

/** Where a path has nothing after it yet: add the next step, or drop one here. */
function OpenEnd({ slot }: { slot: Slot }) {
    const { accepts, over, handlers } = useDropTarget(slot);

    return (
        <div className="flex flex-col items-center">
            <VLine className="h-4" />
            <ArrowHead />
            <div
                {...handlers}
                className={`w-48 rounded-xl border-2 border-dashed p-3 text-center transition-colors ${
                    over
                        ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-500/15'
                        : accepts
                          ? 'border-indigo-300 bg-indigo-50/50'
                          : 'border-border bg-card/60'
                }`}
            >
                <AddMenu
                    slot={slot}
                    trigger={
                        <Button size="sm" variant="outline" className="h-7">
                            <Plus className="size-3.5" aria-hidden /> Add a step
                        </Button>
                    }
                />
                <p className="text-muted-foreground mt-1.5 text-[11px]">{over ? 'Drop to add it here' : 'or drag a step here'}</p>
            </div>
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
            className={`rounded-full border px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase transition-colors ${
                optional ? 'bg-card text-muted-foreground border-slate-300 hover:border-indigo-400' : 'border-rose-500 bg-rose-500 text-white'
            }`}
        >
            {optional ? 'Optional' : 'Required'}
        </button>
    );
}

function NodeMenu({ id }: { id: string }) {
    const { flow, select, move, canMove, remove } = useEditor();
    const movable = isMovable(flow.nodes[id]);
    const can = movable ? canMove(id) : { up: false, down: false };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label={`${stepKind(flow.nodes[id])} actions`}
                    className="text-muted-foreground hover:text-foreground -mt-0.5 -mr-1 rounded-md p-1 hover:bg-black/5 dark:hover:bg-white/10"
                >
                    <MoreHorizontal className="size-4" aria-hidden />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                <DropdownMenuItem onSelect={() => select(id)}>
                    <Pencil className="size-3.5" aria-hidden /> Edit step
                </DropdownMenuItem>
                {movable && (
                    <>
                        <DropdownMenuItem disabled={!can.up} onSelect={() => move(id, 'up')}>
                            <ArrowUp className="size-3.5" aria-hidden /> Move up
                        </DropdownMenuItem>
                        <DropdownMenuItem disabled={!can.down} onSelect={() => move(id, 'down')}>
                            <ArrowDown className="size-3.5" aria-hidden /> Move down
                        </DropdownMenuItem>
                    </>
                )}
                <DropdownMenuSeparator />
                <DropdownMenuItem onSelect={() => remove(id)} className="text-red-600 focus:text-red-600 dark:text-red-400">
                    <Trash2 className="size-3.5" aria-hidden /> Delete
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/** One step on the canvas, as a card tinted by what it does. */
function NodeCard({ step }: { step: TreeStep }) {
    const { selected, select, setDragging, issues } = useEditor();
    const node = step.node;
    const visual = stepVisual(node);
    const movable = isMovable(node);
    const problems = issues[step.id] ?? [];
    const errors = problems.filter((p) => p.level === 'error');
    const isSelected = selected === step.id;

    return (
        <div
            id={`flow-step-${step.id}`}
            data-flow-node={visual.tone.dot}
            draggable={movable}
            onDragStart={(e) => {
                e.dataTransfer.setData('text/plain', step.id);
                e.dataTransfer.effectAllowed = 'move';
                setDragging({ kind: 'step', id: step.id });
            }}
            onDragEnd={() => setDragging(null)}
            className={`w-52 rounded-xl border shadow-sm transition-shadow hover:shadow-md ${visual.tone.card} ${
                isSelected ? 'ring-2 ring-indigo-500 ring-offset-2 ring-offset-transparent' : errors.length > 0 ? 'ring-2 ring-red-400/70' : ''
            } ${movable ? 'cursor-grab active:cursor-grabbing' : ''}`}
        >
            <div className="flex items-start gap-2.5 p-3">
                <button
                    type="button"
                    onClick={() => select(step.id)}
                    aria-pressed={isSelected}
                    className="flex min-w-0 flex-1 items-start gap-2.5 text-left"
                >
                    <StepIcon visual={visual} />
                    <span className="min-w-0 flex-1">
                        <span className="text-foreground block text-[13px] leading-tight font-semibold">{stepKind(node)}</span>
                        <span className="text-muted-foreground mt-0.5 line-clamp-3 block text-xs leading-snug">{describe(node)}</span>
                        {node.type === 'end' && (
                            <span className="text-muted-foreground/80 mt-0.5 block text-[11px]">{outcomeLabel(node.outcome)}</span>
                        )}
                    </span>
                </button>
                <NodeMenu id={step.id} />
            </div>

            {node.type === 'choice' && (node.options ?? []).length > 0 && (
                <ul className="space-y-1 px-3 pb-3">
                    {(node.options ?? []).map((o) => (
                        <li
                            key={o.id}
                            className="bg-card/90 text-foreground flex items-center gap-2 rounded-md border border-blue-100 px-2 py-1 text-xs dark:border-blue-500/20"
                        >
                            <Circle className="size-3 shrink-0 text-blue-400" aria-hidden />
                            <span className="min-w-0 flex-1 truncate">{o.label || 'Untitled answer'}</span>
                            {o.score ? <span className="font-semibold text-blue-700 tabular-nums dark:text-blue-300">+{o.score}</span> : null}
                        </li>
                    ))}
                </ul>
            )}

            {node.type === 'input' && (
                <div className="flex items-center justify-between gap-2 px-3 pb-3">
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
        <span className={`max-w-48 truncate rounded-full border px-2.5 py-0.5 text-[11px] font-medium ${tone.pill}`} title={text}>
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
                        className="flex flex-col items-center px-2"
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
                    className="bg-card text-muted-foreground hover:text-foreground flex max-w-48 items-center gap-1.5 rounded-full border border-dashed border-indigo-300 px-2.5 py-1 text-[11px]"
                >
                    <CornerDownRight className="size-3 shrink-0" aria-hidden />
                    <span className="truncate">Continues at “{describe(flow.nodes[end.to])}”</span>
                </button>
            )}
        </>
    );
}

function BranchColumn({ branch }: { branch: TreeBranch }) {
    return (
        <div className="flex flex-1 flex-col items-center">
            {branch.steps.map((step) => (
                <Fragment key={step.id}>
                    <Gap slot={step.via} />
                    <NodeCard step={step} />
                    {step.branches.length > 0 && <FanOut step={step} />}
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
};

/** A small overview of the whole canvas; click to jump there. */
function MiniMap({
    scroller,
    content,
    tick,
}: {
    scroller: React.RefObject<HTMLDivElement | null>;
    content: React.RefObject<HTMLDivElement | null>;
    tick: number;
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
        });
    }, [tick, scroller, content]);

    if (!view) return null;

    return (
        <div className="bg-card/95 absolute right-3 bottom-3 hidden rounded-lg border p-2 shadow-sm sm:block">
            <p className="text-muted-foreground mb-1 text-[11px] font-medium">Mini map</p>
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

/** Steps nothing leads to: shown, so they can be dragged back in or removed. */
function Unreachable({ ids }: { ids: string[] }) {
    const { flow, select, setDragging, remove } = useEditor();
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
                            <button type="button" onClick={() => select(id)} className="flex max-w-64 min-w-0 items-center gap-2 text-left">
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
 * that scrolls, zooms and goes full screen, with a mini map for large ones.
 */
export function FlowCanvas() {
    const { flow } = useEditor();
    const { root, unreachable } = useMemo(() => buildTree(flow), [flow]);
    const frame = useRef<HTMLDivElement>(null);
    const scroller = useRef<HTMLDivElement>(null);
    const content = useRef<HTMLDivElement>(null);
    const [zoom, setZoom] = useState(1);
    const [tick, setTick] = useState(0);
    const bump = useCallback(() => requestAnimationFrame(() => setTick((t) => t + 1)), []);

    useEffect(() => {
        bump();
    }, [flow, zoom, bump]);

    // Open with the whole conversation in view: shrink a wide tree to fit
    // (never below 80%, where the text stops being comfortable to read;
    // a wider tree scrolls sideways, centred on Start)...
    const fitted = useRef(false);
    useLayoutEffect(() => {
        if (fitted.current) return;
        const s = scroller.current;
        const c = content.current;
        if (!s || !c) return;
        fitted.current = true;
        const natural = c.getBoundingClientRect().width;
        if (natural > s.clientWidth && s.clientWidth > 0) setZoom(Math.max(0.8, Math.floor(((s.clientWidth - 32) / natural) * 20) / 20));
    }, []);

    // ...and keep it centred on Start whenever the zoom changes.
    useEffect(() => {
        const s = scroller.current;
        if (s) s.scrollLeft = (s.scrollWidth - s.clientWidth) / 2;
    }, [zoom]);

    useEffect(() => {
        const s = scroller.current;
        if (!s) return;
        const observer = new ResizeObserver(bump);
        observer.observe(s);
        return () => observer.disconnect();
    }, [bump]);

    const zoomBy = (delta: number) => setZoom((z) => Math.min(1.5, Math.max(0.4, Math.round((z + delta) * 10) / 10)));
    const fit = () => {
        const s = scroller.current;
        const c = content.current;
        if (!s || !c) return;
        const natural = c.getBoundingClientRect().width / zoom;
        setZoom(Math.min(1, Math.max(0.4, Math.floor(((s.clientWidth - 32) / natural) * 20) / 20)));
    };
    const fullscreen = () => {
        if (document.fullscreenElement) void document.exitFullscreen();
        else void frame.current?.requestFullscreen?.();
    };

    const startVisual = blockVisual('start');

    return (
        <div
            ref={frame}
            className="bg-muted/30 relative h-[calc(100vh-16rem)] min-h-[560px] overflow-hidden rounded-xl border [&:fullscreen]:h-screen [&:fullscreen]:rounded-none"
        >
            <div
                ref={scroller}
                onScroll={bump}
                onWheel={(e) => {
                    if (!e.ctrlKey) return;
                    e.preventDefault();
                    zoomBy(e.deltaY < 0 ? 0.1 : -0.1);
                }}
                className="h-full w-full overflow-auto"
                style={{
                    backgroundImage: 'radial-gradient(circle, color-mix(in oklab, var(--foreground) 16%, transparent) 1px, transparent 1.3px)',
                    backgroundSize: '18px 18px',
                }}
            >
                <div ref={content} style={{ zoom }} className="mx-auto flex w-max flex-col items-center px-12 pt-8 pb-28">
                    <div
                        data-flow-node={startVisual.tone.dot}
                        className={`flex w-52 items-center gap-2.5 rounded-xl border p-3 shadow-sm ${startVisual.tone.card}`}
                    >
                        <StepIcon visual={startVisual} />
                        <span>
                            <span className="text-foreground block text-[13px] font-semibold">Start</span>
                            <span className="text-muted-foreground block text-xs">Conversation starts here</span>
                        </span>
                    </div>
                    <BranchColumn branch={root} />
                    <Unreachable ids={unreachable} />
                </div>
            </div>

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
                <Button variant="ghost" size="icon" className="size-8" onClick={fullscreen} aria-label="Full screen" title="Full screen">
                    <Expand className="size-4" aria-hidden />
                </Button>
            </div>

            <MiniMap scroller={scroller} content={content} tick={tick} />
        </div>
    );
}
