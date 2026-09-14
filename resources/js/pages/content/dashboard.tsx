import { BarList } from '@/components/charts/bar-list';
import { SegmentBar } from '@/components/charts/segment-bar';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { countOf, formatDelta, shareOf, type Compared } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

/** Editorial workflow order, so the pipeline reads as a pipeline. */
const STATUS_ORDER = ['idea', 'draft', 'in_review', 'approved', 'published', 'archived'];

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Content', href: '/content' }];

type Stats = {
    pieces: number;
    published: number;
    social_posts: number;
    scheduled_posts: number;
    placements: number;
    prospects: number;
};

type Flows = { pieces_published: Compared; posts_published: Compared };

type Reviews = {
    count: number;
    average: number;
    by_sentiment: { positive: number; neutral: number; negative: number };
    by_source: Record<string, number>;
};

type RecentPiece = {
    id: number;
    title: string;
    content_type: string;
    status: string;
    optimization_score: number;
};

function statusVariant(status: string): 'default' | 'secondary' {
    return status === 'published' ? 'default' : 'secondary';
}

export default function ContentDashboard({
    stats,
    flows,
    byStatus,
    reviews,
    recentPieces,
}: {
    stats: Stats;
    flows: Flows;
    byStatus: Record<string, number>;
    reviews: Reviews;
    recentPieces: RecentPiece[];
}) {
    const positiveShare = shareOf(reviews.by_sentiment.positive, reviews.count);
    const tiles = [
        {
            label: 'Pieces published',
            value: flows.pieces_published.value,
            delta: formatDelta(flows.pieces_published),
            hint: `${stats.published} live of ${stats.pieces}`,
        },
        {
            label: 'Posts published',
            value: flows.posts_published.value,
            delta: formatDelta(flows.posts_published),
            hint: `${stats.scheduled_posts} scheduled · ${stats.social_posts} total`,
        },
        { label: 'Placements won', value: stats.placements, hint: `from ${countOf(stats.prospects, 'prospect')}` },
        {
            label: 'Review rating',
            value: reviews.count > 0 ? `${reviews.average} ★` : '—',
            hint: reviews.count > 0 ? `${countOf(reviews.count, 'review')} · ${positiveShare} positive` : 'No reviews yet',
        },
    ];

    const pipeline = [...STATUS_ORDER.filter((s) => s in byStatus), ...Object.keys(byStatus).filter((s) => !STATUS_ORDER.includes(s))].map(
        (status) => ({
            label: status.replace(/_/g, ' '),
            value: byStatus[status],
        }),
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Content" />
            <div className="space-y-6 p-4">
                <PageHeader title="Content" description="Content, social, reputation and authority — publishing against the previous 30 days." />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {tiles.map((tile) => (
                        <StatCard key={tile.label} label={tile.label} value={tile.value} delta={tile.delta} hint={tile.hint} />
                    ))}
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardContent className="p-4">
                            <h3 className="text-sm font-medium">Editorial pipeline</h3>
                            <BarList
                                className="mt-3"
                                items={pipeline}
                                color="var(--chart-4)"
                                ariaLabel="Content pieces by workflow status"
                                emptyText="No content yet. Create a piece to start planning."
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <h3 className="text-sm font-medium">Review sentiment</h3>
                            <SegmentBar
                                className="mt-3"
                                segments={[
                                    { label: 'Positive', value: reviews.by_sentiment.positive, color: 'var(--chart-1)' },
                                    { label: 'Neutral', value: reviews.by_sentiment.neutral, color: 'var(--chart-3)' },
                                    { label: 'Negative', value: reviews.by_sentiment.negative, color: 'var(--chart-5)' },
                                ]}
                                ariaLabel="Reviews by sentiment"
                                emptyText="No reviews yet. Reviews appear here once collected."
                            />
                            <p className="text-muted-foreground mt-3 text-xs">
                                {reviews.count} reviews · {reviews.average} average rating
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Recent content</h3>
                    {recentPieces.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No content yet. Create a piece to start planning.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3">Title</th>
                                        <th className="p-3">Type</th>
                                        <th className="p-3">Status</th>
                                        <th className="p-3 text-right">Score</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {recentPieces.map((piece) => (
                                        <tr key={piece.id} className="hover:bg-muted/40">
                                            <td className="p-3">
                                                <Link href={route('content.pieces.show', piece.id)} className="font-medium hover:underline">
                                                    {piece.title}
                                                </Link>
                                            </td>
                                            <td className="p-3">
                                                <Badge variant="outline">{piece.content_type}</Badge>
                                            </td>
                                            <td className="p-3">
                                                <Badge variant={statusVariant(piece.status)}>{piece.status}</Badge>
                                            </td>
                                            <td className="p-3 text-right">{piece.optimization_score}/100</td>
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
