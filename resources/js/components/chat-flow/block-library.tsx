import { Input } from '@/components/ui/input';
import { BLOCK_GROUPS, BLOCKS } from '@/lib/flow-blocks';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { Dragging } from './flow-canvas';
import { blockVisual, StepIcon } from './step-visuals';
import type { Template } from './template-gallery';

/**
 * The left panel: every step, grouped and searchable, to drag onto the canvas
 * (or click, to add after the selected step - also the keyboard and touch
 * route); and the templates, by type of business.
 */
export function StepLibrary({
    onQuickAdd,
    setDragging,
    templates,
    onOpenTemplate,
}: {
    onQuickAdd: (key: string) => void;
    setDragging: (dragging: Dragging) => void;
    templates: Template[];
    onOpenTemplate: (key: string) => void;
}) {
    const [tab, setTab] = useState<'steps' | 'templates'>('steps');
    const [query, setQuery] = useState('');
    const q = query.trim().toLowerCase();
    const shown = useMemo(() => BLOCKS.filter((b) => !q || `${b.label} ${b.hint} ${b.group}`.toLowerCase().includes(q)), [q]);
    const categories = useMemo(() => [...new Set(templates.map((t) => t.category))], [templates]);

    return (
        <div className="flex h-full min-h-0 flex-col">
            <div role="tablist" aria-label="Add to the conversation" className="grid grid-cols-2 gap-1 border-b p-1.5">
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

            {tab === 'steps' ? (
                <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-3" role="tabpanel">
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
                                            draggable
                                            onDragStart={(e) => {
                                                e.dataTransfer.setData('text/plain', block.key);
                                                e.dataTransfer.effectAllowed = 'copy';
                                                setDragging({ kind: 'block', key: block.key });
                                            }}
                                            onDragEnd={() => setDragging(null)}
                                            onClick={() => onQuickAdd(block.key)}
                                            title={`${block.hint}. Drag onto the canvas, or click to add it.`}
                                            className="bg-card flex w-full cursor-grab items-center gap-2.5 rounded-lg border px-2 py-1.5 text-left transition-colors hover:border-indigo-300 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none active:cursor-grabbing dark:hover:border-indigo-500/50"
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
