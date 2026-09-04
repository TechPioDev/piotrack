import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Behavior', href: '/analytics/behavior' }];

type PageRow = {
    path: string;
    pageviews: number;
    visitors: number;
    clicks: number;
    avg_scroll_depth: number | null;
};

type BounceRow = {
    landing_path: string;
    sessions: number;
    bounces: number;
    bounce_rate: number | null;
    insufficient: boolean;
};

type Heatmap = {
    path: string;
    clicks: number;
    grid: number[][];
    targets: { label: string; clicks: number }[];
    scroll: { samples: number; avg_depth: number | null; buckets: Record<string, number> };
};

export default function Behavior({ pages, bounces, heatmap }: { pages: PageRow[]; bounces: BounceRow[]; heatmap: Heatmap | null }) {
    const maxCell = heatmap === null ? 0 : Math.max(...heatmap.grid.flat(), 1);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Behavior" />
            <div className="space-y-6 p-4">
                <Heading
                    title="Behavior"
                    description="Click heatmaps, scroll depth and bounce rates from your own first-party pixel — session replay needs a recording provider and is never simulated here"
                />

                <div>
                    <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <h3 className="text-sm font-medium">Click heatmap</h3>
                        {pages.length > 0 && (
                            <Select
                                value={heatmap?.path ?? ''}
                                onValueChange={(path) => router.get(route('analytics.behavior.index'), { path }, { preserveScroll: true })}
                            >
                                <SelectTrigger className="h-8 w-72">
                                    <SelectValue placeholder="Pick a page" />
                                </SelectTrigger>
                                <SelectContent>
                                    {pages.map((page) => (
                                        <SelectItem key={page.path} value={page.path}>
                                            {page.path}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </div>
                    {heatmap === null || heatmap.clicks === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            No clicks recorded yet. The tracking pixel records click positions automatically once installed.
                        </p>
                    ) : (
                        <div className="grid gap-6 lg:grid-cols-2">
                            <Card>
                                <CardContent className="p-4">
                                    <p className="text-muted-foreground mb-2 text-xs">
                                        {heatmap.clicks} clicks on {heatmap.path} — columns are viewport width, rows are page depth
                                    </p>
                                    <div className="grid aspect-[4/3] grid-cols-10 grid-rows-10 gap-px overflow-hidden rounded border">
                                        {heatmap.grid.flatMap((row, y) =>
                                            row.map((count, x) => (
                                                <div
                                                    key={`${y}-${x}`}
                                                    title={`${count} clicks`}
                                                    className="bg-primary"
                                                    style={{ opacity: count === 0 ? 0.03 : 0.15 + (count / maxCell) * 0.85 }}
                                                />
                                            )),
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                            <div className="space-y-4">
                                <Card>
                                    <CardContent className="p-4">
                                        <p className="mb-2 text-sm font-medium">Top click targets</p>
                                        {heatmap.targets.length === 0 ? (
                                            <p className="text-muted-foreground text-sm">No labelled targets yet.</p>
                                        ) : (
                                            <ul className="space-y-1 text-sm">
                                                {heatmap.targets.map((target) => (
                                                    <li key={target.label} className="flex items-center justify-between gap-2">
                                                        <span className="truncate">{target.label}</span>
                                                        <Badge variant="secondary">{target.clicks}</Badge>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardContent className="p-4">
                                        <p className="mb-2 text-sm font-medium">
                                            Scroll depth{heatmap.scroll.avg_depth !== null && <> — average {heatmap.scroll.avg_depth}%</>}
                                        </p>
                                        {heatmap.scroll.samples === 0 ? (
                                            <p className="text-muted-foreground text-sm">No scroll samples yet.</p>
                                        ) : (
                                            <div className="space-y-1">
                                                {Object.entries(heatmap.scroll.buckets).map(([bucket, count]) => (
                                                    <div key={bucket} className="flex items-center gap-2 text-sm">
                                                        <span className="text-muted-foreground w-14">{bucket}%</span>
                                                        <div className="bg-muted h-2 flex-1 overflow-hidden rounded-full">
                                                            <div
                                                                className="bg-primary h-full rounded-full"
                                                                style={{ width: `${(count / heatmap.scroll.samples) * 100}%` }}
                                                            />
                                                        </div>
                                                        <span className="w-8 text-right">{count}</span>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Page behavior</h3>
                    {pages.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No pageviews recorded yet.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Page</th>
                                        <th className="p-3 text-center font-medium">Views</th>
                                        <th className="p-3 text-center font-medium">Visitors</th>
                                        <th className="p-3 text-center font-medium">Clicks</th>
                                        <th className="p-3 text-center font-medium">Avg scroll</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {pages.map((page) => (
                                        <tr key={page.path} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium break-all">{page.path}</td>
                                            <td className="p-3 text-center">{page.pageviews}</td>
                                            <td className="p-3 text-center">{page.visitors}</td>
                                            <td className="p-3 text-center">{page.clicks}</td>
                                            <td className="p-3 text-center">{page.avg_scroll_depth !== null ? `${page.avg_scroll_depth}%` : '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Bounce rates by landing page</h3>
                    {bounces.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No sessions recorded yet.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Landing page</th>
                                        <th className="p-3 text-center font-medium">Sessions</th>
                                        <th className="p-3 text-center font-medium">Bounces</th>
                                        <th className="p-3 text-center font-medium">Bounce rate</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {bounces.map((row) => (
                                        <tr key={row.landing_path} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium break-all">{row.landing_path}</td>
                                            <td className="p-3 text-center">{row.sessions}</td>
                                            <td className="p-3 text-center">{row.bounces}</td>
                                            <td className="p-3 text-center">
                                                {row.insufficient ? (
                                                    <span className="text-muted-foreground" title="Under 5 sessions — a rate would be noise">
                                                        needs {5 - row.sessions} more
                                                    </span>
                                                ) : (
                                                    <Badge variant={row.bounce_rate !== null && row.bounce_rate >= 70 ? 'destructive' : 'secondary'}>
                                                        {row.bounce_rate}%
                                                    </Badge>
                                                )}
                                            </td>
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
