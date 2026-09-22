import { BLOCK_GROUPS, BLOCKS } from '@/lib/flow-blocks';
import type { Dragging } from './flow-canvas';
import { blockVisual, StepIcon } from './step-visuals';

/**
 * The blocks an owner builds a conversation from. Drag one into any gap in the
 * tree - or click it to add it straight after the selected step, which is also
 * the keyboard and touch-screen way in.
 */
export function BlockLibrary({
    onQuickAdd,
    setDragging,
    quickAddHint,
}: {
    onQuickAdd: (key: string) => void;
    setDragging: (dragging: Dragging) => void;
    quickAddHint: string;
}) {
    return (
        <div className="space-y-4">
            <p className="text-muted-foreground text-xs">{quickAddHint}</p>
            {BLOCK_GROUPS.map((group) => (
                <section key={group}>
                    <h3 className="text-muted-foreground mb-1.5 text-[11px] font-semibold tracking-wide uppercase">{group}</h3>
                    <div className="grid gap-1.5">
                        {BLOCKS.filter((b) => b.group === group).map((block) => (
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
                                title={block.hint}
                                className="border-border bg-card hover:border-brand/60 focus-visible:ring-brand flex w-full cursor-grab items-center gap-2.5 rounded-lg border px-2.5 py-2 text-left transition-colors focus-visible:ring-2 focus-visible:outline-none active:cursor-grabbing"
                            >
                                <StepIcon visual={blockVisual(block.key)} className="size-7" />
                                <span className="min-w-0">
                                    <span className="text-foreground block truncate text-sm font-medium">{block.label}</span>
                                    <span className="text-muted-foreground block truncate text-xs">{block.hint}</span>
                                </span>
                            </button>
                        ))}
                    </div>
                </section>
            ))}
        </div>
    );
}
