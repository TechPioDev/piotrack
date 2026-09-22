import { BlockLibrary } from '@/components/chat-flow/block-library';
import { type Dragging, FlowCanvas, FlowEditorContext, type Issue } from '@/components/chat-flow/flow-canvas';
import { StepSettings } from '@/components/chat-flow/step-settings';
import { type Template, TemplateGallery } from '@/components/chat-flow/template-gallery';
import { postJson, TestDialog } from '@/components/chat-flow/test-dialog';
import { FormErrors } from '@/components/form-errors';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogTitle } from '@/components/ui/dialog';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import AppLayout from '@/layouts/app-layout';
import { blockByKey, CONTACT_FIELDS } from '@/lib/flow-blocks';
import { afterSlot, buildTree, canInsert, type Flow, type FlowNode, insertStep, locate, moveStep, removeStep, type Slot } from '@/lib/flow-tree';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Redo2, Undo2, XCircle } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

type Validation = { valid: boolean; errors: { node: string | null; message: string }[]; warnings: { node: string | null; message: string }[] };
type Assignee = { id: number; name: string };
type History = { flow: Flow; past: Flow[]; future: Flow[] };

function useWide(): boolean {
    const query = '(min-width: 1280px)';
    const [wide, setWide] = useState(() => typeof window !== 'undefined' && window.matchMedia(query).matches);
    useEffect(() => {
        const media = window.matchMedia(query);
        const update = () => setWide(media.matches);
        media.addEventListener('change', update);
        return () => media.removeEventListener('change', update);
    }, []);
    return wide;
}

/**
 * The conversation builder: blocks on the left, the conversation as a tree in
 * the middle, the selected step's settings on the right. Blocks are dragged
 * into the gap where they belong - or added with the "+" in any gap - and the
 * builder does the wiring. Every change can be undone.
 */
export default function FlowBuilder({
    widget,
    flow: initialFlow,
    validation: initialValidation,
    templates,
    assignees,
}: {
    widget: { id: number; name: string; status: string };
    flow: Flow;
    validation: Validation;
    templates: Template[];
    assignees: Assignee[];
}) {
    const seed = (f: Flow): Flow => ({ start: f.start ?? null, nodes: f.nodes ?? {} });
    const [history, setHistory] = useState<History>(() => ({ flow: seed(initialFlow), past: [], future: [] }));
    const flow = history.flow;
    const [selected, setSelected] = useState<string | null>(null);
    const [dragging, setDragging] = useState<Dragging>(null);
    const [validation, setValidation] = useState<Validation>(initialValidation);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [refused, setRefused] = useState<Record<string, string>>({});
    const [notice, setNotice] = useState<string | null>(null);
    const [pendingDelete, setPendingDelete] = useState<{ id: string; next: Flow; count: number } | null>(null);
    const lastMerge = useRef<{ key: string; at: number } | null>(null);
    const wide = useWide();
    const live = widget.status === 'active';

    // A save sends the flow back down as a prop; take it as the new baseline.
    const serverFlow = useRef(JSON.stringify(initialFlow));
    useEffect(() => {
        const incoming = JSON.stringify(initialFlow);
        if (incoming === serverFlow.current) return;
        serverFlow.current = incoming;
        setHistory({ flow: seed(initialFlow), past: [], future: [] });
        setValidation(initialValidation);
        setDirty(false);
    }, [initialFlow, initialValidation]);

    // Check the draft as it changes, once typing pauses.
    useEffect(() => {
        if (!dirty) return;
        const timer = window.setTimeout(() => {
            postJson<Validation>(route('chat.flow.validate', widget.id), { flow })
                .then(setValidation)
                .catch(() => undefined);
        }, 350);
        return () => window.clearTimeout(timer);
    }, [flow, dirty, widget.id]);

    /**
     * Every change goes through here, so it can be undone. Typing in one field
     * is one change, not one per keystroke: edits with the same key within a
     * moment of each other are merged.
     */
    const apply = useCallback((next: Flow, mergeKey?: string) => {
        const now = Date.now();
        const merge = mergeKey !== undefined && lastMerge.current?.key === mergeKey && now - lastMerge.current.at < 1200;
        lastMerge.current = mergeKey ? { key: mergeKey, at: now } : null;
        setHistory((h) => (next === h.flow ? h : { flow: next, past: merge ? h.past : [...h.past.slice(-49), h.flow], future: [] }));
        setDirty(true);
    }, []);

    const undo = useCallback(() => {
        lastMerge.current = null;
        setHistory((h) => (h.past.length === 0 ? h : { flow: h.past[h.past.length - 1], past: h.past.slice(0, -1), future: [h.flow, ...h.future] }));
        setDirty(true);
    }, []);
    const redo = useCallback(() => {
        lastMerge.current = null;
        setHistory((h) => (h.future.length === 0 ? h : { flow: h.future[0], past: [...h.past, h.flow], future: h.future.slice(1) }));
        setDirty(true);
    }, []);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            const target = e.target as HTMLElement | null;
            if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable)) return;
            if (!(e.ctrlKey || e.metaKey)) return;
            if (e.key.toLowerCase() === 'z' && !e.shiftKey) {
                e.preventDefault();
                undo();
            } else if ((e.key.toLowerCase() === 'z' && e.shiftKey) || e.key.toLowerCase() === 'y') {
                e.preventDefault();
                redo();
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [undo, redo]);

    // A selected step that no longer exists (undone, deleted) is deselected.
    useEffect(() => {
        if (selected && !flow.nodes[selected]) setSelected(null);
    }, [flow, selected]);

    const tree = useMemo(() => buildTree(flow), [flow]);

    const patchNode = (id: string, patch: Partial<FlowNode>, mergeKey?: string) =>
        apply({ ...flow, nodes: { ...flow.nodes, [id]: { ...flow.nodes[id], ...patch } } }, mergeKey);

    const insertBlock = (slot: Slot, key: string) => {
        const block = blockByKey(key);
        if (!block) return;
        const made = block.make();
        if (!canInsert(flow, slot, made.type)) return;
        const { flow: next, id } = insertStep(flow, slot, made);
        apply(next);
        setSelected(id);
        setNotice(null);
    };

    /** Click a block: add it after the selected step, or at the end of the conversation. */
    const quickAdd = (key: string) => {
        const block = blockByKey(key);
        if (!block) return;
        let slot: Slot | null = selected ? afterSlot(tree.root, selected) : null;
        if (!selected) {
            const steps = tree.root.steps;
            const last = steps[steps.length - 1];
            slot = tree.root.end.kind === 'open' ? tree.root.endSlot : last?.node.type === 'end' ? last.via : null;
        }
        if (!slot) {
            setNotice(
                selected
                    ? 'Nothing can follow the selected step. Drag the block to where it belongs instead.'
                    : 'Drag the block to where it belongs in the conversation.',
            );
            return;
        }
        if (!canInsert(flow, slot, block.make().type)) {
            setNotice('A Finish step goes where nothing follows. Drag it to the end of a path.');
            return;
        }
        insertBlock(slot, key);
    };

    const remove = (id: string) => {
        const { flow: next, removed } = removeStep(flow, id);
        if (removed.length > 1) {
            setPendingDelete({ id, next, count: removed.length - 1 });
            return;
        }
        apply(next);
        setSelected(null);
    };

    const move = (direction: 'up' | 'down') => {
        if (!selected) return;
        const place = locate(tree.root, selected);
        if (!place) return;
        const { branch, index } = place;
        if (direction === 'up' && index > 0) {
            apply(moveStep(flow, selected, branch.steps[index - 1].via));
        } else if (direction === 'down' && index < branch.steps.length - 1) {
            const slot = afterSlot(tree.root, branch.steps[index + 1].id);
            if (slot) apply(moveStep(flow, selected, slot));
        }
    };

    const issues = useMemo(() => {
        const map: Record<string, Issue[]> = {};
        for (const e of validation.errors) if (e.node) (map[e.node] ??= []).push({ level: 'error', message: e.message });
        for (const w of validation.warnings) if (w.node) (map[w.node] ??= []).push({ level: 'warning', message: w.message });
        return map;
    }, [validation]);

    const save = (publish: boolean) => {
        setSaving(true);
        router.put(
            route('chat.flow.update', widget.id),
            { flow, publish },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDirty(false);
                    setRefused({});
                },
                onError: (errors) => setRefused(errors),
                onFinish: () => setSaving(false),
            },
        );
    };

    const editor = {
        flow,
        selected,
        select: setSelected,
        dragging,
        setDragging,
        insertBlock,
        moveTo: (id: string, slot: Slot) => {
            apply(moveStep(flow, id, slot));
            setSelected(id);
        },
        toggleRequired: (id: string) => patchNode(id, { optional: !flow.nodes[id]?.optional }),
        remove,
        issues,
    };

    const settings = selected ? (
        <StepSettings
            flow={flow}
            id={selected}
            root={tree.root}
            assignees={assignees}
            onPatch={(patch, mergeKey) => patchNode(selected, patch, mergeKey)}
            onApply={apply}
            onDelete={() => remove(selected)}
            onMove={move}
            onClose={() => setSelected(null)}
        />
    ) : null;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Website Chat', href: '/chat' },
        { title: 'Widgets', href: '/chat/widgets' },
        { title: widget.name, href: `/chat/widgets/${widget.id}/flow` },
    ];

    const stepCount = Object.keys(flow.nodes).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${widget.name} — conversation`} />
            <div className="space-y-4 p-4">
                <PageHeader
                    title="Conversation builder"
                    description={
                        live
                            ? 'Drag blocks into the conversation, or use + between steps. This chat is live, so published changes reach visitors straight away.'
                            : 'Drag blocks into the conversation, or use + between steps. Nothing reaches your website until you publish.'
                    }
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="flex">
                                <Button
                                    variant="outline"
                                    size="icon"
                                    onClick={undo}
                                    disabled={history.past.length === 0}
                                    aria-label="Undo"
                                    title="Undo (Ctrl+Z)"
                                    className="rounded-r-none"
                                >
                                    <Undo2 className="size-4" aria-hidden />
                                </Button>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    onClick={redo}
                                    disabled={history.future.length === 0}
                                    aria-label="Redo"
                                    title="Redo (Ctrl+Shift+Z)"
                                    className="-ml-px rounded-l-none"
                                >
                                    <Redo2 className="size-4" aria-hidden />
                                </Button>
                            </div>
                            <TemplateGallery
                                templates={templates}
                                hasSteps={stepCount > 0}
                                onUse={(t) => {
                                    apply(JSON.parse(JSON.stringify(t.flow)) as Flow);
                                    setSelected(null);
                                    setNotice(`Loaded “${t.name}”. Change anything you like, then publish.`);
                                }}
                            />
                            <TestDialog widgetId={widget.id} flow={flow} />
                            {!live && (
                                <Button variant="outline" onClick={() => save(false)} disabled={saving}>
                                    Save draft
                                </Button>
                            )}
                            <Button onClick={() => save(true)} disabled={saving || !validation.valid}>
                                {live ? 'Publish changes' : 'Publish'}
                            </Button>
                        </div>
                    }
                />

                <FormErrors errors={refused} />
                <StatusLine validation={validation} dirty={dirty} onSelect={setSelected} />
                {notice && (
                    <p role="status" className="bg-brand-soft text-brand-strong rounded-lg px-3 py-2 text-sm">
                        {notice}
                    </p>
                )}

                <FlowEditorContext.Provider value={editor}>
                    <div className="grid items-start gap-4 xl:grid-cols-[250px_minmax(0,1fr)_360px]">
                        <aside className="bg-card border-border rounded-xl border p-3 xl:sticky xl:top-4 xl:max-h-[calc(100vh-2rem)] xl:overflow-y-auto">
                            <details open={wide} className="group">
                                <summary className="text-foreground cursor-pointer text-sm font-semibold xl:pointer-events-none xl:list-none">
                                    Blocks
                                </summary>
                                <div className="mt-3">
                                    <BlockLibrary
                                        onQuickAdd={quickAdd}
                                        setDragging={setDragging}
                                        quickAddHint={
                                            selected
                                                ? 'Drag a block into the conversation, or click it to add it after the selected step.'
                                                : 'Drag a block into the conversation, or click it to add it at the end.'
                                        }
                                    />
                                </div>
                            </details>
                        </aside>

                        <main className="bg-muted/20 border-border min-h-[60vh] rounded-xl border p-4 sm:p-6">
                            <FlowCanvas />
                        </main>

                        {wide ? (
                            <aside className="bg-card border-border sticky top-4 max-h-[calc(100vh-2rem)] overflow-y-auto rounded-xl border p-4">
                                {settings ?? <GettingStarted flow={flow} />}
                            </aside>
                        ) : (
                            <Sheet open={selected !== null} onOpenChange={(open) => !open && setSelected(null)}>
                                <SheetContent className="w-full overflow-y-auto p-4 sm:max-w-md">
                                    <SheetTitle className="sr-only">Step settings</SheetTitle>
                                    {settings}
                                </SheetContent>
                            </Sheet>
                        )}
                    </div>
                </FlowEditorContext.Provider>
            </div>

            <Dialog open={pendingDelete !== null} onOpenChange={(open) => !open && setPendingDelete(null)}>
                <DialogContent>
                    <DialogTitle>Delete this question and its paths?</DialogTitle>
                    <DialogDescription>
                        Its answers lead to {pendingDelete?.count} {pendingDelete?.count === 1 ? 'step' : 'steps'} that nothing else leads to, so{' '}
                        {pendingDelete?.count === 1 ? 'it goes' : 'they go'} too. You can undo this.
                    </DialogDescription>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setPendingDelete(null)}>
                            Keep it
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (pendingDelete) apply(pendingDelete.next);
                                setPendingDelete(null);
                                setSelected(null);
                            }}
                        >
                            Delete {1 + (pendingDelete?.count ?? 0)} steps
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

/** One line on whether the conversation can go live, with the problems listed underneath. */
function StatusLine({ validation, dirty, onSelect }: { validation: Validation; dirty: boolean; onSelect: (id: string) => void }) {
    if (validation.valid && validation.warnings.length === 0) {
        return (
            <div className="flex items-center gap-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm">
                <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400" aria-hidden />
                <span className="text-foreground">Ready to publish.</span>
                {dirty && <span className="text-muted-foreground ml-auto text-xs">Unsaved changes</span>}
            </div>
        );
    }

    const problems = [
        ...validation.errors.map((e) => ({ ...e, level: 'error' as const })),
        ...validation.warnings.map((w) => ({ ...w, level: 'warning' as const })),
    ];
    const errorCount = validation.errors.length;

    return (
        <details
            className={`rounded-lg border px-4 py-2 ${errorCount > 0 ? 'border-red-500/30 bg-red-500/10' : 'border-amber-500/30 bg-amber-500/10'}`}
        >
            <summary className="flex cursor-pointer items-center gap-2 text-sm">
                {errorCount > 0 ? (
                    <XCircle className="size-4 text-red-600 dark:text-red-400" aria-hidden />
                ) : (
                    <AlertTriangle className="size-4 text-amber-600 dark:text-amber-400" aria-hidden />
                )}
                <span className="text-foreground font-medium">
                    {errorCount > 0
                        ? `${errorCount} ${errorCount === 1 ? 'thing needs' : 'things need'} fixing before this can go live`
                        : `${validation.warnings.length} ${validation.warnings.length === 1 ? 'thing is' : 'things are'} worth a look`}
                </span>
                <span className="text-muted-foreground text-xs">— marked on the steps below</span>
                {dirty && <span className="text-muted-foreground ml-auto text-xs">Unsaved changes</span>}
            </summary>
            <ul className="mt-2 space-y-1 pb-1 text-sm">
                {problems.map((p, i) => (
                    <li key={i} className="flex items-start gap-1.5">
                        <span className={p.level === 'error' ? 'text-red-600 dark:text-red-400' : 'text-amber-700 dark:text-amber-400'} aria-hidden>
                            •
                        </span>
                        {p.node ? (
                            <button
                                type="button"
                                className="text-left underline underline-offset-2"
                                onClick={() => {
                                    onSelect(p.node as string);
                                    document.getElementById(`flow-step-${p.node}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                }}
                            >
                                {p.message}
                            </button>
                        ) : (
                            p.message
                        )}
                    </li>
                ))}
            </ul>
        </details>
    );
}

/** The right-hand panel before a step is selected: how to build, and what the chat collects. */
function GettingStarted({ flow }: { flow: Flow }) {
    const collected = Object.values(flow.nodes)
        .filter((n) => n.type === 'input' && n.field && CONTACT_FIELDS[n.field])
        .map((n) => ({ label: CONTACT_FIELDS[n.field as string], required: !n.optional }));

    return (
        <div className="space-y-4 text-sm">
            <div>
                <h2 className="text-foreground font-semibold">How to build</h2>
                <ol className="text-muted-foreground mt-2 list-decimal space-y-1.5 pl-4">
                    <li>Drag a block from the left into the conversation, into the gap where it belongs.</li>
                    <li>Or click the + between two steps and pick a block.</li>
                    <li>Click a step to change what it says, its answers, or where an answer leads.</li>
                    <li>Switch contact details between Required and Optional right on their cards.</li>
                    <li>Test it, then publish.</li>
                </ol>
            </div>
            <div>
                <h2 className="text-foreground font-semibold">Details this chat collects</h2>
                {collected.length === 0 ? (
                    <p className="text-muted-foreground mt-1">None yet. Drag in Email and First name so every chat can become a lead.</p>
                ) : (
                    <ul className="mt-2 space-y-1">
                        {collected.map((c, i) => (
                            <li key={i} className="flex items-center justify-between gap-2">
                                <span className="text-foreground">{c.label}</span>
                                <span
                                    className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${c.required ? 'bg-brand text-brand-foreground' : 'border-border text-muted-foreground border'}`}
                                >
                                    {c.required ? 'Required' : 'Optional'}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}
