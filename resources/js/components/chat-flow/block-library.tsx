import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { type Block, BLOCK_GROUPS, BLOCKS } from '@/lib/flow-blocks';
import { PanelLeftClose, PanelLeftOpen, Search } from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import type { Dragging } from './flow-canvas';
import { blockVisual, StepIcon } from './step-visuals';
import type { Template } from './template-gallery';

type Props = {
    /** The step being placed by picking it, if any. */
    picking: string | null;
    /** Pick a step to place, or put it down again. */
    onPick: (key: string) => void;
    setDragging: (dragging: Dragging) => void;
    templates: Template[];
    onOpenTemplate: (key: string) => void;
    /** Hide the panel (or, folded, open it again). */
    onToggle?: () => void;
};

/** Drag a block, or click it and then click where it goes. */
function blockHandlers(block: Block, { onPick, setDragging }: Pick<Props, 'onPick' | 'setDragging'>) {
    return {
        draggable: true,
        onDragStart: (e: React.DragEvent) => {
            e.dataTransfer.setData('text/plain', block.key);
            e.dataTransfer.effectAllowed = 'copy';
            setDragging({ kind: 'block', key: block.key });
        },
        onDragEnd: () => setDragging(null),
        onClick: () => onPick(block.key),
    };
}

/**
 * The left panel: every step, grouped and searchable - drag one onto the
 * canvas, or click it and then click the place it goes (the keyboard and
 * touch route); and the templates, by type of business.
 */
export function StepLibrary({ picking, onPick, setDragging, templates, onOpenTemplate, onToggle }: Props) {
    const [tab, setTab] = useState<'steps' | 'templates'>('steps');
    const [query, setQuery] = useState('');
    const q = query.trim().toLowerCase();
    const shown = useMemo(() => BLOCKS.filter((b) => !q || `${b.label} ${b.hint} ${b.group}`.toLowerCase().includes(q)), [q]);
    const categories = useMemo(() => [...new Set(templates.map((t) => t.category))], [templates]);

    return (
        <div className="flex h-full min-h-0 flex-col">
            <div className="flex items-center gap-1 border-b p-1.5">
                <div role="tablist" aria-label="Add to the conversation" className="grid flex-1 grid-cols-2 gap-1">
                    {(['steps', 'templates'] as const).map((t) => (
                        <button
                            key={t}
                            type="button"
                            role="tab"
                            aria-selected={tab === t}
                            onClick={() => setTab(t)}
                            className={`rounded-md py-1.5 text-sm font-medium capitalize transition-colors ${
                                tab === t
                                    ? 'bg-indigo-50 text-indigo-700 shadow-[inset_0_-2px_0_0_var(--color-indigo-500)] dark:bg-indigo-500/15 dark:text-indigo-300'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {t}
                        </button>
                    ))}
                </div>
                {onToggle && (
                    <Button
                        size="icon"
                        variant="ghost"
                        className="size-8 shrink-0"
                        onClick={onToggle}
                        aria-label="Hide steps panel"
                        title="Hide this panel"
                    >
                        <PanelLeftClose className="size-4" aria-hidden />
                    </Button>
                )}
            </div>

            {tab === 'steps' ? (
                <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-3" role="tabpanel">
                    <p className="text-muted-foreground text-xs">Drag a step onto the canvas, or click it and then click where it goes.</p>
                    <div className="relative">
                        <Search className="text-muted-foreground pointer-events-none absolute top-2.5 left-2.5 size-4" aria-hidden />
                        <Input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Search steps..."
                            aria-label="Search steps"
                            className="h-9 pl-8"
                        />
                    </div>
                    {BLOCK_GROUPS.map((group) => {
                        const blocks = shown.filter((b) => b.group === group);
                        if (blocks.length === 0) return null;
                        return (
                            <section key={group}>
                                <h3 className="text-foreground mb-1.5 text-[13px] font-semibold">{group}</h3>
                                <div className="grid gap-1.5">
                                    {blocks.map((block) => (
                                        <button
                                            key={block.key}
                                            type="button"
                                            {...blockHandlers(block, { onPick, setDragging })}
                                            aria-pressed={picking === block.key}
                                            title={`${block.hint}. Drag onto the canvas, or click and then click where it goes.`}
                                            className={`flex w-full cursor-grab items-center gap-2.5 rounded-lg border px-2 py-1.5 text-left transition-colors focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none active:cursor-grabbing ${
                                                picking === block.key
                                                    ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500 dark:bg-indigo-500/15'
                                                    : 'bg-card hover:border-indigo-300 dark:hover:border-indigo-500/50'
                                            }`}
                                        >
                                            <StepIcon visual={blockVisual(block.key)} className="size-7" />
                                            <span className="text-foreground truncate text-[13px]">{block.label}</span>
                                        </button>
                                    ))}
                                </div>
                            </section>
                        );
                    })}
                    {shown.length === 0 && <p className="text-muted-foreground text-sm">No step matches “{query}”.</p>}
                </div>
            ) : (
                <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-3" role="tabpanel">
                    <p className="text-muted-foreground text-xs">Ready-made conversations by type of business. Pick one to preview it.</p>
                    {categories.map((category) => (
                        <section key={category}>
                            <h3 className="text-foreground mb-1.5 text-[13px] font-semibold">{category}</h3>
                            <div className="grid gap-1.5">
                                {templates
                                    .filter((t) => t.category === category)
                                    .map((t) => (
                                        <button
                                            key={t.key}
                                            type="button"
                                            onClick={() => onOpenTemplate(t.key)}
                                            className="bg-card block w-full rounded-lg border px-2.5 py-2 text-left transition-colors hover:border-indigo-300 dark:hover:border-indigo-500/50"
                                        >
                                            <span className="text-foreground block text-[13px] font-medium">{t.name}</span>
                                            <span className="text-muted-foreground line-clamp-2 block text-[11px]">{t.description}</span>
                                        </button>
                                    ))}
                            </div>
                        </section>
                    ))}
                </div>
            )}
        </div>
    );
}

/**
 * The steps panel folded to a narrow strip: every step as an icon, still
 * draggable and pickable, so the canvas gets the room without losing them.
 */
export function StepRail({ picking, onPick, setDragging, onToggle }: Pick<Props, 'picking' | 'onPick' | 'setDragging' | 'onToggle'>) {
    return (
        <div className="flex h-full min-h-0 flex-col items-center">
            <div className="w-full border-b p-1.5">
                <Button
                    size="icon"
                    variant="ghost"
                    className="size-9 w-full"
                    onClick={onToggle}
                    aria-label="Show steps panel"
                    title="Show the steps panel"
                >
                    <PanelLeftOpen className="size-4" aria-hidden />
                </Button>
            </div>
            <div className="flex min-h-0 w-full flex-1 flex-col items-center gap-1 overflow-y-auto py-2">
                {BLOCK_GROUPS.map((group, i) => (
                    <Fragment key={group}>
                        {i > 0 && <span className="bg-border my-1 h-px w-6 shrink-0" aria-hidden />}
                        {BLOCKS.filter((b) => b.group === group).map((block) => (
                            <button
                                key={block.key}
                                type="button"
                                {...blockHandlers(block, { onPick, setDragging })}
                                aria-label={block.label}
                                aria-pressed={picking === block.key}
                                title={`${block.label}: ${block.hint}`}
                                className={`shrink-0 cursor-grab rounded-lg p-0.5 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none active:cursor-grabbing ${
                                    picking === block.key ? 'ring-2 ring-indigo-500' : 'hover:ring-1 hover:ring-indigo-300'
                                }`}
                            >
                                <StepIcon visual={blockVisual(block.key)} className="size-8" />
                            </button>
                        ))}
                    </Fragment>
                ))}
            </div>
        </div>
    );
}
