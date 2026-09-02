import { BarList, type BarItem } from '@/components/charts/bar-list';
import { LineChart, type LinePoint } from '@/components/charts/line-chart';
import { OnboardingChecklist } from '@/components/onboarding-checklist';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, CalendarCheck, DollarSign, Flame, Handshake, MessagesSquare, PieChart, TrendingUp, UserPlus, Users } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

type Onboarding = { steps: { key: string; label: string; done: boolean; url: string }[]; complete: boolean };
type Compared = { value: number; previous: number; delta_pct: number | null };
type Kpis = {
    new_leads: Compared;
    meetings: Compared;
    deals_won: Compared;
    new_mrr: Compared;
    qualified_pipeline: number;
    sqls: number;
    arr: number;
};
type Recommendation = { area: string; score: number | null; action: string };
type GrowthScoreProp = { overall: number; recommendations: Recommendation[]; history: LinePoint[] };
type Attention = {
    alerts: { id: number; type: string; message: string; contact: string | null }[];
    waiting_chats: number;
    hot_leads: number;
};
type TopDeal = { id: number; name: string; stage: string | null; value: number };

/** Minor units (cents) to a compact dollar string: 5400000 -> $54,000. */
function money(minor: number): string {
    const dollars = Math.round(minor / 100);
    if (dollars >= 1000) {
        return `$${(dollars / 1000).toLocaleString('en-US', { maximumFractionDigits: 1 })}K`;
    }
    return `$${dollars.toLocaleString('en-US')}`;
}

const ZERO: Compared = { value: 0, previous: 0, delta_pct: null };

/** "+18%" against the previous 30 days; null previous means "new", not +∞. */
function delta(c: Compared): { value: string; direction: 'up' | 'down' | 'neutral' } | undefined {
    if (c.delta_pct === null) {
        return c.value > 0 ? { value: 'new', direction: 'neutral' } : undefined;
    }
    return {
        value: `${c.delta_pct > 0 ? '+' : ''}${c.delta_pct}%`,
        direction: c.delta_pct > 0 ? 'up' : c.delta_pct < 0 ? 'down' : 'neutral',
    };
}

function scoreBand(overall: number): string {
    if (overall >= 80) return 'Excellent';
    if (overall >= 60) return 'Healthy';
    if (overall >= 40) return 'Building';
    return 'Early days';
}

export default function Dashboard({
    onboarding,
    kpis,
    leadTrend,
    mrrTrend,
    growthScore,
    funnel,
    channels,
    attention,
    topDeals,
    sources,
    range,
    ranges,
}: {
    onboarding?: Onboarding;
    kpis?: Partial<Kpis>;
    leadTrend?: LinePoint[];
    mrrTrend?: LinePoint[];
    growthScore?: GrowthScoreProp;
    funnel?: BarItem[];
    channels?: BarItem[];
    attention?: Attention;
    topDeals?: TopDeal[];
    sources?: Record<string, number>;
    range?: number;
    ranges?: number[];
}) {
    const windowDays = range ?? 30;
    // Never assume the payload is complete: an older backend or an empty tenant
    // can omit fields. Normalise to zeros so the page always renders rather
    // than crashing to a blank screen.
    const k: Kpis = {
        new_leads: kpis?.new_leads ?? ZERO,
        meetings: kpis?.meetings ?? ZERO,
        deals_won: kpis?.deals_won ?? ZERO,
        new_mrr: kpis?.new_mrr ?? ZERO,
        qualified_pipeline: kpis?.qualified_pipeline ?? 0,
        sqls: kpis?.sqls ?? 0,
        arr: kpis?.arr ?? 0,
    };
    const att: Attention = attention ?? { alerts: [], waiting_chats: 0, hot_leads: 0 };
    const score = growthScore ?? { overall: 0, recommendations: [], history: [] };
    const attentionCount = att.alerts.length + (att.waiting_chats > 0 ? 1 : 0) + (att.hot_leads > 0 ? 1 : 0);

    const sourceRows = Object.entries(sources ?? {}).sort((a, b) => b[1] - a[1]);
    const sourceTotal = sourceRows.reduce((sum, [, n]) => sum + n, 0);

    // Grow the source bars in from zero on first paint - a small, calm flourish.
    const [grown, setGrown] = useState(false);
    useEffect(() => {
        const id = requestAnimationFrame(() => setGrown(true));
        return () => cancelAnimationFrame(id);
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-2">
                    <PageHeader
                        title="Dashboard"
                        description={`Your growth command center — the last ${windowDays} days against the ${windowDays} before.`}
                    />
                    <Select value={String(windowDays)} onValueChange={(v) => router.get('/dashboard', { range: v }, { preserveScroll: true })}>
                        <SelectTrigger className="w-36" aria-label="Comparison window">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {(ranges ?? [30, 60, 90]).map((days) => (
                                <SelectItem key={days} value={String(days)}>
                                    Last {days} days
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {onboarding && !onboarding.complete && <OnboardingChecklist onboarding={onboarding} />}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="New Leads" value={k.new_leads.value.toLocaleString('en-US')} delta={delta(k.new_leads)} icon={UserPlus} />
                    <StatCard
                        label="Meetings Booked"
                        value={k.meetings.value.toLocaleString('en-US')}
                        delta={delta(k.meetings)}
                        icon={CalendarCheck}
                    />
                    <StatCard label="Deals Won" value={k.deals_won.value.toLocaleString('en-US')} delta={delta(k.deals_won)} icon={Handshake} />
                    <StatCard label="New MRR" value={money(k.new_mrr.value)} delta={delta(k.new_mrr)} icon={DollarSign} />
                    <StatCard label="Qualified Pipeline" value={money(k.qualified_pipeline)} icon={TrendingUp} />
                    <StatCard label="SQLs" value={k.sqls.toLocaleString('en-US')} icon={Users} />
                    <StatCard label="ARR" value={money(k.arr)} icon={DollarSign} />
                    <div className="border-border bg-card group rounded-lg border p-4">
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground text-sm font-medium">Growth Score</span>
                            <span className="bg-brand-soft text-brand-strong rounded-full px-2 py-0.5 text-xs font-semibold">
                                {scoreBand(score.overall)}
                            </span>
                        </div>
                        <div className="mt-3 flex items-baseline gap-1">
                            <span className="text-foreground text-3xl font-semibold tracking-tight tabular-nums">{score.overall}</span>
                            <span className="text-muted-foreground text-sm">/ 100</span>
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardContent className="p-4">
                            <h2 className="text-sm font-semibold">New leads — last {windowDays} days</h2>
                            <LineChart
                                className="mt-3"
                                data={leadTrend ?? []}
                                ariaLabel={`New leads per day over the last ${windowDays} days`}
                                emptyText={`No new leads in the last ${windowDays} days yet.`}
                            />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <h2 className="text-sm font-semibold">MRR added this period</h2>
                            <LineChart
                                className="mt-3"
                                data={mrrTrend ?? []}
                                color="var(--chart-2)"
                                formatValue={money}
                                ariaLabel={`Cumulative MRR won across the last ${windowDays} days`}
                                emptyText={`No deals won in the last ${windowDays} days yet.`}
                            />
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <CardContent className="p-4">
                            <h2 className="text-sm font-semibold">Funnel</h2>
                            <BarList className="mt-3" items={funnel ?? []} ariaLabel="Acquisition funnel" emptyText="No funnel data yet." />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <h2 className="text-sm font-semibold">Revenue by channel</h2>
                            <BarList
                                className="mt-3"
                                items={channels ?? []}
                                color="var(--chart-2)"
                                formatValue={money}
                                ariaLabel="Won revenue by acquisition channel"
                                emptyText="No won revenue yet — channels appear as deals close."
                            />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between gap-2">
                                <h2 className="text-sm font-semibold">Needs attention</h2>
                                {attentionCount > 0 && (
                                    <span className="rounded-full bg-amber-500/15 px-2 py-0.5 text-xs font-semibold text-amber-600 dark:text-amber-400">
                                        {attentionCount}
                                    </span>
                                )}
                            </div>
                            <div className="mt-3 space-y-2.5">
                                {att.waiting_chats > 0 && (
                                    <Link href="/chat" className="hover:bg-muted/50 flex items-start gap-2.5 rounded-lg p-2 transition-colors">
                                        <MessagesSquare className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" aria-hidden />
                                        <span className="text-sm">
                                            {att.waiting_chats} chat {att.waiting_chats === 1 ? 'visitor' : 'visitors'} waiting for a reply
                                        </span>
                                    </Link>
                                )}
                                {att.hot_leads > 0 && (
                                    <Link
                                        href="/crm/contacts"
                                        className="hover:bg-muted/50 flex items-start gap-2.5 rounded-lg p-2 transition-colors"
                                    >
                                        <Flame className="mt-0.5 size-4 shrink-0 text-red-500" aria-hidden />
                                        <span className="text-sm">
                                            {att.hot_leads} hot {att.hot_leads === 1 ? 'lead' : 'leads'} ready for outreach
                                        </span>
                                    </Link>
                                )}
                                {att.alerts.map((alert) => (
                                    <Link
                                        key={alert.id}
                                        href="/sales/alerts"
                                        className="hover:bg-muted/50 flex items-start gap-2.5 rounded-lg p-2 transition-colors"
                                    >
                                        <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" aria-hidden />
                                        <span className="min-w-0 text-sm">
                                            <span className="line-clamp-2">{alert.message}</span>
                                            {alert.contact && <span className="text-muted-foreground text-xs">{alert.contact}</span>}
                                        </span>
                                    </Link>
                                ))}
                                {attentionCount === 0 && (
                                    <p className="text-muted-foreground py-4 text-center text-sm">All clear — nothing is waiting on you.</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardContent className="p-4">
                            <h2 className="text-sm font-semibold">Top open deals</h2>
                            {(topDeals ?? []).length === 0 ? (
                                <p className="text-muted-foreground py-4 text-center text-sm">No open deals yet.</p>
                            ) : (
                                <ul className="divide-border mt-2 divide-y">
                                    {(topDeals ?? []).map((deal) => (
                                        <li key={deal.id}>
                                            <Link
                                                href={route('crm.deals.show', deal.id)}
                                                className="hover:bg-muted/40 flex items-center justify-between gap-3 rounded-md px-1 py-2.5 transition-colors"
                                            >
                                                <span className="min-w-0">
                                                    <span className="text-foreground block truncate text-sm font-medium">{deal.name}</span>
                                                    {deal.stage && <span className="text-muted-foreground text-xs">{deal.stage}</span>}
                                                </span>
                                                <span className="text-foreground shrink-0 text-sm font-semibold tabular-nums">
                                                    {money(deal.value)}
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <h2 className="text-sm font-semibold">Growth Score trend & next moves</h2>
                            <LineChart
                                className="mt-3"
                                height={120}
                                data={score.history}
                                ariaLabel="Growth score across daily snapshots"
                                emptyText="The daily snapshot builds this trend — check back tomorrow."
                            />
                            {score.recommendations.length > 0 && (
                                <ul className="mt-3 space-y-1.5">
                                    {score.recommendations.map((recommendation) => (
                                        <li key={recommendation.area} className="text-muted-foreground flex gap-2 text-sm">
                                            <TrendingUp className="text-brand-strong mt-0.5 size-3.5 shrink-0" aria-hidden />
                                            <span>
                                                <span className="text-foreground font-medium capitalize">
                                                    {recommendation.area.replace(/_/g, ' ')}:
                                                </span>{' '}
                                                {recommendation.action}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="border-border bg-card rounded-lg border">
                    <div className="border-border flex items-center gap-2.5 border-b px-4 py-3">
                        <span className="bg-brand-soft text-brand-strong flex size-7 items-center justify-center rounded-lg">
                            <PieChart className="size-4" aria-hidden />
                        </span>
                        <h2 className="text-foreground text-sm font-semibold">Lead Sources</h2>
                        {sourceTotal > 0 && (
                            <span className="text-muted-foreground ml-auto text-xs tabular-nums">{sourceTotal.toLocaleString('en-US')} leads</span>
                        )}
                    </div>
                    {sourceRows.length === 0 ? (
                        <p className="text-muted-foreground px-4 py-6 text-sm">No leads captured yet.</p>
                    ) : (
                        <ul className="divide-border divide-y">
                            {sourceRows.map(([channel, total], i) => {
                                const pct = sourceTotal > 0 ? Math.round((total / sourceTotal) * 100) : 0;
                                return (
                                    <li key={channel} className="hover:bg-muted/40 flex items-center gap-3 px-4 py-2.5 transition-colors">
                                        <span className="bg-brand-soft text-brand-strong flex size-5 shrink-0 items-center justify-center rounded-md text-[11px] font-semibold tabular-nums">
                                            {i + 1}
                                        </span>
                                        <span className="text-foreground w-24 shrink-0 text-sm font-medium capitalize">{channel}</span>
                                        <div className="bg-muted h-2.5 flex-1 overflow-hidden rounded-full">
                                            <div
                                                className="from-brand to-brand-strong h-full rounded-full bg-gradient-to-r transition-[width] duration-700 ease-out motion-reduce:transition-none"
                                                style={{ width: grown ? `${pct}%` : '0%' }}
                                            />
                                        </div>
                                        <span className="text-muted-foreground w-16 shrink-0 text-right text-sm tabular-nums">
                                            {total} · {pct}%
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
