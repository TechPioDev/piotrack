import { LineChart } from '@/components/charts/line-chart';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Growth Score', href: '/analytics/growth-score' }];

type Recommendation = {
    area: string;
    score: number | null;
    action: string;
};

type TrendPoint = {
    date: string;
    overall: number;
};

function humanizeArea(area: string): string {
    const words = area.replace(/_/g, ' ');

    return words.charAt(0).toUpperCase() + words.slice(1);
}

function scoreVariant(score: number): 'default' | 'secondary' | 'destructive' {
    if (score >= 70) return 'default';
    if (score >= 40) return 'secondary';
    return 'destructive';
}

export default function GrowthScore({
    overall,
    breakdown,
    recommendations,
    weights,
    trend,
}: {
    overall: number;
    breakdown: Record<string, number | null>;
    recommendations: Recommendation[];
    weights: Record<string, number>;
    trend: TrendPoint[];
}) {
    const areas = Object.entries(breakdown);
    const measured = areas.filter(([, score]) => score !== null).length;

    const { can } = usePermissions();
    const snapshot = () => router.post(route('analytics.growth-score.snapshot'), {}, { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Growth Score" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading title="MSP Growth Score" description="The composite 0-100 score across every growth module" />
                    {can('analytics.growth-score.manage') && (
                        <Button variant="secondary" onClick={snapshot}>
                            Save snapshot
                        </Button>
                    )}
                </div>

                <Card>
                    <CardContent className="flex flex-wrap items-center gap-6 p-6">
                        <div>
                            <p className="text-muted-foreground text-sm">Overall</p>
                            <p className="text-6xl font-semibold tracking-tight">{overall}</p>
                            <p className="text-muted-foreground text-sm">out of 100</p>
                        </div>
                        <div className="min-w-48 flex-1 space-y-2">
                            <div className="bg-muted h-3 overflow-hidden rounded-full">
                                <div className="bg-primary h-full rounded-full" style={{ width: `${overall}%` }} />
                            </div>
                            <p className="text-muted-foreground text-sm">
                                Weighted across {measured} of {areas.length} modules with data. Modules with no data are excluded rather than scored
                                zero.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Sub-scores</h3>
                    <div className="space-y-3 rounded-lg border p-4">
                        {areas.map(([area, score]) => (
                            <div key={area} className="space-y-1">
                                <div className="flex items-center justify-between gap-3 text-sm">
                                    <span className="font-medium">{humanizeArea(area)}</span>
                                    <span className="flex items-center gap-2">
                                        <span className="text-muted-foreground text-xs">weight {Math.round((weights[area] ?? 0) * 100)}%</span>
                                        {score === null ? (
                                            <span className="text-muted-foreground">No data</span>
                                        ) : (
                                            <Badge variant={scoreVariant(score)}>{score}</Badge>
                                        )}
                                    </span>
                                </div>
                                {score === null ? (
                                    <div className="border-muted h-2 rounded-full border border-dashed" />
                                ) : (
                                    <div className="bg-muted h-2 overflow-hidden rounded-full">
                                        <div className="bg-primary h-full rounded-full" style={{ width: `${score}%` }} />
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Recommendations</h3>
                    {recommendations.length === 0 ? (
                        <p className="text-muted-foreground text-sm">Nothing to fix right now. Every measured module is scoring well.</p>
                    ) : (
                        <div className="divide-y rounded-lg border">
                            {recommendations.map((recommendation) => (
                                <div key={recommendation.area} className="flex items-center justify-between gap-3 p-3">
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium">{humanizeArea(recommendation.area)}</p>
                                        <p className="text-muted-foreground text-sm">{recommendation.action}</p>
                                    </div>
                                    <div className="shrink-0">
                                        {recommendation.score === null ? (
                                            <span className="text-muted-foreground text-sm">No data</span>
                                        ) : (
                                            <Badge variant={scoreVariant(recommendation.score)}>{recommendation.score}</Badge>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Trend</h3>
                    <div className="rounded-lg border p-4">
                        <LineChart
                            ariaLabel="Overall growth score across saved snapshots"
                            data={trend.map((point) => ({ label: point.date, value: point.overall }))}
                            height={140}
                            emptyText="No snapshots yet. Save a snapshot to start tracking the trend."
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
