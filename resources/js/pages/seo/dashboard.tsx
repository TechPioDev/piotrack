import { BarList, type BarItem } from '@/components/charts/bar-list';
import { LineChart, type LinePoint } from '@/components/charts/line-chart';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { countOf, formatDelta, shareOf, type Compared } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'SEO', href: '/seo' }];

type Stats = {
    audits: number;
    avg_score: number;
    keywords: number;
    page_one: number;
    top_three: number;
    locations: number;
    ai_checks: number;
    tracked: number;
    geo_keywords: number;
    latest_score: number | null;
};

type Flows = { audits: Compared; ai_checks: Compared };

type RecentAudit = {
    id: number;
    url: string;
    score: number;
    issues_count: number;
};

function scoreVariant(score: number): 'default' | 'secondary' | 'destructive' {
    if (score >= 80) return 'default';
    if (score >= 50) return 'secondary';
    return 'destructive';
}

export default function SeoDashboard({
    stats,
    flows,
    recentAudits,
    distribution,
    auditTrend,
}: {
    stats: Stats;
    flows: Flows;
    recentAudits: RecentAudit[];
    distribution: BarItem[];
    auditTrend: LinePoint[];
}) {
    const ofTracked = (count: number) => {
        const share = shareOf(count, stats.tracked);

        return share ? `${share} of tracked` : 'None tracked yet';
    };
    const tiles = [
        { label: 'Audits run', value: flows.audits.value, delta: formatDelta(flows.audits), hint: `${stats.audits} all time` },
        {
            label: 'Avg score',
            value: stats.latest_score !== null ? stats.avg_score : '—',
            hint: stats.latest_score !== null ? `latest audit ${stats.latest_score}` : 'No audits yet',
        },
        { label: 'Keywords', value: stats.keywords, hint: `${stats.tracked} tracked` },
        { label: 'Page 1', value: stats.page_one, hint: `${stats.top_three} in top 3 · ${ofTracked(stats.page_one)}` },
        { label: 'Locations', value: stats.locations, hint: countOf(stats.geo_keywords, 'geo keyword') },
        { label: 'AI checks', value: flows.ai_checks.value, delta: formatDelta(flows.ai_checks), hint: `${stats.ai_checks} all time` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="SEO" />
            <div className="space-y-6 p-4">
                <PageHeader
                    title="SEO"
                    description="Search intelligence, rankings and AI visibility — audits and AI checks against the previous 30 days."
                />

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                    {tiles.map((tile) => (
                        <StatCard key={tile.label} label={tile.label} value={tile.value} delta={tile.delta} hint={tile.hint} />
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <CardContent className="p-4">
                            <h3 className="text-sm font-medium">Ranking distribution</h3>
                            <BarList
                                className="mt-3"
                                items={distribution}
                                color="var(--chart-2)"
                                ariaLabel="Tracked keywords by ranking bucket"
                                emptyText="No tracked keywords yet. Track keywords to see where they rank."
                            />
                        </CardContent>
                    </Card>
                    <Card className="lg:col-span-2">
                        <CardContent className="p-4">
                            <h3 className="text-sm font-medium">Audit score over recent audits</h3>
                            <LineChart
                                className="mt-3"
                                data={auditTrend}
                                ariaLabel="Technical audit score across recent audits"
                                emptyText="No audits yet. Run a technical audit to start the trend."
                            />
                        </CardContent>
                    </Card>
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Recent audits</h3>
                    {recentAudits.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No audits yet. Run a technical audit to see results here.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3">URL</th>
                                        <th className="p-3">Score</th>
                                        <th className="p-3 text-right">Issues</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {recentAudits.map((audit) => (
                                        <tr key={audit.id} className="hover:bg-muted/40">
                                            <td className="p-3">
                                                <Link href={route('seo.audits.show', audit.id)} className="font-medium hover:underline">
                                                    {audit.url}
                                                </Link>
                                            </td>
                                            <td className="p-3">
                                                <Badge variant={scoreVariant(audit.score)}>{audit.score}</Badge>
                                            </td>
                                            <td className="p-3 text-right">{audit.issues_count}</td>
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
