import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { stepKind } from '@/lib/flow-blocks';
import { buildTree, describe, type Flow, type TreeBranch } from '@/lib/flow-tree';
import { LayoutTemplate } from 'lucide-react';
import { useMemo, useState } from 'react';
import { StepIcon, stepVisual } from './step-visuals';

export type Template = { key: string; name: string; description: string; category: string; steps: number; flow: Flow };

type Line = { depth: number; kind: 'step' | 'branch'; text: string; id?: string };

/** The template as the owner will see it in the tree, flattened for a preview. */
function outline(branch: TreeBranch, flow: Flow, depth = 0): Line[] {
    const lines: Line[] = [];
    for (const step of branch.steps) {
        lines.push({ depth, kind: 'step', text: describe(step.node), id: step.id });
        for (const b of step.branches) {
            const label =
                b.slot.kind === 'answers'
                    ? (step.node.options ?? [])
                          .filter((o) => (b.slot.kind === 'answers' ? b.slot.answers : []).includes(o.id))
                          .map((o) => o.label)
                          .join(' · ')
                    : (b.label ?? '');
            lines.push({ depth: depth + 1, kind: 'branch', text: `If they choose ${label}` });
            lines.push(...outline(b, flow, depth + 2));
        }
    }
    return lines;
}

/**
 * Ready-made conversations by type of business. Choosing one loads it into the
 * editor - nothing reaches the website until it is published, and Undo brings
 * back what was there.
 */
export function TemplateGallery({ templates, onUse, hasSteps }: { templates: Template[]; onUse: (template: Template) => void; hasSteps: boolean }) {
    const [open, setOpen] = useState(false);
    const categories = useMemo(() => [...new Set(templates.map((t) => t.category))], [templates]);
    const [category, setCategory] = useState<string>('All');
    const shown = category === 'All' ? templates : templates.filter((t) => t.category === category);
    const [chosen, setChosen] = useState<string>(templates[0]?.key ?? '');
    const template = templates.find((t) => t.key === chosen) ?? shown[0];
    const lines = useMemo(() => (template ? outline(buildTree(template.flow).root, template.flow) : []), [template]);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <LayoutTemplate className="size-3.5" aria-hidden /> Templates
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-hidden sm:max-w-5xl">
                <DialogTitle>Start from a template</DialogTitle>
                <DialogDescription>
                    Ready-made conversations for your type of business. Pick one, change anything you like, then publish.
                </DialogDescription>

                <div className="flex flex-wrap gap-1.5" role="tablist" aria-label="Type of business">
                    {['All', ...categories].map((c) => (
                        <button
                            key={c}
                            type="button"
                            role="tab"
                            aria-selected={category === c}
                            onClick={() => setCategory(c)}
                            className={`rounded-full border px-3 py-1 text-xs font-medium transition-colors ${
                                category === c ? 'border-brand bg-brand text-brand-foreground' : 'border-border hover:border-brand/50'
                            }`}
                        >
                            {c}
                        </button>
                    ))}
                </div>

                <div className="grid min-h-0 gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
                    <ul className="max-h-[55vh] space-y-2 overflow-y-auto pr-1">
                        {shown.map((t) => (
                            <li key={t.key}>
                                <button
                                    type="button"
                                    onClick={() => setChosen(t.key)}
                                    aria-pressed={template?.key === t.key}
                                    className={`block w-full rounded-lg border p-3 text-left transition-colors ${
                                        template?.key === t.key ? 'border-brand bg-brand-soft/50' : 'border-border hover:border-brand/50'
                                    }`}
                                >
                                    <span className="text-muted-foreground block text-[11px] font-semibold tracking-wide uppercase">
                                        {t.category}
                                    </span>
                                    <span className="text-foreground block text-sm font-semibold">{t.name}</span>
                                    <span className="text-muted-foreground mt-0.5 block text-xs">{t.description}</span>
                                    <span className="text-muted-foreground mt-1 block text-[11px]">{t.steps} steps</span>
                                </button>
                            </li>
                        ))}
                    </ul>

                    {template && (
                        <div className="border-border flex min-h-0 flex-col rounded-lg border">
                            <div className="border-border border-b px-3 py-2">
                                <h3 className="text-foreground text-sm font-semibold">{template.name}</h3>
                                <p className="text-muted-foreground text-xs">What your visitors will go through</p>
                            </div>
                            <ol className="max-h-[42vh] min-h-0 flex-1 space-y-1 overflow-y-auto p-3">
                                {lines.map((line, i) => (
                                    <li key={i} style={{ paddingLeft: `${line.depth * 14}px` }} className="flex items-start gap-2 text-sm">
                                        {line.kind === 'step' && line.id ? (
                                            <>
                                                <StepIcon visual={stepVisual(template.flow.nodes[line.id])} className="size-5" />
                                                <span className="min-w-0">
                                                    <span className="text-muted-foreground mr-1 text-[11px] uppercase">
                                                        {stepKind(template.flow.nodes[line.id])}
                                                    </span>
                                                    {line.text}
                                                </span>
                                            </>
                                        ) : (
                                            <span className="text-brand-strong text-xs font-medium">{line.text}</span>
                                        )}
                                    </li>
                                ))}
                            </ol>
                            <div className="border-border flex flex-wrap items-center gap-2 border-t p-3">
                                <p className="text-muted-foreground mr-auto text-xs">
                                    {hasSteps ? 'Replaces what is in the editor. Undo brings it back.' : 'Loads into the editor.'} Nothing changes on
                                    your website until you publish.
                                </p>
                                <Button
                                    onClick={() => {
                                        onUse(template);
                                        setOpen(false);
                                    }}
                                >
                                    Use this template
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
