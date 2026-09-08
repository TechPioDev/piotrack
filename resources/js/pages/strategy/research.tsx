import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Strategy', href: '/strategy' },
    { title: 'Research', href: '/strategy/research' },
];

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-16 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

type TamDefaults = {
    avg_mrr: number | null;
    avg_mrr_source: string;
    win_rate_pct: number | null;
    win_rate_source: string;
    won_deals: number;
    closed_deals: number;
};

type Persona = {
    id: number;
    name: string;
    role_title: string | null;
    seniority: string | null;
    goals: string | null;
    pains: string | null;
    channels: string | null;
    objections: string | null;
};

type PersonaEvidence = {
    buying_roles: Record<string, number>;
    won_titles: Record<string, number>;
    questions: string[];
    contacts_sampled: number;
    won_deals: number;
};

type PainPoints = {
    themes: { theme: string; total: number; by_source: Record<string, number>; quotes: { text: string; source: string }[] }[];
    sources: Record<string, number>;
    total_signals: number;
};

type Journey = {
    stages: {
        stage: string;
        funnel_stage: string | null;
        metrics: Record<string, number>;
        published_content: number;
        conversion: { label: string; rate: number | null };
    }[];
    avg_days_to_close: number | null;
    capture_pages: number;
};

type ServiceLines = {
    lines: {
        id: number;
        name: string;
        category: string | null;
        deals: number;
        won: number;
        win_rate: number | null;
        won_mrr: number;
        open_pipeline: number;
        pages: number;
        campaigns: number;
        sales_assets: number;
        recommendations: string[];
    }[];
    unbound_deals: number;
};

type Positioning = {
    angles: { angle: string; evidence: string; strength: string }[];
    inputs: Record<string, number>;
};

type Messaging = {
    elements: { source: string; label: string; words: string[]; carried_by: Record<string, number>; total: number }[];
    assets_without_messaging: { type: string; title: string }[];
    asset_counts: Record<string, number>;
};

function TamDialog({ defaults }: { defaults: TamDefaults }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        title: '',
        market_businesses: '',
        addressable_pct: '100',
        reachable_pct: '10',
        avg_mrr: '',
        win_rate_pct: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('strategy.research.tam'), {
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
                <Button>Run market sizing</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>TAM / SAM / SOM</DialogTitle>
                <p className="text-muted-foreground text-sm">
                    The market count is yours to enter — the platform never invents market data. Average MRR and win rate default to your own won-deal
                    records when left blank; every figure is computed server-side and saved as an auditable research item.
                </p>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="tam-title">Title</Label>
                        <Input
                            id="tam-title"
                            value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)}
                            placeholder="e.g. Philadelphia metro TAM 2026"
                        />
                        <InputError message={form.errors.title} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="tam-market">Businesses in the target market</Label>
                        <Input
                            id="tam-market"
                            type="number"
                            min="1"
                            value={form.data.market_businesses}
                            onChange={(e) => form.setData('market_businesses', e.target.value)}
                        />
                        <InputError message={form.errors.market_businesses} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="tam-addressable">Addressable %</Label>
                            <Input
                                id="tam-addressable"
                                type="number"
                                min="0"
                                max="100"
                                value={form.data.addressable_pct}
                                onChange={(e) => form.setData('addressable_pct', e.target.value)}
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="tam-reachable">Reachable %</Label>
                            <Input
                                id="tam-reachable"
                                type="number"
                                min="0"
                                max="100"
                                value={form.data.reachable_pct}
                                onChange={(e) => form.setData('reachable_pct', e.target.value)}
                            />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="tam-mrr">Avg MRR ($)</Label>
                            <Input
                                id="tam-mrr"
                                type="number"
                                min="0"
                                value={form.data.avg_mrr}
                                onChange={(e) => form.setData('avg_mrr', e.target.value)}
                                placeholder={
                                    defaults.avg_mrr !== null ? `${(defaults.avg_mrr / 100).toFixed(0)} from your won deals` : 'no won deals yet'
                                }
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="tam-rate">Win rate %</Label>
                            <Input
                                id="tam-rate"
                                type="number"
                                min="0"
                                max="100"
                                value={form.data.win_rate_pct}
                                onChange={(e) => form.setData('win_rate_pct', e.target.value)}
                                placeholder={
                                    defaults.win_rate_pct !== null ? `${defaults.win_rate_pct} from your closed deals` : 'not enough closed deals'
                                }
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Compute &amp; save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function PersonaDialog({ seniorities }: { seniorities: string[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '', role_title: '', seniority: '', goals: '', pains: '', channels: '', objections: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('strategy.research.personas.store'), {
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
                <Button variant="outline">New persona</Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <DialogTitle>New buyer persona</DialogTitle>
                <p className="text-muted-foreground text-sm">Write it against the evidence panel — who actually buys and what they actually ask.</p>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="p-name">Name</Label>
                        <Input
                            id="p-name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="e.g. Operations Olivia"
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="p-title">Role title</Label>
                            <Input id="p-title" value={form.data.role_title} onChange={(e) => form.setData('role_title', e.target.value)} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="p-seniority">Seniority</Label>
                            <Select value={form.data.seniority} onValueChange={(v) => form.setData('seniority', v)}>
                                <SelectTrigger id="p-seniority">
                                    <SelectValue placeholder="Level" />
                                </SelectTrigger>
                                <SelectContent>
                                    {seniorities.map((s) => (
                                        <SelectItem key={s} value={s}>
                                            {s}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    {(
                        [
                            ['goals', 'Goals'],
                            ['pains', 'Pains'],
                            ['channels', 'Where they spend time'],
                            ['objections', 'Objections'],
                        ] as const
                    ).map(([field, label]) => (
                        <div key={field} className="grid gap-1">
                            <Label htmlFor={`p-${field}`}>{label}</Label>
                            <textarea
                                id={`p-${field}`}
                                rows={2}
                                className={textareaClass}
                                value={form.data[field]}
                                onChange={(e) => form.setData(field, e.target.value)}
                            />
                        </div>
                    ))}
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Create
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Research({
    tam_defaults,
    personas,
    persona_evidence,
    seniorities,
    pain_points,
    journey,
    service_lines,
    positioning,
    messaging,
}: {
    tam_defaults: TamDefaults;
    personas: Persona[];
    persona_evidence: PersonaEvidence;
    seniorities: string[];
    pain_points: PainPoints;
    journey: Journey;
    service_lines: ServiceLines;
    positioning: Positioning;
    messaging: Messaging;
}) {
    const { can } = usePermissions();
    const manage = can('strategy.manage');

    const sourceLabels: Record<string, string> = { chat: 'chat', review: 'negative review', social: 'social', ticket: 'ticket' };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Research" />
            <div className="space-y-6 p-4">
                <PageHeader
                    title="Research"
                    description="Market sizing, personas, pain points, the measured journey and positioning — computed from your own records; the narrative stays yours."
                    actions={manage ? <TamDialog defaults={tam_defaults} /> : undefined}
                />

                {/* STRAT-005: derived economics behind the calculator */}
                <Card>
                    <CardHeader>
                        <CardTitle>Market sizing inputs on record</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-3">
                        <div>
                            <p className="text-muted-foreground text-xs uppercase">Median won MRR</p>
                            <p className="text-lg font-semibold tabular-nums">
                                {tam_defaults.avg_mrr !== null ? formatMoney(tam_defaults.avg_mrr) : '—'}
                            </p>
                            <p className="text-muted-foreground text-xs">
                                {tam_defaults.avg_mrr !== null ? `derived from ${tam_defaults.won_deals} won deals` : 'no won deals yet'}
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-xs uppercase">Real win rate</p>
                            <p className="text-lg font-semibold tabular-nums">
                                {tam_defaults.win_rate_pct !== null ? `${tam_defaults.win_rate_pct}%` : '—'}
                            </p>
                            <p className="text-muted-foreground text-xs">
                                {tam_defaults.win_rate_pct !== null
                                    ? `derived from ${tam_defaults.closed_deals} closed deals`
                                    : 'needs 5+ closed deals'}
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-xs uppercase">Saved analyses</p>
                            <p className="text-muted-foreground text-sm">
                                Each run is stored as a research item on the Strategy workspace with every input, its provenance, and the math.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                {/* STRAT-007: personas beside their evidence */}
                <section className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Buyer personas</h2>
                        {manage && <PersonaDialog seniorities={seniorities} />}
                    </div>
                    <div className="grid gap-3 lg:grid-cols-3">
                        <Card className="lg:col-span-1">
                            <CardHeader>
                                <CardTitle>Evidence from your records</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <div>
                                    <p className="text-muted-foreground text-xs uppercase">
                                        Buying roles ({persona_evidence.contacts_sampled} contacts)
                                    </p>
                                    {Object.keys(persona_evidence.buying_roles).length === 0 && (
                                        <p className="text-muted-foreground text-xs">No buying roles recorded yet.</p>
                                    )}
                                    {Object.entries(persona_evidence.buying_roles).map(([role, count]) => (
                                        <p key={role} className="tabular-nums">
                                            {role} · {count}
                                        </p>
                                    ))}
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-xs uppercase">Titles at won-deal companies</p>
                                    {Object.keys(persona_evidence.won_titles).length === 0 && (
                                        <p className="text-muted-foreground text-xs">No titled contacts at won companies yet.</p>
                                    )}
                                    {Object.entries(persona_evidence.won_titles).map(([title, count]) => (
                                        <p key={title} className="tabular-nums">
                                            {title} · {count}
                                        </p>
                                    ))}
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-xs uppercase">What visitors actually ask</p>
                                    {persona_evidence.questions.length === 0 && (
                                        <p className="text-muted-foreground text-xs">No visitor questions yet.</p>
                                    )}
                                    {persona_evidence.questions.map((q) => (
                                        <p key={q} className="text-muted-foreground">
                                            “{q}”
                                        </p>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                        <div className="grid gap-3 sm:grid-cols-2 lg:col-span-2">
                            {personas.map((persona) => (
                                <Card key={persona.id}>
                                    <CardHeader className="pb-2">
                                        <div className="flex items-start justify-between">
                                            <CardTitle>{persona.name}</CardTitle>
                                            {manage && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        router.delete(route('strategy.research.personas.destroy', persona.id), {
                                                            preserveScroll: true,
                                                        })
                                                    }
                                                >
                                                    Remove
                                                </Button>
                                            )}
                                        </div>
                                        <p className="text-muted-foreground text-sm">
                                            {persona.role_title ?? '—'}
                                            {persona.seniority ? ` · ${persona.seniority}` : ''}
                                        </p>
                                    </CardHeader>
                                    <CardContent className="space-y-1 text-sm">
                                        {persona.goals && (
                                            <p>
                                                <span className="font-medium">Goals:</span> {persona.goals}
                                            </p>
                                        )}
                                        {persona.pains && (
                                            <p>
                                                <span className="font-medium">Pains:</span> {persona.pains}
                                            </p>
                                        )}
                                        {persona.channels && (
                                            <p>
                                                <span className="font-medium">Channels:</span> {persona.channels}
                                            </p>
                                        )}
                                        {persona.objections && (
                                            <p>
                                                <span className="font-medium">Objections:</span> {persona.objections}
                                            </p>
                                        )}
                                    </CardContent>
                                </Card>
                            ))}
                            {personas.length === 0 && (
                                <p className="text-muted-foreground text-sm">No personas yet — create the first one against the evidence panel.</p>
                            )}
                        </div>
                    </div>
                </section>

                {/* STRAT-008 */}
                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">Pain-point research</h2>
                    <p className="text-muted-foreground text-sm">
                        Compiled from {pain_points.total_signals} first-party signals
                        {Object.entries(pain_points.sources).map(([source, count]) => ` · ${count} ${sourceLabels[source] ?? source}`)}. Themes are a
                        deterministic keyword scan — a transparent heuristic, not a claim of understanding.
                    </p>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {pain_points.themes.map((theme) => (
                            <Card key={theme.theme}>
                                <CardHeader className="pb-2">
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="text-base">{theme.theme}</CardTitle>
                                        <Badge variant={theme.total > 0 ? 'default' : 'outline'}>{theme.total}</Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-2 text-sm">
                                    {theme.quotes.map((quote, i) => (
                                        <p key={i} className="text-muted-foreground">
                                            “{quote.text}” <span className="text-xs">({sourceLabels[quote.source] ?? quote.source})</span>
                                        </p>
                                    ))}
                                    {theme.total === 0 && <p className="text-muted-foreground text-xs">No signal in this theme yet.</p>}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </section>

                {/* STRAT-009 */}
                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">Buyer journey, as measured</h2>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {journey.stages.map((stage) => (
                            <Card key={stage.stage}>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-base capitalize">{stage.stage}</CardTitle>
                                    {stage.funnel_stage && <p className="text-muted-foreground text-xs uppercase">{stage.funnel_stage} content</p>}
                                </CardHeader>
                                <CardContent className="space-y-1 text-sm">
                                    {Object.entries(stage.metrics).map(([key, value]) => (
                                        <p key={key} className="flex justify-between tabular-nums">
                                            <span className="text-muted-foreground">{key.replace(/_/g, ' ')}</span>
                                            <span>{value}</span>
                                        </p>
                                    ))}
                                    {stage.funnel_stage !== null && (
                                        <p className="flex justify-between tabular-nums">
                                            <span className="text-muted-foreground">published content</span>
                                            <span>{stage.published_content}</span>
                                        </p>
                                    )}
                                    <p className="text-muted-foreground border-t pt-1 text-xs">
                                        {stage.conversion.label}: {stage.conversion.rate !== null ? `${stage.conversion.rate}%` : 'no data yet'}
                                    </p>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                    <p className="text-muted-foreground text-sm">
                        Average days to close (won deals): {journey.avg_days_to_close ?? '—'} · Capture-ready landing pages: {journey.capture_pages}
                    </p>
                </section>

                {/* STRAT-014 */}
                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">Service-line opportunities</h2>
                    {service_lines.unbound_deals > 0 && (
                        <p className="text-muted-foreground text-sm">
                            {service_lines.unbound_deals} deals are not bound to a service line yet — bind them on the deal board to sharpen this
                            analysis.
                        </p>
                    )}
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="p-2">Service line</th>
                                    <th className="p-2 text-right">Deals</th>
                                    <th className="p-2 text-right">Won</th>
                                    <th className="p-2 text-right">Win rate</th>
                                    <th className="p-2 text-right">Won MRR</th>
                                    <th className="p-2 text-right">Open pipeline</th>
                                    <th className="p-2 text-right">Pages</th>
                                    <th className="p-2 text-right">Campaigns</th>
                                    <th className="p-2 text-right">Collateral</th>
                                </tr>
                            </thead>
                            <tbody>
                                {service_lines.lines.map((line) => (
                                    <tr key={line.id} className="border-t align-top">
                                        <td className="p-2">
                                            <p className="font-medium">{line.name}</p>
                                            {line.recommendations.map((rec, i) => (
                                                <p key={i} className="text-muted-foreground text-xs">
                                                    {rec}
                                                </p>
                                            ))}
                                        </td>
                                        <td className="p-2 text-right tabular-nums">{line.deals}</td>
                                        <td className="p-2 text-right tabular-nums">{line.won}</td>
                                        <td className="p-2 text-right tabular-nums">{line.win_rate !== null ? `${line.win_rate}%` : '—'}</td>
                                        <td className="p-2 text-right tabular-nums">{formatMoney(line.won_mrr)}</td>
                                        <td className="p-2 text-right tabular-nums">{formatMoney(line.open_pipeline)}</td>
                                        <td className="p-2 text-right tabular-nums">{line.pages}</td>
                                        <td className="p-2 text-right tabular-nums">{line.campaigns}</td>
                                        <td className="p-2 text-right tabular-nums">{line.sales_assets}</td>
                                    </tr>
                                ))}
                                {service_lines.lines.length === 0 && (
                                    <tr>
                                        <td colSpan={9} className="text-muted-foreground p-3 text-sm">
                                            No active service lines yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                {/* STRAT-015 */}
                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">Positioning research</h2>
                    <p className="text-muted-foreground text-sm">
                        Composed from {positioning.inputs.keywords_measured} measured keywords, {positioning.inputs.ai_checks} AI answer checks and{' '}
                        {positioning.inputs.competitors_snapshotted} competitor snapshots — every angle cites its numbers.
                    </p>
                    <div className="grid gap-3 sm:grid-cols-2">
                        {positioning.angles.map((angle, i) => (
                            <Card key={i}>
                                <CardContent className="flex items-start justify-between gap-3 pt-4">
                                    <div>
                                        <p className="font-medium">{angle.angle}</p>
                                        <p className="text-muted-foreground text-sm">{angle.evidence}</p>
                                    </div>
                                    <Badge variant={angle.strength === 'lead' ? 'default' : 'destructive'}>{angle.strength}</Badge>
                                </CardContent>
                            </Card>
                        ))}
                        {positioning.angles.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No measured positioning inputs yet — track keywords, competitors and AI visibility checks to build this panel.
                            </p>
                        )}
                    </div>
                </section>

                {/* STRAT-016 */}
                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">Messaging analysis</h2>
                    <p className="text-muted-foreground text-sm">
                        Checks whether {messaging.asset_counts.content} published pieces, {messaging.asset_counts.page} published pages and{' '}
                        {messaging.asset_counts.campaign} sent campaigns carry your recorded messaging — a significant-word presence check, stated as
                        the heuristic it is.
                    </p>
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="p-2">Message element</th>
                                    <th className="p-2">Source</th>
                                    <th className="p-2 text-right">Content</th>
                                    <th className="p-2 text-right">Pages</th>
                                    <th className="p-2 text-right">Campaigns</th>
                                    <th className="p-2 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {messaging.elements.map((element, i) => (
                                    <tr key={i} className="border-t">
                                        <td className="p-2">{element.label}</td>
                                        <td className="p-2">{element.source}</td>
                                        <td className="p-2 text-right tabular-nums">{element.carried_by.content}</td>
                                        <td className="p-2 text-right tabular-nums">{element.carried_by.page}</td>
                                        <td className="p-2 text-right tabular-nums">{element.carried_by.campaign}</td>
                                        <td className="p-2 text-right font-medium tabular-nums">{element.total}</td>
                                    </tr>
                                ))}
                                {messaging.elements.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="text-muted-foreground p-3 text-sm">
                                            No messaging on record yet — capture the brand USP, value proposition and differentiators on the Brand
                                            page, or per-vertical messaging on the taxonomy page.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {messaging.assets_without_messaging.length > 0 && (
                        <div>
                            <p className="text-sm font-medium">Published assets carrying none of your messaging</p>
                            {messaging.assets_without_messaging.map((asset, i) => (
                                <p key={i} className="text-muted-foreground text-sm">
                                    {asset.title} <span className="text-xs">({asset.type})</span>
                                </p>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
