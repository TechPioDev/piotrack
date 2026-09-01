import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Strategy', href: '/strategy' }];

type Plan = {
    id: number;
    name: string;
    summary: string | null;
    status: string;
    items_count: number;
    period_start: string | null;
    period_end: string | null;
};

type StrategyItem = {
    id: number;
    strategy_plan_id: number | null;
    type: string;
    title: string;
    findings: string | null;
    recommendation: string | null;
    priority: string;
    status: string;
    due_on: string | null;
    source_module: string | null;
};

type Kpi = {
    id: number;
    metric: string;
    target: number;
    actual: number;
    attainment: number;
    on_track: boolean;
    lower_is_better: boolean;
};

type Evidence = {
    signal: string;
    value: number | string;
    met: boolean;
};

type Stage = {
    stage: string;
    label: string;
    score: number;
    evidence: Evidence[];
};

type Methodology = {
    overall: number;
    stages: Stage[];
};

const itemStatuses = ['open', 'in_progress', 'complete'];
const priorities = ['low', 'medium', 'high'];
const planStatuses = ['draft', 'active', 'completed'];

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

const humanize = (value: string) => value.replace(/_/g, ' ');

function scoreVariant(score: number): 'default' | 'secondary' | 'destructive' {
    if (score >= 70) return 'default';
    if (score >= 40) return 'secondary';

    return 'destructive';
}

function NewPlanDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; summary: string; status: string; period_start: string; period_end: string }>({
        name: '',
        summary: '',
        status: 'draft',
        period_start: '',
        period_end: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('strategy.plans.store'), {
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
                <Button>New plan</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New strategy plan</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="plan_name">Name</Label>
                        <Input id="plan_name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="plan_summary">Summary</Label>
                        <textarea
                            id="plan_summary"
                            className={textareaClass}
                            value={form.data.summary}
                            onChange={(e) => form.setData('summary', e.target.value)}
                        />
                        <InputError message={form.errors.summary} />
                    </div>
                    <div className="grid grid-cols-3 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="plan_status">Status</Label>
                            <Select value={form.data.status} onValueChange={(v) => form.setData('status', v)}>
                                <SelectTrigger id="plan_status">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {planStatuses.map((status) => (
                                        <SelectItem key={status} value={status}>
                                            {status}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.status} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="plan_start">Period start</Label>
                            <Input
                                id="plan_start"
                                type="date"
                                value={form.data.period_start}
                                onChange={(e) => form.setData('period_start', e.target.value)}
                            />
                            <InputError message={form.errors.period_start} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="plan_end">Period end</Label>
                            <Input
                                id="plan_end"
                                type="date"
                                value={form.data.period_end}
                                onChange={(e) => form.setData('period_end', e.target.value)}
                            />
                            <InputError message={form.errors.period_end} />
                        </div>
                    </div>
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

function NewItemDialog({ plans, types }: { plans: Plan[]; types: string[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{
        strategy_plan_id: string;
        type: string;
        title: string;
        findings: string;
        recommendation: string;
        priority: string;
        due_on: string;
        source_module: string;
    }>({
        strategy_plan_id: '',
        type: types[0] ?? 'assessment',
        title: '',
        findings: '',
        recommendation: '',
        priority: 'medium',
        due_on: '',
        source_module: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            strategy_plan_id: data.strategy_plan_id === '' ? null : Number(data.strategy_plan_id),
            source_module: data.source_module === '' ? null : data.source_module,
        }));
        form.post(route('strategy.items.store'), {
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
                <Button variant="outline">New item</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New strategy item</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="item_type">Type</Label>
                            <Select value={form.data.type} onValueChange={(v) => form.setData('type', v)}>
                                <SelectTrigger id="item_type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {types.map((type) => (
                                        <SelectItem key={type} value={type}>
                                            {type}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.type} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="item_plan">Plan</Label>
                            <Select value={form.data.strategy_plan_id} onValueChange={(v) => form.setData('strategy_plan_id', v)}>
                                <SelectTrigger id="item_plan">
                                    <SelectValue placeholder="Unassigned" />
                                </SelectTrigger>
                                <SelectContent>
                                    {plans.map((plan) => (
                                        <SelectItem key={plan.id} value={String(plan.id)}>
                                            {plan.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.strategy_plan_id} />
                        </div>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="item_title">Title</Label>
                        <Input id="item_title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                        <InputError message={form.errors.title} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="item_findings">Findings</Label>
                        <textarea
                            id="item_findings"
                            className={textareaClass}
                            value={form.data.findings}
                            onChange={(e) => form.setData('findings', e.target.value)}
                        />
                        <InputError message={form.errors.findings} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="item_recommendation">Recommendation</Label>
                        <textarea
                            id="item_recommendation"
                            className={textareaClass}
                            value={form.data.recommendation}
                            onChange={(e) => form.setData('recommendation', e.target.value)}
                        />
                        <InputError message={form.errors.recommendation} />
                    </div>
                    <div className="grid grid-cols-3 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="item_priority">Priority</Label>
                            <Select value={form.data.priority} onValueChange={(v) => form.setData('priority', v)}>
                                <SelectTrigger id="item_priority">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {priorities.map((priority) => (
                                        <SelectItem key={priority} value={priority}>
                                            {priority}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.priority} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="item_due">Due</Label>
                            <Input id="item_due" type="date" value={form.data.due_on} onChange={(e) => form.setData('due_on', e.target.value)} />
                            <InputError message={form.errors.due_on} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="item_module">Source module</Label>
                            <Input
                                id="item_module"
                                value={form.data.source_module}
                                onChange={(e) => form.setData('source_module', e.target.value)}
                                placeholder="seo"
                            />
                            <InputError message={form.errors.source_module} />
                        </div>
                    </div>
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

function NewTargetDialog({ metrics }: { metrics: string[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ metric: string; target_value: string; period_start: string; period_end: string }>({
        metric: metrics[0] ?? 'leads',
        target_value: '',
        period_start: '',
        period_end: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, target_value: Number(data.target_value) }));
        form.post(route('strategy.targets.store'), {
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
                <Button size="sm" variant="outline">
                    Set target
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Set a KPI target</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="target_metric">Metric</Label>
                            <Select value={form.data.metric} onValueChange={(v) => form.setData('metric', v)}>
                                <SelectTrigger id="target_metric">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {metrics.map((metric) => (
                                        <SelectItem key={metric} value={metric}>
                                            {metric}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.metric} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="target_value">Target</Label>
                            <Input
                                id="target_value"
                                type="number"
                                min="0"
                                value={form.data.target_value}
                                onChange={(e) => form.setData('target_value', e.target.value)}
                            />
                            <p className="text-muted-foreground text-xs">
                                {form.data.metric === 'cpl'
                                    ? 'Cost per lead is a ceiling — lower is better.'
                                    : 'Actuals are read from live analytics.'}
                            </p>
                            <InputError message={form.errors.target_value} />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="target_start">Period start</Label>
                            <Input
                                id="target_start"
                                type="date"
                                value={form.data.period_start}
                                onChange={(e) => form.setData('period_start', e.target.value)}
                            />
                            <InputError message={form.errors.period_start} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="target_end">Period end</Label>
                            <Input
                                id="target_end"
                                type="date"
                                value={form.data.period_end}
                                onChange={(e) => form.setData('period_end', e.target.value)}
                            />
                            <InputError message={form.errors.period_end} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Set target
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * The methodology panel. The score on its own is an assertion; the evidence
 * underneath is what makes it checkable, so every signal is listed with the
 * value it was read from and whether it was met.
 */
function MethodologyPanel({ methodology }: { methodology: Methodology }) {
    return (
        <div className="space-y-3">
            <Card>
                <CardContent className="flex flex-wrap items-center gap-6 p-6">
                    <div>
                        <p className="text-muted-foreground text-sm">Methodology score</p>
                        <p className="text-6xl font-semibold tracking-tight">{methodology.overall}</p>
                        <p className="text-muted-foreground text-sm">out of 100</p>
                    </div>
                    <div className="min-w-48 flex-1 space-y-2">
                        <div className="bg-muted h-3 overflow-hidden rounded-full">
                            <div className="bg-primary h-full rounded-full" style={{ width: `${methodology.overall}%` }} />
                        </div>
                        <p className="text-muted-foreground text-sm">
                            The mean of the five stages below. Each stage is scored from signals read out of the modules that implement it — never
                            from a self-assessment — so a stage with no data scores zero and says which signals are missing.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <div className="grid gap-3 lg:grid-cols-2">
                {methodology.stages.map((stage) => (
                    <Card key={stage.stage}>
                        <CardContent className="space-y-3 p-4">
                            <div className="flex items-center justify-between gap-3">
                                <p className="font-medium">{stage.label}</p>
                                <Badge variant={scoreVariant(stage.score)}>{stage.score}</Badge>
                            </div>
                            <div className="bg-muted h-2 overflow-hidden rounded-full">
                                <div className="bg-primary h-full rounded-full" style={{ width: `${stage.score}%` }} />
                            </div>
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground">
                                        <tr>
                                            <th className="p-3 font-medium">Evidence</th>
                                            <th className="p-3 text-center font-medium">Value</th>
                                            <th className="p-3 text-center font-medium">Met</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {stage.evidence.map((evidence) => (
                                            <tr key={evidence.signal} className="hover:bg-muted/40">
                                                <td className="p-3">{evidence.signal}</td>
                                                <td className="text-muted-foreground p-3 text-center">{evidence.value}</td>
                                                <td className="p-3 text-center">
                                                    {evidence.met ? (
                                                        <Check className="text-primary mx-auto size-4" aria-label="Met" />
                                                    ) : (
                                                        <X className="text-muted-foreground mx-auto size-4" aria-label="Not met" />
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </div>
    );
}

function KpiTable({ kpis, canManage, metrics }: { kpis: Kpi[]; canManage: boolean; metrics: string[] }) {
    const removeTarget = (id: number) => router.delete(route('strategy.targets.destroy', id), { preserveScroll: true });

    return (
        <div>
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-sm font-medium">KPI targets vs actuals</h3>
                {canManage && <NewTargetDialog metrics={metrics} />}
            </div>
            {kpis.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    No KPI targets set. Actuals come from analytics once a target exists to measure against.
                </p>
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="p-3 font-medium">Metric</th>
                                <th className="p-3 text-center font-medium">Target</th>
                                <th className="p-3 text-center font-medium">Actual</th>
                                <th className="p-3 text-center font-medium">Attainment</th>
                                <th className="p-3 font-medium">Direction</th>
                                <th className="p-3 font-medium">On track</th>
                                {canManage && <th className="p-3 text-right font-medium">Actions</th>}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {kpis.map((kpi) => (
                                <tr key={kpi.id} className="hover:bg-muted/40">
                                    <td className="p-3 font-medium">{kpi.metric}</td>
                                    <td className="p-3 text-center">{kpi.target}</td>
                                    <td className="p-3 text-center">{kpi.actual}</td>
                                    <td className="p-3 text-center">{kpi.attainment}%</td>
                                    <td className="text-muted-foreground p-3">{kpi.lower_is_better ? 'Lower is better' : 'Higher is better'}</td>
                                    <td className="p-3">
                                        {kpi.on_track ? <Badge>On track</Badge> : <Badge variant="destructive">Off target</Badge>}
                                    </td>
                                    {canManage && (
                                        <td className="p-3">
                                            <div className="flex justify-end">
                                                <Button size="sm" variant="ghost" className="text-destructive" onClick={() => removeTarget(kpi.id)}>
                                                    Remove
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
    );
}

type Insights = {
    keyword_opportunities: {
        intent_mix: Record<string, number>;
        opportunities: {
            phrase: string;
            search_volume: number | null;
            difficulty: number | null;
            position: number | null;
            intent: string;
            location: string | null;
        }[];
    };
    competitor_landscape: {
        keywords_measured: number;
        keywords_leading: number;
        ai_share: { checks: number; share: number };
    };
    geo_markets: { market: string; keywords: number; avg_position: number | null; opportunities: number }[];
    vertical_performance: { industry: string; deals: number; won: number; win_rate: number | null; won_mrr: number }[];
    conversion_audit: {
        visitors: number;
        identified: number;
        form_submissions: number;
        bookings: number;
        won_deals: number;
        identification_rate: number | null;
        visitor_to_booking_rate: number | null;
    };
    crm_hygiene: Record<string, number>;
    funnel_audit: { funnel: string; stages: number; stages_without_assets: number }[];
    ppc_audit: { campaign: string; platform: string; spend: number; conversions: number; revenue: number; flags: string[] }[];
    lead_gen_gaps: { sources: { source: string; visitors: number; identified: number }[]; pages_without_capture: string[] };
    lead_channels: Record<string, { visitors: number; leads: number }>;
    revenue_model: {
        insufficient_data: boolean;
        closed_deals: number;
        open_deals: number;
        open_mrr: number;
        win_rate: number | null;
        projected_mrr: number | null;
    };
    icp_profile: {
        insufficient_data: boolean;
        won_deals: number;
        top_industries: Record<string, number>;
        top_sources: Record<string, number>;
        median_mrr: number | null;
    };
    seo_audit_summary: { audits: number; avg_score: number | null; total_issues: number; worst_url: string | null };
    assessment: { issues: Record<string, number>; strengths: Record<string, number> };
};

const centsToMoney = (cents: number) => `$${(cents / 100).toLocaleString('en-US', { minimumFractionDigits: 0 })}`;
const label = (key: string) => key.replace(/_/g, ' ');

function InsightCard({ title, note, children }: { title: string; note?: string; children: React.ReactNode }) {
    return (
        <div className="rounded-lg border p-4">
            <h4 className="text-sm font-medium">{title}</h4>
            {note && <p className="text-muted-foreground mt-0.5 text-xs">{note}</p>}
            <div className="mt-3 space-y-1 text-sm">{children}</div>
        </div>
    );
}

function StatRow({ name, value }: { name: string; value: React.ReactNode }) {
    return (
        <div className="flex items-baseline justify-between gap-2">
            <span className="text-muted-foreground capitalize">{name}</span>
            <span className="font-medium tabular-nums">{value}</span>
        </div>
    );
}

function InsightsSection({ insights }: { insights: Insights }) {
    const conversion = insights.conversion_audit;
    const model = insights.revenue_model;
    const icp = insights.icp_profile;

    return (
        <div>
            <h3 className="mb-1 text-sm font-medium">Computed insights</h3>
            <p className="text-muted-foreground mb-3 text-sm">
                Every number below is computed from this organization&apos;s own records — nothing estimated, nothing imported. Blocks with too little
                history say so.
            </p>
            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <InsightCard title="Marketing assessment" note="Issue and strength counts aggregated from the audits below.">
                    {Object.entries(insights.assessment.issues).map(([key, value]) => (
                        <StatRow key={key} name={label(key)} value={<span className={value > 0 ? 'text-destructive' : ''}>{value}</span>} />
                    ))}
                    <div className="border-t pt-1" />
                    {Object.entries(insights.assessment.strengths).map(([key, value]) => (
                        <StatRow key={key} name={label(key)} value={value} />
                    ))}
                </InsightCard>

                <InsightCard title="Keyword opportunities" note="Tracked demand you do not rank for yet (top 10).">
                    {insights.keyword_opportunities.opportunities.length === 0 ? (
                        <p className="text-muted-foreground">Every tracked keyword ranks — add more keywords to hunt new demand.</p>
                    ) : (
                        insights.keyword_opportunities.opportunities
                            .slice(0, 6)
                            .map((opportunity) => (
                                <StatRow
                                    key={opportunity.phrase}
                                    name={opportunity.phrase + (opportunity.location ? ` · ${opportunity.location}` : '')}
                                    value={`vol ${opportunity.search_volume ?? '—'} · pos ${opportunity.position ?? '—'}`}
                                />
                            ))
                    )}
                </InsightCard>

                <InsightCard title="Competitive landscape" note="Head-to-head rankings and AI recommendation share.">
                    <StatRow name="keywords measured" value={insights.competitor_landscape.keywords_measured} />
                    <StatRow name="keywords leading" value={insights.competitor_landscape.keywords_leading} />
                    <StatRow name="AI checks recorded" value={insights.competitor_landscape.ai_share.checks} />
                    <StatRow name="AI recommendation share" value={`${Math.round(insights.competitor_landscape.ai_share.share * 100)}%`} />
                </InsightCard>

                <InsightCard title="Website conversion audit" note="The real capture chain, end to end.">
                    <StatRow name="visitors" value={conversion.visitors} />
                    <StatRow name="identified" value={`${conversion.identified} (${conversion.identification_rate ?? '—'}%)`} />
                    <StatRow name="form submissions" value={conversion.form_submissions} />
                    <StatRow name="bookings" value={conversion.bookings} />
                    <StatRow name="won deals" value={conversion.won_deals} />
                </InsightCard>

                <InsightCard title="Revenue opportunity model" note="Open pipeline × your own historical win rate.">
                    {model.insufficient_data ? (
                        <p className="text-muted-foreground">
                            Only {model.closed_deals} closed deals — projections start at 5 so the win rate means something.
                        </p>
                    ) : (
                        <>
                            <StatRow name="win rate" value={`${model.win_rate}%`} />
                            <StatRow name="open pipeline MRR" value={centsToMoney(model.open_mrr)} />
                            <StatRow name="projected MRR" value={centsToMoney(model.projected_mrr ?? 0)} />
                        </>
                    )}
                </InsightCard>

                <InsightCard title="Data-driven ICP" note="What your closed-won history says your ideal customer looks like.">
                    {icp.insufficient_data ? (
                        <p className="text-muted-foreground">No won deals yet — the ICP profile builds itself from wins.</p>
                    ) : (
                        <>
                            {Object.entries(icp.top_industries).map(([industry, count]) => (
                                <StatRow key={industry} name={industry} value={`${count} won`} />
                            ))}
                            <StatRow name="median won MRR" value={centsToMoney(icp.median_mrr ?? 0)} />
                        </>
                    )}
                </InsightCard>

                <InsightCard title="CRM hygiene" note="What a CRM audit would flag today.">
                    {Object.entries(insights.crm_hygiene).map(([key, value]) => (
                        <StatRow key={key} name={label(key)} value={<span className={value > 0 ? 'text-destructive' : ''}>{value}</span>} />
                    ))}
                </InsightCard>

                <InsightCard title="Geographic markets" note="Per-market keyword footprint.">
                    {insights.geo_markets.length === 0 ? (
                        <p className="text-muted-foreground">No geo-targeted keywords yet — add locations on SEO → Keywords.</p>
                    ) : (
                        insights.geo_markets
                            .slice(0, 6)
                            .map((market) => (
                                <StatRow
                                    key={market.market}
                                    name={market.market}
                                    value={`${market.keywords} kw · avg ${market.avg_position ?? '—'} · ${market.opportunities} opp`}
                                />
                            ))
                    )}
                </InsightCard>

                <InsightCard title="Vertical performance" note="Win rate and won MRR by industry.">
                    {insights.vertical_performance.length === 0 ? (
                        <p className="text-muted-foreground">No deals yet.</p>
                    ) : (
                        insights.vertical_performance
                            .slice(0, 6)
                            .map((vertical) => (
                                <StatRow
                                    key={vertical.industry}
                                    name={vertical.industry}
                                    value={`${vertical.won}/${vertical.deals} won · ${centsToMoney(vertical.won_mrr)}`}
                                />
                            ))
                    )}
                </InsightCard>

                <InsightCard title="Funnel & PPC audits" note="Stages without assets; campaigns spending without converting.">
                    {insights.funnel_audit.map((funnel) => (
                        <StatRow
                            key={funnel.funnel}
                            name={funnel.funnel}
                            value={
                                <span className={funnel.stages_without_assets > 0 ? 'text-destructive' : ''}>
                                    {funnel.stages_without_assets}/{funnel.stages} empty stages
                                </span>
                            }
                        />
                    ))}
                    {insights.ppc_audit
                        .filter((campaign) => campaign.flags.length > 0)
                        .map((campaign) => (
                            <StatRow
                                key={campaign.campaign}
                                name={campaign.campaign}
                                value={<span className="text-destructive">{campaign.flags.map(label).join(', ')}</span>}
                            />
                        ))}
                    {insights.funnel_audit.length === 0 && insights.ppc_audit.length === 0 && (
                        <p className="text-muted-foreground">No funnels or ad campaigns yet.</p>
                    )}
                </InsightCard>

                <InsightCard title="Lead-gen gaps" note="Traffic that never becomes a lead.">
                    {insights.lead_gen_gaps.sources.length === 0 && insights.lead_gen_gaps.pages_without_capture.length === 0 ? (
                        <p className="text-muted-foreground">No unconverted sources or capture-less pages detected.</p>
                    ) : (
                        <>
                            {insights.lead_gen_gaps.sources.map((source) => (
                                <StatRow key={source.source} name={source.source} value={`${source.visitors} visitors, 0 identified`} />
                            ))}
                            {insights.lead_gen_gaps.pages_without_capture.map((page) => (
                                <StatRow key={page} name={page} value="no capture form" />
                            ))}
                        </>
                    )}
                </InsightCard>

                <InsightCard title="Leads by channel" note="Identified leads by derived first-touch channel.">
                    {Object.keys(insights.lead_channels).length === 0 ? (
                        <p className="text-muted-foreground">No visitor traffic recorded yet.</p>
                    ) : (
                        Object.entries(insights.lead_channels).map(([channel, row]) => (
                            <StatRow key={channel} name={channel} value={`${row.leads} leads / ${row.visitors} visitors`} />
                        ))
                    )}
                </InsightCard>

                <InsightCard title="SEO audit summary" note="Roll-up of stored technical audits.">
                    <StatRow name="audits run" value={insights.seo_audit_summary.audits} />
                    <StatRow name="average score" value={insights.seo_audit_summary.avg_score ?? '—'} />
                    <StatRow name="total issues" value={insights.seo_audit_summary.total_issues} />
                    <StatRow name="worst page" value={insights.seo_audit_summary.worst_url ?? '—'} />
                </InsightCard>
            </div>
        </div>
    );
}

export default function StrategyDashboard({
    plans,
    items,
    kpis,
    methodology,
    types,
    metrics,
    insights,
}: {
    plans: Plan[];
    items: StrategyItem[];
    kpis: Kpi[];
    methodology: Methodology;
    types: string[];
    metrics: string[];
    insights: Insights;
}) {
    const { can } = usePermissions();
    const canManage = can('strategy.manage');

    const setItemStatus = (item: StrategyItem, status: string) =>
        router.patch(route('strategy.items.update', item.id), { status }, { preserveScroll: true });
    const removeItem = (id: number) => router.delete(route('strategy.items.destroy', id), { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Strategy" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <PageHeader title="Strategy" description="Plans, the consulting work under them, KPI attainment and the five-P methodology." />
                    {canManage && (
                        <div className="flex flex-wrap gap-2">
                            <NewItemDialog plans={plans} types={types} />
                            <NewPlanDialog />
                        </div>
                    )}
                </div>

                <MethodologyPanel methodology={methodology} />

                <InsightsSection insights={insights} />

                <KpiTable kpis={kpis} canManage={canManage} metrics={metrics} />

                <div>
                    <h3 className="mb-2 text-sm font-medium">Plans</h3>
                    {plans.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No strategy plans yet.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Plan</th>
                                        <th className="p-3 font-medium">Status</th>
                                        <th className="p-3 text-center font-medium">Items</th>
                                        <th className="p-3 font-medium">Period</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {plans.map((plan) => (
                                        <tr key={plan.id} className="hover:bg-muted/40 align-top">
                                            <td className="max-w-96 p-3">
                                                <p className="font-medium">{plan.name}</p>
                                                {plan.summary && <p className="text-muted-foreground">{plan.summary}</p>}
                                            </td>
                                            <td className="p-3">
                                                <Badge variant="outline">{plan.status}</Badge>
                                            </td>
                                            <td className="p-3 text-center">{plan.items_count}</td>
                                            <td className="text-muted-foreground p-3">
                                                {plan.period_start ?? '—'} → {plan.period_end ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <div className="space-y-4">
                    <h3 className="text-sm font-medium">Strategy items</h3>
                    {types.map((type) => {
                        const typeItems = items.filter((item) => item.type === type);

                        return (
                            <div key={type}>
                                <h4 className="text-muted-foreground mb-2 text-sm">
                                    {humanize(type)} ({typeItems.length})
                                </h4>
                                {typeItems.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">Nothing recorded under {humanize(type)}.</p>
                                ) : (
                                    <div className="overflow-x-auto rounded-lg border">
                                        <table className="w-full text-left text-sm">
                                            <thead className="bg-muted/50 text-muted-foreground">
                                                <tr>
                                                    <th className="p-3 font-medium">Item</th>
                                                    <th className="p-3 font-medium">Plan</th>
                                                    <th className="p-3 font-medium">Priority</th>
                                                    <th className="p-3 font-medium">Due</th>
                                                    <th className="p-3 font-medium">Status</th>
                                                    {canManage && <th className="p-3 text-right font-medium">Actions</th>}
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y">
                                                {typeItems.map((item) => (
                                                    <tr key={item.id} className="hover:bg-muted/40 align-top">
                                                        <td className="max-w-96 p-3">
                                                            <div className="flex flex-wrap items-center gap-2">
                                                                <span className="font-medium">{item.title}</span>
                                                                {item.source_module !== null && (
                                                                    <Badge variant="secondary" title="The module that computes this work">
                                                                        {item.source_module}
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                            {item.findings && <p className="text-muted-foreground">{item.findings}</p>}
                                                            {item.recommendation && (
                                                                <p className="mt-1">
                                                                    <span className="font-medium">Recommendation: </span>
                                                                    <span className="text-muted-foreground">{item.recommendation}</span>
                                                                </p>
                                                            )}
                                                        </td>
                                                        <td className="text-muted-foreground p-3">
                                                            {plans.find((plan) => plan.id === item.strategy_plan_id)?.name ?? 'Unassigned'}
                                                        </td>
                                                        <td className="p-3">
                                                            <Badge variant={item.priority === 'high' ? 'destructive' : 'outline'}>
                                                                {item.priority}
                                                            </Badge>
                                                        </td>
                                                        <td className="text-muted-foreground p-3">{item.due_on ?? '—'}</td>
                                                        <td className="p-3">
                                                            {canManage ? (
                                                                <Select value={item.status} onValueChange={(v) => setItemStatus(item, v)}>
                                                                    <SelectTrigger className="w-36" aria-label={`Status of ${item.title}`}>
                                                                        <SelectValue />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        {itemStatuses.map((status) => (
                                                                            <SelectItem key={status} value={status}>
                                                                                {humanize(status)}
                                                                            </SelectItem>
                                                                        ))}
                                                                    </SelectContent>
                                                                </Select>
                                                            ) : (
                                                                <Badge variant="outline">{humanize(item.status)}</Badge>
                                                            )}
                                                        </td>
                                                        {canManage && (
                                                            <td className="p-3">
                                                                <div className="flex justify-end">
                                                                    <Button
                                                                        size="sm"
                                                                        variant="ghost"
                                                                        className="text-destructive"
                                                                        onClick={() => removeItem(item.id)}
                                                                    >
                                                                        Remove
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
                        );
                    })}
                </div>
            </div>
        </AppLayout>
    );
}
