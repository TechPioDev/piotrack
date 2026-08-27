import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { TrendingDown, TrendingUp } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'AI Visibility', href: '/ai/visibility' }];

type Frequencies = {
    checks: number;
    mention_rate: number;
    citation_rate: number;
    recommendation_rate: number;
};

type EngineRow = {
    engine: string;
    checks: number;
    mention_rate: number;
    share_of_answer: number;
};

type CompetitorRow = {
    domain: string;
    appearances: number;
    share: number;
};

type DimensionRow = {
    value: string;
    checks: number;
    mention_rate: number;
};

type TrendPoint = {
    date: string;
    checks: number;
    mention_rate: number;
};

type ChangeAlert = {
    changed: boolean;
    direction: string;
    delta: number;
    current: number;
    previous: number;
};

type Prompt = {
    id: number;
    text: string;
    category: string | null;
    service: string | null;
    city: string | null;
    vertical: string | null;
    is_active: boolean;
};

function DimensionTable({ label, rows, empty }: { label: string; rows: DimensionRow[]; empty: string }) {
    return (
        <div>
            <h3 className="mb-2 text-sm font-medium">{label}</h3>
            {rows.length === 0 ? (
                <p className="text-muted-foreground text-sm">{empty}</p>
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="p-3 font-medium">{label}</th>
                                <th className="p-3 text-center font-medium">Checks</th>
                                <th className="p-3 text-center font-medium">Mention rate</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {rows.map((row) => (
                                <tr key={row.value} className="hover:bg-muted/40">
                                    <td className="p-3 font-medium">{row.value}</td>
                                    <td className="p-3 text-center">{row.checks}</td>
                                    <td className="text-muted-foreground p-3 text-center">{row.mention_rate}%</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function NewPromptDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm<{ text: string; category: string; service: string; city: string; vertical: string; is_active: boolean }>({
        text: '',
        category: '',
        service: '',
        city: '',
        vertical: '',
        is_active: true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('ai.visibility.prompts.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>Add prompt</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Add monitored prompt</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="prompt_text">Prompt</Label>
                        <Input
                            id="prompt_text"
                            value={form.data.text}
                            placeholder="best managed IT provider in Austin"
                            onChange={(e) => form.setData('text', e.target.value)}
                        />
                        <InputError message={form.errors.text} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="prompt_category">Category</Label>
                            <Input id="prompt_category" value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} />
                            <InputError message={form.errors.category} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="prompt_service">Service</Label>
                            <Input id="prompt_service" value={form.data.service} onChange={(e) => form.setData('service', e.target.value)} />
                            <InputError message={form.errors.service} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="prompt_city">City</Label>
                            <Input id="prompt_city" value={form.data.city} onChange={(e) => form.setData('city', e.target.value)} />
                            <InputError message={form.errors.city} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="prompt_vertical">Vertical</Label>
                            <Input id="prompt_vertical" value={form.data.vertical} onChange={(e) => form.setData('vertical', e.target.value)} />
                            <InputError message={form.errors.vertical} />
                        </div>
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={form.data.is_active} onCheckedChange={(v) => form.setData('is_active', v === true)} />
                        Include this prompt when checks run
                    </label>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Add
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function AiVisibility({
    frequencies,
    share_of_voice,
    by_engine,
    competitors,
    by_service,
    by_city,
    by_vertical,
    trend,
    alert,
    prompts,
    engines,
    engineStatuses,
    evidence,
}: {
    frequencies: Frequencies;
    share_of_voice: number;
    by_engine: EngineRow[];
    competitors: CompetitorRow[];
    by_service: DimensionRow[];
    by_city: DimensionRow[];
    by_vertical: DimensionRow[];
    trend: TrendPoint[];
    alert: ChangeAlert;
    prompts: Prompt[];
    engines: string[];
    engineStatuses: Record<string, string>;
    evidence: {
        id: number;
        prompt: string;
        engine: string;
        provider: string | null;
        mentioned: boolean;
        position: number | null;
        answer_excerpt: string | null;
        checked_at: string | null;
    }[];
}) {
    const { can } = usePermissions();
    const canManage = can('ai.prompts.manage');

    const trendPeak = trend.reduce((max, point) => Math.max(max, point.mention_rate), 0);
    const up = alert.direction === 'up';

    const frequencyCards: { label: string; value: string | number }[] = [
        { label: 'Checks recorded', value: frequencies.checks },
        { label: 'Mention rate', value: `${frequencies.mention_rate}%` },
        { label: 'Citation rate', value: `${frequencies.citation_rate}%` },
        { label: 'Recommendation rate', value: `${frequencies.recommendation_rate}%` },
    ];

    const runChecks = () => router.post(route('ai.visibility.run'), {}, { preserveScroll: true });
    const removePrompt = (id: number) => router.delete(route('ai.visibility.prompts.destroy', id), { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="AI Visibility" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading title="AI visibility" description="How often AI answer engines mention, cite and recommend you" />
                    {canManage && (
                        <div className="flex flex-wrap gap-2">
                            <Button variant="secondary" onClick={runChecks}>
                                Run checks
                            </Button>
                            <NewPromptDialog />
                        </div>
                    )}
                </div>

                {alert.changed && (
                    <Alert variant={up ? 'default' : 'destructive'} className={up ? '' : 'border-destructive bg-destructive/10'}>
                        {up ? <TrendingUp className="h-4 w-4" /> : <TrendingDown className="h-4 w-4" />}
                        <AlertTitle>
                            Mention rate moved {up ? 'up' : 'down'} {Math.abs(alert.delta)} points
                        </AlertTitle>
                        <AlertDescription>
                            This window is at {alert.current}%, against {alert.previous}% in the window before it.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {frequencyCards.map((card) => (
                        <Card key={card.label}>
                            <CardContent className="p-4">
                                <p className="text-muted-foreground text-sm">{card.label}</p>
                                <p className="text-2xl font-semibold">{card.value}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardContent className="flex flex-wrap items-center gap-6 p-4">
                        <div>
                            <p className="text-muted-foreground text-sm">Share of voice</p>
                            <p className="text-4xl font-semibold tracking-tight">{share_of_voice}%</p>
                        </div>
                        <div className="min-w-48 flex-1 space-y-2">
                            <div className="bg-muted h-3 overflow-hidden rounded-full">
                                <div className="bg-primary h-full rounded-full" style={{ width: `${Math.min(share_of_voice, 100)}%` }} />
                            </div>
                            <p className="text-muted-foreground text-sm">Average share of the answer across every recorded check.</p>
                        </div>
                    </CardContent>
                </Card>

                <div>
                    <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <h3 className="text-sm font-medium">By engine</h3>
                        {/* live = a wired API with a key answers this engine; simulated = fixture numbers (AIVM). */}
                        <div className="flex flex-wrap gap-1.5">
                            {engines.map((engine) => (
                                <span
                                    key={engine}
                                    className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                                        engineStatuses[engine] === 'live'
                                            ? 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300'
                                            : 'bg-muted text-muted-foreground'
                                    }`}
                                >
                                    {engine.replace(/_/g, ' ')} · {engineStatuses[engine] ?? 'simulated'}
                                </span>
                            ))}
                        </div>
                    </div>
                    {by_engine.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No checks recorded yet. Run the library to populate per-engine visibility.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Engine</th>
                                        <th className="p-3 text-center font-medium">Checks</th>
                                        <th className="p-3 text-center font-medium">Mention rate</th>
                                        <th className="p-3 text-center font-medium">Share of answer</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {by_engine.map((row) => (
                                        <tr key={row.engine} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{row.engine}</td>
                                            <td className="p-3 text-center">{row.checks}</td>
                                            <td className="p-3 text-center">{row.mention_rate}%</td>
                                            <td className="text-muted-foreground p-3 text-center">{row.share_of_answer}%</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Competitors in the same answers</h3>
                    {competitors.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No competitor domains have appeared alongside you yet.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Domain</th>
                                        <th className="p-3 text-center font-medium">Appearances</th>
                                        <th className="p-3 text-center font-medium">Share of checks</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {competitors.map((row) => (
                                        <tr key={row.domain} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{row.domain}</td>
                                            <td className="p-3 text-center">{row.appearances}</td>
                                            <td className="text-muted-foreground p-3 text-center">{row.share}%</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <DimensionTable label="Service" rows={by_service} empty="No prompts carry a service yet." />
                    <DimensionTable label="City" rows={by_city} empty="No prompts carry a city yet." />
                    <DimensionTable label="Vertical" rows={by_vertical} empty="No prompts carry a vertical yet." />
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Mention rate trend</h3>
                    {trend.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No trend yet. It builds up as checks are recorded day by day.</p>
                    ) : (
                        <div className="space-y-3 rounded-lg border p-4">
                            <div className="flex h-32 items-end gap-1">
                                {trend.map((point) => (
                                    <div
                                        key={point.date}
                                        title={`${point.date}: ${point.mention_rate}% of ${point.checks} checks`}
                                        className="bg-primary min-h-0.5 flex-1 rounded-t"
                                        style={{ height: `${trendPeak > 0 ? (point.mention_rate / trendPeak) * 100 : 0}%` }}
                                    />
                                ))}
                            </div>
                            <div className="text-muted-foreground flex justify-between text-xs">
                                <span>{trend[0].date}</span>
                                <span>{trend[trend.length - 1].date}</span>
                            </div>
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Evidence — the answers behind the numbers</h3>
                    {evidence.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No checks recorded yet. Every check stores the answer it was measured from.</p>
                    ) : (
                        <div className="space-y-2">
                            {evidence.map((check) => (
                                <details key={check.id} className="border-border bg-card rounded-lg border px-4 py-2.5">
                                    <summary className="flex cursor-pointer flex-wrap items-center gap-2 text-sm">
                                        <span className="font-medium">{check.prompt}</span>
                                        <span className="text-muted-foreground text-xs capitalize">{check.engine.replace(/_/g, ' ')}</span>
                                        <span
                                            className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                                                check.mentioned
                                                    ? 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300'
                                                    : 'bg-muted text-muted-foreground'
                                            }`}
                                        >
                                            {check.mentioned
                                                ? check.position !== null
                                                    ? `Mentioned #${check.position}`
                                                    : 'Mentioned'
                                                : 'Not mentioned'}
                                        </span>
                                        {check.provider === 'fixture' && (
                                            <span className="rounded-full bg-amber-500/15 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:text-amber-300">
                                                simulated
                                            </span>
                                        )}
                                        <span className="text-muted-foreground ml-auto text-xs">{check.checked_at}</span>
                                    </summary>
                                    <p className="text-muted-foreground mt-2 text-sm whitespace-pre-wrap">
                                        {check.answer_excerpt ?? 'No answer text stored for this check (recorded before evidence storage).'}
                                    </p>
                                </details>
                            ))}
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Prompt library</h3>
                    {prompts.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            No prompts monitored yet. Add the questions your buyers ask an AI assistant, then run the checks.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Prompt</th>
                                        <th className="p-3 font-medium">Category</th>
                                        <th className="p-3 font-medium">Service</th>
                                        <th className="p-3 font-medium">City</th>
                                        <th className="p-3 font-medium">Vertical</th>
                                        <th className="p-3 font-medium">State</th>
                                        {canManage && <th className="p-3 text-right font-medium">Actions</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {prompts.map((prompt) => (
                                        <tr key={prompt.id} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{prompt.text}</td>
                                            <td className="text-muted-foreground p-3">{prompt.category ?? '—'}</td>
                                            <td className="text-muted-foreground p-3">{prompt.service ?? '—'}</td>
                                            <td className="text-muted-foreground p-3">{prompt.city ?? '—'}</td>
                                            <td className="text-muted-foreground p-3">{prompt.vertical ?? '—'}</td>
                                            <td className="p-3">
                                                <Badge variant={prompt.is_active ? 'default' : 'secondary'}>
                                                    {prompt.is_active ? 'Active' : 'Paused'}
                                                </Badge>
                                            </td>
                                            {canManage && (
                                                <td className="p-3">
                                                    <div className="flex justify-end">
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="text-destructive"
                                                            onClick={() => removePrompt(prompt.id)}
                                                        >
                                                            Delete
                                                        </Button>
                                                    </div>
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
