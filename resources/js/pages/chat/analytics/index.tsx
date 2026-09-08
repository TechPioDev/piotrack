import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { BarChart3, MessagesSquare, MousePointerClick, Target, TrendingDown, UserPlus } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Website Chat', href: '/chat' },
    { title: 'Analytics', href: '/chat/analytics' },
];

type Summary = {
    impressions: number;
    opens: number;
    conversations: number;
    completed: number;
    leads: number;
    qualified: number;
    meetings: number;
    open_rate: number;
    engagement_rate: number;
    completion_rate: number;
    lead_rate: number;
    revenue: number;
};
type FunnelRow = { stage: string; count: number; of: string | null; rate: number | null };
type DropOffRow = { node: string; label: string; reached: number; abandoned: number; rate: number };
type WidgetRow = {
    id: number;
    name: string;
    experiment: string | null;
    variant: string | null;
    conversations: number;
    leads: number;
    qualified: number;
    lead_rate: number;
};

const RANGES = [
    { days: 7, label: 'Last 7 days' },
    { days: 30, label: 'Last 30 days' },
    { days: 90, label: 'Last 90 days' },
    { days: 365, label: 'Last year' },
];

/** Minor units (cents) to a compact dollar string. */
function money(minor: number): string {
    const dollars = Math.round(minor / 100);
    if (dollars >= 1000) return `$${(dollars / 1000).toLocaleString('en-US', { maximumFractionDigits: 1 })}K`;
    return `$${dollars.toLocaleString('en-US')}`;
}

type TeaserTest = { active: boolean; variants: Record<string, { conversations: number; leads: number; rate: number | null }> };

export default function ChatAnalytics({
    summary,
    funnel,
    dropOff,
    widgets,
    filters,
    widgetOptions,
    teaser_test,
}: {
    summary: Summary;
    funnel: FunnelRow[];
    dropOff: DropOffRow[];
    widgets: WidgetRow[];
    filters: { days: number; widget: number | null };
    widgetOptions: { id: number; name: string }[];
    teaser_test: TeaserTest;
}) {
    const go = (next: Partial<{ days: number; widget: number | null }>) => {
        const params: Record<string, number> = {};
        const days = next.days ?? filters.days;
        const widget = next.widget === undefined ? filters.widget : next.widget;
        if (days !== 30) params.days = days;
        if (widget) params.widget = widget;
        router.get(route('chat.analytics'), params, { preserveState: true });
    };

    // Experiments are widgets sharing a key. Numbers only — no significance claim.
    const experiments = widgets.filter((w) => w.experiment);
    const nothingYet = summary.impressions === 0 && summary.conversations === 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Chat analytics" />
            <div className="space-y-6 p-4">
                <PageHeader
                    title="Chat analytics"
                    description="How your website chat performs — from a widget being seen to a meeting booked."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Select value={String(filters.widget ?? 'all')} onValueChange={(v) => go({ widget: v === 'all' ? null : Number(v) })}>
                                <SelectTrigger className="w-48" aria-label="Widget">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All widgets</SelectItem>
                                    {widgetOptions.map((w) => (
                                        <SelectItem key={w.id} value={String(w.id)}>
                                            {w.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <div className="flex gap-1">
                                {RANGES.map((r) => (
                                    <Button
                                        key={r.days}
                                        size="sm"
                                        variant={filters.days === r.days ? 'default' : 'outline'}
                                        onClick={() => go({ days: r.days })}
                                    >
                                        {r.days === 365 ? '1y' : `${r.days}d`}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    }
                />

                {nothingYet ? (
                    <EmptyState
                        icon={BarChart3}
                        title="No chat activity yet"
                        description="Once your widget is live on your website, engagement and conversion numbers appear here."
                    />
                ) : (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <StatCard label="Widget views" value={summary.impressions.toLocaleString('en-US')} icon={MousePointerClick} />
                            <StatCard
                                label="Chats opened"
                                value={summary.opens.toLocaleString('en-US')}
                                delta={{ value: `${summary.open_rate}% of views`, direction: 'neutral' }}
                                icon={MessagesSquare}
                            />
                            <StatCard
                                label="Leads captured"
                                value={summary.leads.toLocaleString('en-US')}
                                delta={{ value: `${summary.lead_rate}% of chats`, direction: 'neutral' }}
                                icon={UserPlus}
                            />
                            <StatCard label="Attributed revenue" value={money(summary.revenue)} icon={Target} />
                        </div>

                        {/* Funnel */}
                        <div className="border-border bg-card rounded-lg border">
                            <div className="border-border border-b px-4 py-3">
                                <h2 className="text-foreground text-sm font-semibold">Conversion funnel</h2>
                                <p className="text-muted-foreground text-xs">Each step as a share of the one it is measured against.</p>
                            </div>
                            <ul className="divide-border divide-y">
                                {funnel.map((row) => {
                                    const top = funnel[0]?.count || 1;
                                    const width = Math.max(2, Math.round((row.count / top) * 100));
                                    return (
                                        <li key={row.stage} className="flex items-center gap-3 px-4 py-3">
                                            <span className="text-foreground w-36 shrink-0 text-sm font-medium">{row.stage}</span>
                                            <div className="bg-muted h-2.5 flex-1 overflow-hidden rounded-full">
                                                <div
                                                    className="from-brand to-brand-strong h-full rounded-full bg-gradient-to-r"
                                                    style={{ width: `${width}%` }}
                                                />
                                            </div>
                                            <span className="text-foreground w-20 shrink-0 text-right text-sm font-semibold tabular-nums">
                                                {row.count.toLocaleString('en-US')}
                                            </span>
                                            <span className="text-muted-foreground w-16 shrink-0 text-right text-xs tabular-nums">
                                                {row.rate === null ? '—' : `${row.rate}% of ${row.of}`}
                                            </span>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>

                        {/* Question drop-off */}
                        <div className="border-border bg-card rounded-lg border">
                            <div className="border-border flex items-center gap-2.5 border-b px-4 py-3">
                                <span className="bg-brand-soft text-brand-strong flex size-7 items-center justify-center rounded-lg">
                                    <TrendingDown className="size-4" aria-hidden />
                                </span>
                                <div>
                                    <h2 className="text-foreground text-sm font-semibold">Where visitors stop</h2>
                                    <p className="text-muted-foreground text-xs">Worst leak first — shorten or soften these questions.</p>
                                </div>
                            </div>
                            {dropOff.length === 0 ? (
                                <p className="text-muted-foreground px-4 py-6 text-sm">Not enough conversations yet to show drop-off.</p>
                            ) : (
                                <Table containerClassName="border-0 rounded-none">
                                    <TableHeader>
                                        <tr>
                                            <TableHead>Question</TableHead>
                                            <TableHead className="text-right">Reached</TableHead>
                                            <TableHead className="text-right">Dropped</TableHead>
                                            <TableHead className="text-right">Drop-off</TableHead>
                                        </tr>
                                    </TableHeader>
                                    <TableBody>
                                        {dropOff.slice(0, 12).map((row) => (
                                            <TableRow key={row.node}>
                                                <TableCell className="max-w-md truncate" title={row.label}>
                                                    {row.label}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">{row.reached}</TableCell>
                                                <TableCell className="text-right tabular-nums">{row.abandoned}</TableCell>
                                                <TableCell className="text-right">
                                                    <span
                                                        className={`rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums ${
                                                            row.rate >= 40
                                                                ? 'bg-red-500/10 text-red-600 dark:text-red-400'
                                                                : row.rate >= 20
                                                                  ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                                                  : 'text-muted-foreground'
                                                        }`}
                                                    >
                                                        {row.rate}%
                                                    </span>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </div>

                        {/* Per-widget / experiment comparison */}
                        <div className="border-border bg-card rounded-lg border">
                            <div className="border-border border-b px-4 py-3">
                                <h2 className="text-foreground text-sm font-semibold">By widget</h2>
                                {experiments.length > 0 && (
                                    <p className="text-muted-foreground text-xs">
                                        Widgets sharing an experiment key are variants. These are raw counts — we do not claim a winner, because at
                                        typical chat volumes that judgement needs far more data than this.
                                    </p>
                                )}
                            </div>
                            <Table containerClassName="border-0 rounded-none">
                                <TableHeader>
                                    <tr>
                                        <TableHead>Widget</TableHead>
                                        <TableHead>Experiment</TableHead>
                                        <TableHead className="text-right">Conversations</TableHead>
                                        <TableHead className="text-right">Leads</TableHead>
                                        <TableHead className="text-right">Lead rate</TableHead>
                                    </tr>
                                </TableHeader>
                                <TableBody>
                                    {widgets.map((w) => (
                                        <TableRow key={w.id}>
                                            <TableCell className="font-medium">{w.name}</TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {w.experiment ? `${w.experiment}${w.variant ? ` · ${w.variant}` : ''}` : '—'}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">{w.conversations}</TableCell>
                                            <TableCell className="text-right tabular-nums">{w.leads}</TableCell>
                                            <TableCell className="text-right tabular-nums">{w.lead_rate}%</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </>
                )}

                {/* CHAT-041: teaser A/B — numbers only, no significance claim */}
                {teaser_test.active && (
                    <div className="space-y-2 rounded-lg border p-4">
                        <p className="text-sm font-medium">Teaser A/B test</p>
                        <div className="grid grid-cols-2 gap-3">
                            {Object.entries(teaser_test.variants).map(([variant, stats]) => (
                                <div key={variant} className="rounded-md border p-3">
                                    <p className="text-muted-foreground text-xs uppercase">Variant {variant}</p>
                                    <p className="text-sm tabular-nums">
                                        {stats.conversations} conversations · {stats.leads} leads
                                        {stats.rate !== null && ` · ${stats.rate}% lead rate`}
                                    </p>
                                </div>
                            ))}
                        </div>
                        <p className="text-muted-foreground text-xs">
                            Variants are served sticky per visitor; each conversation records which teaser started it.
                        </p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
