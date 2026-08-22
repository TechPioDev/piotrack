import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    CircleDot,
    Copy,
    Flag,
    GitBranch,
    Headphones,
    MessageSquare,
    Play,
    Plus,
    Send,
    Tag,
    Target,
    Trash2,
    UserCheck,
    XCircle,
} from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';

/** One step in the conversation. Shape mirrors the server-side flow graph. */
type Option = { id: string; label: string; score?: number; next?: string | null; priority?: string };
type Node = {
    type: string;
    text?: string;
    next?: string | null;
    otherwise?: string | null;
    field?: string;
    input?: string;
    optional?: boolean;
    options?: Option[];
    points?: number;
    tag?: string;
    assignee_id?: number | null;
    outcome?: string;
    operator?: string;
    value?: string;
};
type Flow = { start: string | null; nodes: Record<string, Node> };
type Issue = { node: string | null; message: string };
type Validation = { valid: boolean; errors: Issue[]; warnings: Issue[] };
type Template = { key: string; name: string; description: string; steps: number };
type Assignee = { id: number; name: string };

type TestNode = { id: string; type: string; text: string; options?: { id: string; label: string }[]; input?: string; optional?: boolean };
type TestMessage = { role: string; body: string };

/** The palette of steps a tenant can add, in the order they usually reach for them. */
const STEP_TYPES: { type: string; label: string; hint: string; icon: typeof MessageSquare; color: string }[] = [
    { type: 'message', label: 'Message', hint: 'Say something, then continue', icon: MessageSquare, color: 'text-sky-600' },
    { type: 'choice', label: 'Question', hint: 'Ask with buttons to choose from', icon: CircleDot, color: 'text-brand-strong' },
    { type: 'input', label: 'Collect answer', hint: 'Ask them to type something', icon: Send, color: 'text-violet-600' },
    { type: 'condition', label: 'Condition', hint: 'Branch on an earlier answer', icon: GitBranch, color: 'text-amber-600' },
    { type: 'score', label: 'Add score', hint: 'Adjust the lead score', icon: Target, color: 'text-emerald-600' },
    { type: 'tag', label: 'Tag', hint: 'Label the conversation', icon: Tag, color: 'text-pink-600' },
    { type: 'assign', label: 'Assign', hint: 'Route to a salesperson', icon: UserCheck, color: 'text-indigo-600' },
    { type: 'handoff', label: 'Talk to a human', hint: 'Connect to an available agent', icon: Headphones, color: 'text-rose-600' },
    { type: 'end', label: 'End', hint: 'Finish the conversation', icon: Flag, color: 'text-slate-600' },
];

const INPUT_KINDS = ['text', 'email', 'phone', 'number', 'company'];
const OPERATORS = [
    { id: 'equals', label: 'is' },
    { id: 'not_equals', label: 'is not' },
    { id: 'contains', label: 'contains' },
    { id: 'is_set', label: 'has any value' },
    { id: 'gte', label: 'is at least' },
    { id: 'lte', label: 'is at most' },
];

function meta(type: string) {
    return STEP_TYPES.find((s) => s.type === type) ?? STEP_TYPES[0];
}

/**
 * POST JSON to the app. The project has no axios; Laravel accepts the
 * XSRF-TOKEN cookie Inertia already maintains as an X-XSRF-TOKEN header.
 */
async function postJson<T>(url: string, body: unknown): Promise<T> {
    const xsrf = document.cookie
        .split('; ')
        .find((c) => c.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf) } : {}),
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });

    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw Object.assign(new Error('request failed'), { status: response.status, data });
    }
    return data as T;
}

/** A readable one-liner for a step in the list. */
function summarise(node: Node): string {
    if (node.type === 'score') return `${(node.points ?? 0) >= 0 ? '+' : ''}${node.points ?? 0} points`;
    if (node.type === 'tag') return node.tag ? `Tag "${node.tag}"` : 'No tag set';
    if (node.type === 'assign') return node.assignee_id ? 'Route to a salesperson' : 'No one selected';
    if (node.type === 'condition') return node.field ? `If ${node.field} …` : 'No answer chosen';
    if (node.type === 'handoff') return 'Offer a live agent, then continue';
    return node.text?.trim() || 'No text yet';
}

/** Unique, readable ids so the saved graph stays legible. */
function newId(type: string, nodes: Record<string, Node>): string {
    const base = type === 'choice' ? 'question' : type === 'input' ? 'collect' : type;
    let n = 1;
    while (nodes[`${base}_${n}`]) n += 1;
    return `${base}_${n}`;
}

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
    const [flow, setFlow] = useState<Flow>(() => ({ start: initialFlow.start ?? null, nodes: initialFlow.nodes ?? {} }));
    const [selected, setSelected] = useState<string | null>(initialFlow.start ?? Object.keys(initialFlow.nodes ?? {})[0] ?? null);
    const [validation, setValidation] = useState<Validation>(initialValidation);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const ids = useMemo(() => Object.keys(flow.nodes), [flow.nodes]);
    const node = selected ? flow.nodes[selected] : null;

    /** Every mutation goes through here so validation and dirty state stay honest. */
    const apply = useCallback(
        (next: Flow) => {
            setFlow(next);
            setDirty(true);
            postJson<Validation>(route('chat.flow.validate', widget.id), { flow: next })
                .then(setValidation)
                .catch(() => undefined);
        },
        [widget.id],
    );

    const patchNode = (id: string, patch: Partial<Node>) => apply({ ...flow, nodes: { ...flow.nodes, [id]: { ...flow.nodes[id], ...patch } } });

    const addNode = (type: string) => {
        const id = newId(type, flow.nodes);
        const created: Node =
            type === 'choice'
                ? { type, text: 'What can we help you with?', options: [{ id: 'option_1', label: 'First answer', score: 0, next: null }] }
                : type === 'input'
                  ? { type, text: 'What is your email?', input: 'email', field: 'email', next: null }
                  : type === 'condition'
                    ? { type, field: '', operator: 'equals', value: '', next: null, otherwise: null }
                    : type === 'score'
                      ? { type, points: 10, next: null }
                      : type === 'tag'
                        ? { type, tag: '', next: null }
                        : type === 'assign'
                          ? { type, assignee_id: null, next: null }
                          : type === 'handoff'
                            ? { type, next: null }
                            : type === 'end'
                              ? { type, outcome: 'lead', text: 'Thanks — we will be in touch shortly.' }
                              : { type: 'message', text: 'Hello!', next: null };

        const nodes = { ...flow.nodes, [id]: created };
        apply({ start: flow.start ?? id, nodes });
        setSelected(id);
    };

    const duplicateNode = (id: string) => {
        const copy = newId(flow.nodes[id].type, flow.nodes);
        apply({ ...flow, nodes: { ...flow.nodes, [copy]: JSON.parse(JSON.stringify(flow.nodes[id])) } });
        setSelected(copy);
    };

    const deleteNode = (id: string) => {
        const nodes = { ...flow.nodes };
        delete nodes[id];
        // Clear anything that pointed at it, so the graph never keeps a dead link.
        for (const [key, n] of Object.entries(nodes)) {
            const cleaned: Node = { ...n };
            if (cleaned.next === id) cleaned.next = null;
            if (cleaned.otherwise === id) cleaned.otherwise = null;
            if (cleaned.options) cleaned.options = cleaned.options.map((o) => (o.next === id ? { ...o, next: null } : o));
            nodes[key] = cleaned;
        }
        const remaining = Object.keys(nodes);
        apply({ start: flow.start === id ? (remaining[0] ?? null) : flow.start, nodes });
        setSelected(remaining[0] ?? null);
    };

    const save = (publish: boolean) => {
        setSaving(true);
        router.put(
            route('chat.flow.update', widget.id),
            { flow, publish },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Website Chat', href: '/chat' },
        { title: 'Widgets', href: '/chat/widgets' },
        { title: widget.name, href: `/chat/widgets/${widget.id}/flow` },
    ];

    /** Dropdown of steps a connection can point at. */
    const StepPicker = ({ value, onChange, label }: { value: string | null | undefined; onChange: (v: string | null) => void; label: string }) => (
        <div className="grid gap-1">
            <Label>{label}</Label>
            <Select value={value ?? '__none'} onValueChange={(v) => onChange(v === '__none' ? null : v)}>
                <SelectTrigger>
                    <SelectValue placeholder="Not connected" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="__none">Not connected</SelectItem>
                    {ids
                        .filter((id) => id !== selected)
                        .map((id) => (
                            <SelectItem key={id} value={id}>
                                {meta(flow.nodes[id].type).label}: {summarise(flow.nodes[id]).slice(0, 40)}
                            </SelectItem>
                        ))}
                </SelectContent>
            </Select>
        </div>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${widget.name} — conversation`} />
            <div className="space-y-4 p-4">
                <PageHeader
                    title="Conversation builder"
                    description="Design what your widget asks visitors. Every change is saved as a draft until you publish."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <TemplateDialog templates={templates} widgetId={widget.id} />
                            <TestDialog widgetId={widget.id} flow={flow} />
                            <Button variant="outline" onClick={() => save(false)} disabled={saving}>
                                Save draft
                            </Button>
                            <Button onClick={() => save(true)} disabled={saving || !validation.valid}>
                                {widget.status === 'active' ? 'Publish changes' : 'Publish'}
                            </Button>
                        </div>
                    }
                />

                <ValidationBanner validation={validation} dirty={dirty} onSelect={setSelected} />

                <div className="grid gap-4 lg:grid-cols-[300px_1fr]">
                    {/* Steps */}
                    <div className="border-border bg-card h-fit rounded-lg border">
                        <div className="border-border flex items-center justify-between border-b px-3 py-2.5">
                            <h2 className="text-foreground text-sm font-semibold">Steps</h2>
                            <AddStepMenu onAdd={addNode} />
                        </div>
                        {ids.length === 0 ? (
                            <p className="text-muted-foreground px-3 py-6 text-center text-sm">No steps yet. Add one, or start from a template.</p>
                        ) : (
                            <ul className="divide-border divide-y">
                                {ids.map((id) => {
                                    const m = meta(flow.nodes[id].type);
                                    const Icon = m.icon;
                                    const hasError = validation.errors.some((e) => e.node === id);
                                    return (
                                        <li key={id}>
                                            <button
                                                type="button"
                                                onClick={() => setSelected(id)}
                                                className={`hover:bg-muted/50 flex w-full items-start gap-2.5 px-3 py-2.5 text-left transition-colors ${
                                                    selected === id ? 'bg-brand-soft/60' : ''
                                                }`}
                                            >
                                                <Icon className={`mt-0.5 size-4 shrink-0 ${m.color}`} aria-hidden />
                                                <span className="min-w-0 flex-1">
                                                    <span className="flex items-center gap-1.5">
                                                        <span className="text-foreground truncate text-sm font-medium">{m.label}</span>
                                                        {flow.start === id && (
                                                            <Badge variant="secondary" className="px-1.5 py-0 text-[10px]">
                                                                Start
                                                            </Badge>
                                                        )}
                                                        {hasError && (
                                                            <AlertTriangle className="size-3.5 shrink-0 text-red-500" aria-label="Has a problem" />
                                                        )}
                                                    </span>
                                                    <span className="text-muted-foreground line-clamp-2 text-xs">{summarise(flow.nodes[id])}</span>
                                                </span>
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </div>

                    {/* Inspector */}
                    <div className="border-border bg-card rounded-lg border">
                        {!node || !selected ? (
                            <p className="text-muted-foreground p-8 text-center text-sm">Select a step on the left to edit it.</p>
                        ) : (
                            <>
                                <div className="border-border flex flex-wrap items-center gap-2 border-b px-4 py-3">
                                    <h2 className="text-foreground mr-auto text-sm font-semibold">{meta(node.type).label} step</h2>
                                    {flow.start !== selected && (
                                        <Button size="sm" variant="outline" onClick={() => apply({ ...flow, start: selected })}>
                                            Make this the start
                                        </Button>
                                    )}
                                    <Button size="sm" variant="outline" onClick={() => duplicateNode(selected)}>
                                        <Copy className="size-3.5" aria-hidden /> Duplicate
                                    </Button>
                                    <Button size="sm" variant="outline" className="text-red-600" onClick={() => deleteNode(selected)}>
                                        <Trash2 className="size-3.5" aria-hidden /> Delete
                                    </Button>
                                </div>

                                <div className="space-y-4 p-4">
                                    {['message', 'choice', 'input', 'end'].includes(node.type) && (
                                        <div className="grid gap-1">
                                            <Label htmlFor="text">{node.type === 'end' ? 'Closing message' : 'What the widget says'}</Label>
                                            <Input
                                                id="text"
                                                value={node.text ?? ''}
                                                onChange={(e) => patchNode(selected, { text: e.target.value })}
                                            />
                                        </div>
                                    )}

                                    {node.type === 'input' && (
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div className="grid gap-1">
                                                <Label>Answer type</Label>
                                                <Select value={node.input ?? 'text'} onValueChange={(v) => patchNode(selected, { input: v })}>
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {INPUT_KINDS.map((k) => (
                                                            <SelectItem key={k} value={k} className="capitalize">
                                                                {k}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="field">Save the answer as</Label>
                                                <Input
                                                    id="field"
                                                    value={node.field ?? ''}
                                                    onChange={(e) => patchNode(selected, { field: e.target.value })}
                                                    placeholder="email"
                                                />
                                            </div>
                                            <label className="flex items-center gap-2 text-sm sm:col-span-2">
                                                <input
                                                    type="checkbox"
                                                    checked={Boolean(node.optional)}
                                                    onChange={(e) => patchNode(selected, { optional: e.target.checked })}
                                                />
                                                Visitors may skip this
                                            </label>
                                        </div>
                                    )}

                                    {node.type === 'choice' && (
                                        <OptionsEditor
                                            node={node}
                                            ids={ids}
                                            selected={selected}
                                            nodes={flow.nodes}
                                            onChange={(options) => patchNode(selected, { options })}
                                            onFieldChange={(field) => patchNode(selected, { field })}
                                        />
                                    )}

                                    {node.type === 'condition' && (
                                        <div className="space-y-3">
                                            <div className="grid gap-3 sm:grid-cols-3">
                                                <div className="grid gap-1">
                                                    <Label htmlFor="cond-field">Answer to check</Label>
                                                    <Input
                                                        id="cond-field"
                                                        value={node.field ?? ''}
                                                        onChange={(e) => patchNode(selected, { field: e.target.value })}
                                                        placeholder="company_size"
                                                    />
                                                </div>
                                                <div className="grid gap-1">
                                                    <Label>Test</Label>
                                                    <Select
                                                        value={node.operator ?? 'equals'}
                                                        onValueChange={(v) => patchNode(selected, { operator: v })}
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {OPERATORS.map((o) => (
                                                                <SelectItem key={o.id} value={o.id}>
                                                                    {o.label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div className="grid gap-1">
                                                    <Label htmlFor="cond-value">Value</Label>
                                                    <Input
                                                        id="cond-value"
                                                        value={node.value ?? ''}
                                                        onChange={(e) => patchNode(selected, { value: e.target.value })}
                                                        disabled={node.operator === 'is_set'}
                                                    />
                                                </div>
                                            </div>
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                <StepPicker
                                                    label="If true, go to"
                                                    value={node.next}
                                                    onChange={(v) => patchNode(selected, { next: v })}
                                                />
                                                <StepPicker
                                                    label="Otherwise, go to"
                                                    value={node.otherwise}
                                                    onChange={(v) => patchNode(selected, { otherwise: v })}
                                                />
                                            </div>
                                        </div>
                                    )}

                                    {node.type === 'score' && (
                                        <div className="grid gap-1 sm:max-w-xs">
                                            <Label htmlFor="points">Points to add</Label>
                                            <Input
                                                id="points"
                                                type="number"
                                                value={node.points ?? 0}
                                                onChange={(e) => patchNode(selected, { points: Number(e.target.value) })}
                                            />
                                        </div>
                                    )}

                                    {node.type === 'tag' && (
                                        <div className="grid gap-1 sm:max-w-sm">
                                            <Label htmlFor="tag">Tag</Label>
                                            <Input
                                                id="tag"
                                                value={node.tag ?? ''}
                                                onChange={(e) => patchNode(selected, { tag: e.target.value })}
                                                placeholder="high-intent"
                                            />
                                        </div>
                                    )}

                                    {node.type === 'assign' && (
                                        <div className="grid gap-1 sm:max-w-sm">
                                            <Label>Route to</Label>
                                            <Select
                                                value={node.assignee_id ? String(node.assignee_id) : '__none'}
                                                onValueChange={(v) => patchNode(selected, { assignee_id: v === '__none' ? null : Number(v) })}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Choose a salesperson" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="__none">Use automatic routing</SelectItem>
                                                    {assignees.map((a) => (
                                                        <SelectItem key={a.id} value={String(a.id)}>
                                                            {a.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    )}

                                    {node.type === 'end' && (
                                        <div className="grid gap-1 sm:max-w-sm">
                                            <Label>What this outcome counts as</Label>
                                            <Select value={node.outcome ?? 'lead'} onValueChange={(v) => patchNode(selected, { outcome: v })}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="lead">A new lead</SelectItem>
                                                    <SelectItem value="meeting">A lead, and offer a meeting</SelectItem>
                                                    <SelectItem value="support">An existing customer (no lead)</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    )}

                                    {node.type === 'handoff' && (
                                        <p className="text-muted-foreground text-sm">
                                            If an agent is online and you are inside business hours, the visitor is connected to them and the
                                            conversation goes live. Otherwise they are told when to expect a reply and the steps below carry on
                                            collecting their details.
                                        </p>
                                    )}

                                    {['message', 'input', 'score', 'tag', 'assign', 'handoff'].includes(node.type) && (
                                        <div className="sm:max-w-sm">
                                            <StepPicker label="Then go to" value={node.next} onChange={(v) => patchNode(selected, { next: v })} />
                                        </div>
                                    )}
                                </div>
                            </>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function AddStepMenu({ onAdd }: { onAdd: (type: string) => void }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Plus className="size-3.5" aria-hidden /> Add
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Add a step</DialogTitle>
                <div className="grid gap-2 sm:grid-cols-2">
                    {STEP_TYPES.map((s) => {
                        const Icon = s.icon;
                        return (
                            <button
                                key={s.type}
                                type="button"
                                onClick={() => {
                                    onAdd(s.type);
                                    setOpen(false);
                                }}
                                className="border-border hover:border-brand hover:bg-muted/40 flex items-start gap-2.5 rounded-lg border p-3 text-left transition-colors"
                            >
                                <Icon className={`mt-0.5 size-4 shrink-0 ${s.color}`} aria-hidden />
                                <span>
                                    <span className="text-foreground block text-sm font-medium">{s.label}</span>
                                    <span className="text-muted-foreground block text-xs">{s.hint}</span>
                                </span>
                            </button>
                        );
                    })}
                </div>
            </DialogContent>
        </Dialog>
    );
}

function OptionsEditor({
    node,
    ids,
    selected,
    nodes,
    onChange,
    onFieldChange,
}: {
    node: Node;
    ids: string[];
    selected: string;
    nodes: Record<string, Node>;
    onChange: (options: Option[]) => void;
    onFieldChange: (field: string) => void;
}) {
    const options = node.options ?? [];

    const update = (index: number, patch: Partial<Option>) => onChange(options.map((o, i) => (i === index ? { ...o, ...patch } : o)));

    return (
        <div className="space-y-3">
            <div className="grid gap-1 sm:max-w-xs">
                <Label htmlFor="choice-field">Save the answer as</Label>
                <Input id="choice-field" value={node.field ?? ''} onChange={(e) => onFieldChange(e.target.value)} placeholder="company_size" />
            </div>

            <div className="space-y-2">
                <Label>Answers</Label>
                {options.map((option, index) => (
                    <div key={index} className="border-border grid gap-2 rounded-lg border p-3 sm:grid-cols-[1fr_90px_1fr_auto]">
                        <Input value={option.label} onChange={(e) => update(index, { label: e.target.value })} placeholder="Answer text" />
                        <Input
                            type="number"
                            value={option.score ?? 0}
                            onChange={(e) => update(index, { score: Number(e.target.value) })}
                            aria-label="Points"
                            title="Points added when chosen"
                        />
                        <Select value={option.next ?? '__none'} onValueChange={(v) => update(index, { next: v === '__none' ? null : v })}>
                            <SelectTrigger aria-label="Then go to">
                                <SelectValue placeholder="Then go to…" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none">Not connected</SelectItem>
                                {ids
                                    .filter((id) => id !== selected)
                                    .map((id) => (
                                        <SelectItem key={id} value={id}>
                                            {meta(nodes[id].type).label}: {summarise(nodes[id]).slice(0, 32)}
                                        </SelectItem>
                                    ))}
                            </SelectContent>
                        </Select>
                        <Button
                            size="sm"
                            variant="outline"
                            className="text-red-600"
                            onClick={() => onChange(options.filter((_, i) => i !== index))}
                            aria-label={`Remove answer ${option.label}`}
                        >
                            <Trash2 className="size-3.5" aria-hidden />
                        </Button>
                    </div>
                ))}
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        onChange([
                            ...options,
                            { id: `option_${options.length + 1}_${Date.now().toString(36)}`, label: 'New answer', score: 0, next: null },
                        ])
                    }
                >
                    <Plus className="size-3.5" aria-hidden /> Add answer
                </Button>
            </div>
        </div>
    );
}

function ValidationBanner({ validation, dirty, onSelect }: { validation: Validation; dirty: boolean; onSelect: (id: string) => void }) {
    if (validation.valid && validation.warnings.length === 0) {
        return (
            <div className="flex items-center gap-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-2.5 text-sm">
                <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400" aria-hidden />
                <span className="text-foreground">This conversation is ready to publish.</span>
                {dirty && <span className="text-muted-foreground ml-auto text-xs">Unsaved changes</span>}
            </div>
        );
    }

    return (
        <div className="space-y-2">
            {validation.errors.length > 0 && (
                <div className="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3">
                    <div className="flex items-center gap-2 text-sm font-semibold text-red-700 dark:text-red-400">
                        <XCircle className="size-4" aria-hidden /> Fix these before publishing
                    </div>
                    <ul className="mt-1.5 space-y-1 text-sm">
                        {validation.errors.map((e, i) => (
                            <li key={i}>
                                {e.node ? (
                                    <button type="button" className="underline underline-offset-2" onClick={() => onSelect(e.node as string)}>
                                        {e.message}
                                    </button>
                                ) : (
                                    e.message
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            )}
            {validation.warnings.length > 0 && (
                <div className="rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3">
                    <div className="flex items-center gap-2 text-sm font-semibold text-amber-700 dark:text-amber-400">
                        <AlertTriangle className="size-4" aria-hidden /> Worth a look
                    </div>
                    <ul className="mt-1.5 space-y-1 text-sm">
                        {validation.warnings.map((w, i) => (
                            <li key={i}>{w.message}</li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}

function TemplateDialog({ templates, widgetId }: { templates: Template[]; widgetId: number }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">Templates</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Start from a template</DialogTitle>
                <p className="text-muted-foreground text-sm">This replaces the current conversation. You can edit every step afterwards.</p>
                <div className="max-h-[60vh] space-y-2 overflow-y-auto">
                    {templates.map((t) => (
                        <button
                            key={t.key}
                            type="button"
                            onClick={() => {
                                router.post(route('chat.flow.template', widgetId), { template: t.key }, { onFinish: () => setOpen(false) });
                            }}
                            className="border-border hover:border-brand hover:bg-muted/40 block w-full rounded-lg border p-3 text-left transition-colors"
                        >
                            <span className="text-foreground flex items-center gap-2 text-sm font-medium">
                                {t.name}
                                <Badge variant="secondary" className="text-[10px]">
                                    {t.steps} steps
                                </Badge>
                            </span>
                            <span className="text-muted-foreground mt-0.5 block text-xs">{t.description}</span>
                        </button>
                    ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}

/** Runs the draft through the real engine so the tenant tests what visitors get. */
function TestDialog({ widgetId, flow }: { widgetId: number; flow: Flow }) {
    const [open, setOpen] = useState(false);
    const [token, setToken] = useState<string | null>(null);
    const [messages, setMessages] = useState<TestMessage[]>([]);
    const [node, setNode] = useState<TestNode | null>(null);
    const [done, setDone] = useState(false);
    const [value, setValue] = useState('');
    const [error, setError] = useState<string | null>(null);

    const send = async (payload: { token?: string | null; option?: string; value?: string }, echo?: string) => {
        setError(null);
        if (echo !== undefined) setMessages((m) => [...m, { role: 'visitor', body: echo }]);
        try {
            const data = await postJson<{ token?: string; messages?: TestMessage[]; node?: TestNode | null; done?: boolean }>(
                route('chat.flow.test', widgetId),
                { flow, ...payload },
            );
            setToken(data.token ?? payload.token ?? null);
            setMessages((m) => [...m, ...(data.messages ?? [])]);
            setNode(data.node ?? null);
            setDone(Boolean(data.done));
        } catch (e) {
            const data = (e as { data?: { errors?: Record<string, string[]>; message?: string } }).data;
            setError(data?.errors ? Object.values(data.errors)[0][0] : (data?.message ?? 'Something went wrong.'));
        }
    };

    const restart = () => {
        setMessages([]);
        setNode(null);
        setDone(false);
        setToken(null);
        setError(null);
        void send({});
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(o) => {
                setOpen(o);
                if (o) restart();
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Play className="size-3.5" aria-hidden /> Test
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Test conversation</DialogTitle>
                <p className="text-muted-foreground -mt-2 text-xs">
                    This runs your unsaved draft through the real chat engine. Nothing is added to your CRM.
                </p>

                <div className="bg-muted/40 max-h-[45vh] min-h-[220px] space-y-2 overflow-y-auto rounded-lg p-3">
                    {messages.map((m, i) => (
                        <div key={i} className={`flex ${m.role === 'visitor' ? 'justify-end' : 'justify-start'}`}>
                            <div
                                className={`max-w-[85%] rounded-2xl px-3 py-2 text-sm ${
                                    m.role === 'visitor'
                                        ? 'bg-brand text-brand-foreground rounded-br-sm'
                                        : 'bg-card border-border rounded-bl-sm border'
                                }`}
                            >
                                {m.body}
                            </div>
                        </div>
                    ))}
                    {done && <p className="text-muted-foreground pt-1 text-center text-xs">Conversation finished.</p>}
                </div>

                {error && <p className="text-sm text-red-600">{error}</p>}

                {!done && node?.type === 'choice' && (
                    <div className="space-y-1.5">
                        {(node.options ?? []).map((o) => (
                            <Button
                                key={o.id}
                                variant="outline"
                                className="w-full justify-start"
                                onClick={() => send({ token, option: o.id }, o.label)}
                            >
                                {o.label}
                            </Button>
                        ))}
                    </div>
                )}

                {!done && node?.type === 'input' && (
                    <form
                        className="flex gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            const v = value.trim();
                            if (!v && !node.optional) return;
                            setValue('');
                            void send({ token, value: v }, v || '—');
                        }}
                    >
                        <Input value={value} onChange={(e) => setValue(e.target.value)} placeholder="Type an answer…" />
                        <Button type="submit">Send</Button>
                    </form>
                )}

                <Button variant="outline" size="sm" onClick={restart}>
                    Start over
                </Button>
            </DialogContent>
        </Dialog>
    );
}
