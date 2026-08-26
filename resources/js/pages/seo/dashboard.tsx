import { BarList, type BarItem } from '@/components/charts/bar-list';
import { LineChart, type LinePoint } from '@/components/charts/line-chart';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
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
};

type RecentAudit = {
    id: number;
    url: string;
    score: number;
    issues_count: number;
};

const STAT_CARDS: { key: keyof Stats; label: string }[] = [
    { key: 'audits', label: 'Audits' },
    { key: 'avg_score', label: 'Avg score' },
    { key: 'keywords', label: 'Keywords' },
    { key: 'page_one', label: 'Page 1' },
    { key: 'top_three', label: 'Top 3' },
    { key: 'locations', label: 'Locations' },
    { key: 'ai_checks', label: 'AI checks' },
];

function scoreVariant(score: number): 'default' | 'secondary' | 'destructive' {
    if (score >= 80) return 'default';
    if (score >= 50) return 'secondary';
    return 'destructive';
}

export default function SeoDashboard({
    stats,
    recentAudits,
    distribution,
    auditTrend,
}: {
    stats: Stats;
    recentAudits: RecentAudit[];
    distribution: BarItem[];
    auditTrend: LinePoint[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="SEO" />
            <div className="space-y-6 p-4">
                <PageHeader title="SEO" description="Search intelligence, rankings and AI visibility at a glance." />

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-7">
                    {STAT_CARDS.map((card) => (
                        <StatCard key={card.key} label={card.label} value={stats[card.key]} />
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
                                        <th className="p-3 font-medium">URL</th>
                                        <th className="p-3 font-medium">Score</th>
                                        <th className="p-3 text-center font-medium">Issues</th>
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
                                            <td className="p-3 text-center">{audit.issues_count}</td>
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
