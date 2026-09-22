import { FormErrors } from '@/components/form-errors';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogTitle } from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import { blockByKey, CONTACT_FIELDS } from '@/lib/flow-blocks';
import {
    addOption,
    afterSlot,
    buildTree,
    canInsert,
    duplicateStep,
    type Flow,
    type FlowNode,
    insertStep,
    isMovable,
    locate,
    moveStep,
    removeOption,
    removeStep,
    type Slot,
    withQuickReplies,
} from '@/lib/flow-tree';
import { router } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    ChevronDown,
    LayoutTemplate,
    Maximize2,
    MessagesSquare,
    Minimize2,
    MoreVertical,
    PanelLeftClose,
    PanelLeftOpen,
    PanelRightClose,
    PanelRightOpen,
    Play,
    PlayCircle,
    Redo2,
    Save,
    Send,
    Settings2,
    Undo2,
    XCircle,
} from 'lucide-react';
import { type ReactNode, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { StepLibrary, StepRail } from './block-library';
import { type Dragging, FlowCanvas, FlowEditorContext, type Issue } from './flow-canvas';
import { StepSettings } from './step-settings';
import { type Template, TemplateGallery } from './template-gallery';
import { postJson, TestDialog } from './test-dialog';

export type Validation = { valid: boolean; errors: { node: string | null; message: string }[]; warnings: { node: string | null; message: string }[] };
type Assignee = { id: number; name: string };
type WidgetRef = { id: number; name: string; status: string };
type History = { flow: Flow; past: Flow[]; future: Flow[] };

type Panels = { steps: boolean; settings: boolean };
const PANELS_KEY = 'piotrack.chat-builder.panels';

/** Which side panels are showing, remembered in this browser (a convenience: it may be unavailable). */
function usePanels(): [Panels, (change: Partial<Panels>) => void] {
    const [panels, setPanels] = useState<Panels>(() => {
        try {
            const saved = JSON.parse(window.localStorage.getItem(PANELS_KEY) ?? 'null') as Partial<Panels> | null;
            return { steps: saved?.steps !== false, settings: saved?.settings !== false };
        } catch {
            return { steps: true, settings: true };
        }
    });
    const update = useCallback((change: Partial<Panels>) => {
        setPanels((current) => {
            const next = { ...current, ...change };
            try {
                window.localStorage.setItem(PANELS_KEY, JSON.stringify(next));
            } catch {
                // Not remembered; it still works for this visit.
            }
            return next;
        });
    }, []);
    return [panels, update];
}

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
 * The conversation builder: steps and templates on the left, the conversation
 * drawn as a tree on the canvas, the selected step's settings on the right.
 * Steps are dragged onto the line where they belong - or added with the "+"
 * on any line - and the builder does the wiring. Every change can be undone.
 */
export function ConversationBuilder({
    widget,
    widgets = [],
    flow: initialFlow,
    validation: initialValidation,
    templates,
    assignees,
}: {
    widget: WidgetRef;
    widgets?: WidgetRef[];
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
    const [pendingDelete, setPendingDelete] = useState<{ next: Flow; count: number } | null>(null);
    const [galleryOpen, setGalleryOpen] = useState(false);
    const [galleryKey, setGalleryKey] = useState<string | null>(null);
    const [testOpen, setTestOpen] = useState(false);
    const [panels, setPanels] = usePanels();
    const [focus, setFocus] = useState(false);
    const [sheetOpen, setSheetOpen] = useState(false);
    const [collapsed, setCollapsed] = useState<Set<string>>(() => new Set());
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

    // Keys: undo and redo; Esc puts down a picked step, then deselects, then
    // leaves focus mode; Delete removes the selected step.
    const keys = useRef<(e: KeyboardEvent) => void>(() => undefined);
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => keys.current(e);
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    // A selected step that no longer exists (undone, deleted) is deselected.
    useEffect(() => {
        if (selected && !flow.nodes[selected]) setSelected(null);
    }, [flow, selected]);

    // Focus mode covers the app's menu and header; the page behind stays still.
    useEffect(() => {
        if (!focus) return;
        const before = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = before;
        };
    }, [focus]);

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

    /** Pick a step in the library to place with a click - or put it down again. */
    const pick = (key: string) => {
        setNotice(null);
        setDragging((current) => (current?.kind === 'block' && current.key === key && current.pick ? null : { kind: 'block', key, pick: true }));
    };

    const place = (slot: Slot) => {
        if (!dragging) return;
        if (dragging.kind === 'block') insertBlock(slot, dragging.key);
        else {
            apply(moveStep(flow, dragging.id, slot));
            setSelected(dragging.id);
        }
        setDragging(null);
    };

    /** Select a step and bring its settings into view: the side panel, or a slide-over on a narrow screen. */
    const openSettings = (id: string) => {
        setSelected(id);
        if (wide) setPanels({ settings: true });
        else setSheetOpen(true);
    };

    const remove = (id: string) => {
        const { flow: next, removed } = removeStep(flow, id);
        if (removed.length > 1) {
            setPendingDelete({ next, count: removed.length - 1 });
            return;
        }
        apply(next);
        setSelected(null);
    };

    const neighbours = (id: string) => {
        const place = locate(tree.root, id);
        if (!place) return null;
        return { ...place, prev: place.branch.steps[place.index - 1], next: place.branch.steps[place.index + 1] };
    };

    const canMove = (id: string) => {
        const n = neighbours(id);
        return { up: Boolean(n?.prev && isMovable(n.prev.node)), down: Boolean(n?.next && isMovable(n.next.node)) };
    };

    const move = (id: string, direction: 'up' | 'down') => {
        const n = neighbours(id);
        if (!n) return;
        if (direction === 'up' && n.prev) {
            apply(moveStep(flow, id, n.prev.via));
        } else if (direction === 'down' && n.next) {
            const slot = afterSlot(tree.root, n.next.id);
            if (slot) apply(moveStep(flow, id, slot));
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

    const openTemplates = (key: string | null = null) => {
        setGalleryKey(key);
        setGalleryOpen(true);
    };

    keys.current = (e: KeyboardEvent) => {
        const target = e.target as HTMLElement | null;
        if (target && (['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) || target.isContentEditable)) return;
        // Menus and dialogs handle their own keys.
        if (target?.closest('[role="menu"], [role="dialog"], [role="listbox"]')) return;
        if (e.ctrlKey || e.metaKey) {
            if (e.key.toLowerCase() === 'z' && !e.shiftKey) {
                e.preventDefault();
                undo();
            } else if ((e.key.toLowerCase() === 'z' && e.shiftKey) || e.key.toLowerCase() === 'y') {
                e.preventDefault();
                redo();
            }
            return;
        }
        if (e.key === 'Escape') {
            if (dragging) setDragging(null);
            else if (selected) setSelected(null);
            else if (focus) setFocus(false);
        } else if ((e.key === 'Delete' || e.key === 'Backspace') && selected) {
            e.preventDefault();
            remove(selected);
        }
    };

    const editor = {
        flow,
        selected,
        select: setSelected,
        openSettings,
        dragging,
        setDragging,
        place,
        insertBlock,
        move,
        canMove,
        duplicate: (id: string) => {
            const copy = duplicateStep(flow, tree.root, id);
            if (!copy) return;
            apply(copy.flow);
            setSelected(copy.id);
        },
        toggleRequired: (id: string) => patchNode(id, { optional: !flow.nodes[id]?.optional }),
        setText: (id: string, text: string) => patchNode(id, { text }),
        renameReply: (id: string, optionId: string, label: string) =>
            patchNode(id, { options: (flow.nodes[id]?.options ?? []).map((o) => (o.id === optionId ? { ...o, label } : o)) }),
        addReply: (id: string) => {
            if (!flow.nodes[id]) return null;
            const added = addOption(flow, tree.root, id);
            apply(added.flow);
            return added.optionId;
        },
        removeReply: (id: string, optionId: string) => apply(removeOption(flow, id, optionId)),
        addQuickReplies: (id: string) => {
            apply(withQuickReplies(flow, id));
            setSelected(id);
        },
        remove,
        collapsed,
        toggleCollapsed: (id: string) =>
            setCollapsed((current) => {
                const next = new Set(current);
                if (next.has(id)) next.delete(id);
                else next.add(id);
                return next;
            }),
        issues,
    };

    const settings = selected ? (
        <StepSettings
            key={selected}
            flow={flow}
            id={selected}
            root={tree.root}
            assignees={assignees}
            onPatch={(patch, mergeKey) => patchNode(selected, patch, mergeKey)}
            onApply={apply}
            onDelete={() => remove(selected)}
            onMove={(direction) => move(selected, direction)}
            onClose={() => {
                setSelected(null);
                setSheetOpen(false);
            }}
            onHide={wide ? () => setPanels({ settings: false }) : undefined}
        />
    ) : null;

    const pickedKey = dragging?.kind === 'block' && dragging.pick ? dragging.key : null;
    const height = focus ? 'h-full min-h-0' : 'h-[calc(100vh-16rem)] min-h-[560px]';
    const columns = `${panels.steps ? '240px' : '60px'} minmax(0,1fr)${panels.settings ? ' 320px' : ''}`;

    const toolbar = (
        <div className="bg-card/95 flex items-center gap-0.5 rounded-lg border p-1 shadow-sm">
            <Button
                variant="ghost"
                size="sm"
                className="h-8 gap-1.5 px-2"
                onClick={() => setPanels({ steps: !panels.steps })}
                aria-label={panels.steps ? 'Hide steps panel' : 'Show steps panel'}
                aria-pressed={panels.steps}
                title={panels.steps ? 'Hide the steps panel' : 'Show the steps panel'}
            >
                {panels.steps ? <PanelLeftClose className="size-4" aria-hidden /> : <PanelLeftOpen className="size-4" aria-hidden />}
                Steps
            </Button>
            {wide && (
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-8 gap-1.5 px-2"
                    onClick={() => setPanels({ settings: !panels.settings })}
                    aria-label={panels.settings ? 'Hide settings panel' : 'Show settings panel'}
                    aria-pressed={panels.settings}
                    title={panels.settings ? 'Hide the settings panel' : 'Show the settings panel'}
                >
                    {panels.settings ? <PanelRightClose className="size-4" aria-hidden /> : <PanelRightOpen className="size-4" aria-hidden />}
                    Settings
                </Button>
            )}
            <span className="bg-border mx-1 h-5 w-px" aria-hidden />
            <Button
                variant="ghost"
                size="sm"
                className="h-8 gap-1.5 px-2"
                onClick={() => setFocus((on) => !on)}
                aria-pressed={focus}
                title={focus ? 'Back to the page (Esc)' : 'Hide the app menu and use the whole window'}
            >
                {focus ? <Minimize2 className="size-4" aria-hidden /> : <Maximize2 className="size-4" aria-hidden />}
                {focus ? 'Exit focus' : 'Focus mode'}
            </Button>
        </div>
    );

    return (
        <div className={focus ? 'bg-background fixed inset-0 z-40 flex flex-col gap-3 p-3' : 'space-y-4 p-4'}>
            <PageHeader
                title="Conversation Builder"
                description="Create, test and publish your website chat conversation without code."
                actions={
                    <div className="flex flex-wrap items-center gap-2">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" className="max-w-56">
                                    <MessagesSquare className="size-4" aria-hidden />
                                    <span className="truncate">{widget.name}</span>
                                    <ChevronDown className="size-3.5 opacity-60" aria-hidden />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-64">
                                <DropdownMenuLabel>Your chat widgets</DropdownMenuLabel>
                                {(widgets.length > 0 ? widgets : [widget]).map((w) => (
                                    <DropdownMenuItem
                                        key={w.id}
                                        disabled={w.id === widget.id}
                                        onSelect={() => router.visit(route('chat.flow.edit', w.id))}
                                    >
                                        <span className="min-w-0 flex-1 truncate">{w.name}</span>
                                        <span className="text-muted-foreground text-[11px] capitalize">
                                            {w.status === 'active' ? 'Live' : w.status}
                                        </span>
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>

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

                        {!live && (
                            <Button variant="outline" onClick={() => save(false)} disabled={saving}>
                                <Save className="size-4" aria-hidden /> Save
                            </Button>
                        )}
                        <Button variant="outline" onClick={() => setTestOpen(true)}>
                            <Play className="size-4" aria-hidden /> Test
                        </Button>
                        <Button onClick={() => save(true)} disabled={saving || !validation.valid}>
                            <Send className="size-4" aria-hidden /> {live ? 'Publish changes' : 'Publish'}
                        </Button>

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="icon" aria-label="More">
                                    <MoreVertical className="size-4" aria-hidden />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-52">
                                <DropdownMenuItem onSelect={() => openTemplates()}>
                                    <LayoutTemplate className="size-4" aria-hidden /> Browse templates
                                </DropdownMenuItem>
                                <DropdownMenuItem onSelect={() => router.visit(route('chat.widgets.edit', widget.id))}>
                                    <Settings2 className="size-4" aria-hidden /> Widget settings
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem disabled={history.past.length === 0} onSelect={undo}>
                                    <Undo2 className="size-4" aria-hidden /> Undo
                                </DropdownMenuItem>
                                <DropdownMenuItem disabled={history.future.length === 0} onSelect={redo}>
                                    <Redo2 className="size-4" aria-hidden /> Redo
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                }
            />

            <FormErrors errors={refused} />
            <StatusLine validation={validation} dirty={dirty} live={live} onSelect={openSettings} />
            {notice && (
                <p role="status" className="rounded-lg bg-indigo-50 px-3 py-2 text-sm text-indigo-800 dark:bg-indigo-500/15 dark:text-indigo-200">
                    {notice}
                </p>
            )}

            <FlowEditorContext.Provider value={editor}>
                <div className={`grid gap-4 ${focus ? 'min-h-0 flex-1' : 'items-start'}`} style={wide ? { gridTemplateColumns: columns } : undefined}>
                    {(wide || panels.steps) && (
                        <aside
                            className={`bg-card order-last flex min-h-0 flex-col overflow-hidden rounded-xl border xl:order-none ${
                                wide ? height : 'max-h-[420px]'
                            }`}
                        >
                            {panels.steps ? (
                                <StepLibrary
                                    picking={pickedKey}
                                    onPick={pick}
                                    setDragging={setDragging}
                                    templates={templates}
                                    onOpenTemplate={(key) => openTemplates(key)}
                                    onToggle={() => setPanels({ steps: false })}
                                />
                            ) : (
                                <StepRail picking={pickedKey} onPick={pick} setDragging={setDragging} onToggle={() => setPanels({ steps: true })} />
                            )}
                        </aside>
                    )}

                    <FlowCanvas className={height} toolbar={toolbar} />

                    {wide ? (
                        panels.settings && (
                            <aside className={`bg-card flex flex-col overflow-hidden rounded-xl border ${height}`}>
                                {settings ?? <GettingStarted flow={flow} onHide={() => setPanels({ settings: false })} />}
                            </aside>
                        )
                    ) : (
                        <Sheet open={sheetOpen && selected !== null} onOpenChange={(open) => !open && setSheetOpen(false)}>
                            {/* The settings carry their own close button; the slide-over's own would sit on top of it. */}
                            <SheetContent className="w-full p-0 sm:max-w-md [&>button:last-child]:hidden">
                                <SheetTitle className="sr-only">Step settings</SheetTitle>
                                {settings}
                            </SheetContent>
                        </Sheet>
                    )}
                </div>
            </FlowEditorContext.Provider>

            <div className={`bg-card grid gap-px overflow-hidden rounded-xl border sm:grid-cols-2 xl:grid-cols-4 ${focus ? 'hidden' : ''}`}>
                <Feature
                    icon={<LayoutTemplate className="size-5 text-indigo-600" aria-hidden />}
                    title="Pre-built Templates"
                    text="Start from a conversation made for your type of business, then change anything."
                    action={
                        <Button size="sm" variant="outline" onClick={() => openTemplates()}>
                            Browse Templates
                        </Button>
                    }
                />
                <Feature
                    icon={<PlayCircle className="size-5 text-indigo-600" aria-hidden />}
                    title="Test Your Bot"
                    text="Chat with your draft exactly as visitors will, before you publish it."
                    action={
                        <Button size="sm" variant="outline" onClick={() => setTestOpen(true)}>
                            Start Testing
                        </Button>
                    }
                />
                <Feature
                    icon={<Undo2 className="size-5 text-indigo-600" aria-hidden />}
                    title="Undo Anything"
                    text="Every change here can be undone and redone, until you leave the page."
                    action={
                        <Button size="sm" variant="outline" onClick={undo} disabled={history.past.length === 0}>
                            Undo Last Change
                        </Button>
                    }
                />
                <Feature
                    icon={<Settings2 className="size-5 text-indigo-600" aria-hidden />}
                    title="Look and Install"
                    text="Colours, logo, where the chat appears, and the code for your website."
                    action={
                        <Button size="sm" variant="outline" onClick={() => router.visit(route('chat.widgets.edit', widget.id))}>
                            Widget Settings
                        </Button>
                    }
                />
            </div>

            <TemplateGallery
                templates={templates}
                hasSteps={Object.keys(flow.nodes).length > 0}
                open={galleryOpen}
                onOpenChange={setGalleryOpen}
                initialKey={galleryKey}
                onUse={(t) => {
                    apply(JSON.parse(JSON.stringify(t.flow)) as Flow);
                    setSelected(null);
                    setNotice(`Loaded “${t.name}”. Change anything you like, then publish.`);
                }}
            />
            <TestDialog widgetId={widget.id} flow={flow} open={testOpen} onOpenChange={setTestOpen} />

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
        </div>
    );
}

function Feature({ icon, title, text, action }: { icon: ReactNode; title: string; text: string; action: ReactNode }) {
    return (
        <div className="bg-card flex items-start gap-3 p-4">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-500/15">{icon}</span>
            <div className="min-w-0 space-y-2">
                <div>
                    <p className="text-foreground text-sm font-semibold">{title}</p>
                    <p className="text-muted-foreground text-xs">{text}</p>
                </div>
                {action}
            </div>
        </div>
    );
}

/** One line on whether the conversation can go live, with the problems listed underneath. */
function StatusLine({
    validation,
    dirty,
    live,
    onSelect,
}: {
    validation: Validation;
    dirty: boolean;
    live: boolean;
    onSelect: (id: string) => void;
}) {
    if (validation.valid && validation.warnings.length === 0) {
        return (
            <div className="flex flex-wrap items-center gap-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm">
                <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400" aria-hidden />
                <span className="text-foreground">Ready to publish.</span>
                <span className="text-muted-foreground text-xs">
                    {live ? 'This chat is live: published changes reach visitors straight away.' : 'Nothing reaches your website until you publish.'}
                </span>
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
                <span className="text-muted-foreground text-xs">— marked on the steps</span>
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
                                    document
                                        .getElementById(`flow-step-${p.node}`)
                                        ?.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
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
function GettingStarted({ flow, onHide }: { flow: Flow; onHide: () => void }) {
    // Each detail once, in the order the fields are listed; required if any
    // path asks for it as required.
    const inputs = Object.values(flow.nodes).filter((n) => n.type === 'input' && n.field && CONTACT_FIELDS[n.field]);
    const collected = Object.keys(CONTACT_FIELDS)
        .filter((field) => inputs.some((n) => n.field === field))
        .map((field) => ({ label: CONTACT_FIELDS[field], required: inputs.some((n) => n.field === field && !n.optional) }));

    return (
        <div className="flex h-full min-h-0 flex-col">
            <div className="flex items-center justify-between border-b px-4 py-3">
                <h2 className="text-foreground text-sm font-semibold">Step Settings</h2>
                <Button size="icon" variant="ghost" className="size-7" onClick={onHide} aria-label="Hide settings panel" title="Hide this panel">
                    <PanelRightClose className="size-4" aria-hidden />
                </Button>
            </div>
            <div className="min-h-0 flex-1 space-y-5 overflow-y-auto p-4 text-sm">
                <p className="text-muted-foreground">Click a step's name on the canvas to change its settings here.</p>
                <div>
                    <h3 className="text-foreground font-semibold">How to build</h3>
                    <ol className="text-muted-foreground mt-2 list-decimal space-y-1.5 pl-4">
                        <li>Drag a step from the left onto a line - or click it, then click the place it goes.</li>
                        <li>Or point at a line and click its +.</li>
                        <li>Click any text on a card to change it there. Add replies to a question right on its card.</li>
                        <li>Mark contact details Required or Optional on their cards.</li>
                        <li>Hide the side panels, or use Focus mode, for more room.</li>
                        <li>Test it, then publish.</li>
                    </ol>
                </div>
                <div>
                    <h3 className="text-foreground font-semibold">Keys</h3>
                    <dl className="text-muted-foreground mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs">
                        <dt className="font-mono">Ctrl+Z</dt>
                        <dd>Undo (Ctrl+Shift+Z redo)</dd>
                        <dt className="font-mono">Delete</dt>
                        <dd>Delete the selected step</dd>
                        <dt className="font-mono">Esc</dt>
                        <dd>Cancel placing, then deselect, then leave focus mode</dd>
                    </dl>
                </div>
                <div>
                    <h3 className="text-foreground font-semibold">Details this chat collects</h3>
                    {collected.length === 0 ? (
                        <p className="text-muted-foreground mt-1">None yet. Add Email and First Name so every chat can become a lead.</p>
                    ) : (
                        <ul className="mt-2 space-y-1.5">
                            {collected.map((c, i) => (
                                <li key={i} className="flex items-center justify-between gap-2">
                                    <span className="text-foreground">{c.label}</span>
                                    <span
                                        className={`rounded-full px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase ${
                                            c.required ? 'bg-rose-500 text-white' : 'text-muted-foreground border'
                                        }`}
                                    >
                                        {c.required ? 'Required' : 'Optional'}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </div>
    );
}
