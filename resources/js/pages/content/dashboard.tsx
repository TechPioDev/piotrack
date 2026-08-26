import { BarList } from '@/components/charts/bar-list';
import { SegmentBar } from '@/components/charts/segment-bar';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

/** Editorial workflow order, so the pipeline reads as a pipeline. */
const STATUS_ORDER = ['idea', 'draft', 'in_review', 'approved', 'published', 'archived'];

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Content', href: '/content' }];

type Stats = {
    pieces: number;
    published: number;
    social_posts: number;
    placements: number;
};

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
    byStatus,
    reviews,
    recentPieces,
}: {
    stats: Stats;
    byStatus: Record<string, number>;
    reviews: Reviews;
    recentPieces: RecentPiece[];
}) {
    const kpiCards: { label: string; value: string | number }[] = [
        { label: 'Pieces', value: stats.pieces },
        { label: 'Published', value: stats.published },
        { label: 'Social posts', value: stats.social_posts },
        { label: 'Placements', value: stats.placements },
        { label: 'Reviews', value: `${reviews.average} ★` },
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
                <PageHeader title="Content" description="Content, social, reputation, and authority at a glance." />

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    {kpiCards.map((card) => (
                        <Card key={card.label}>
                            <CardContent className="p-4">
                                <p className="text-muted-foreground text-sm">{card.label}</p>
                                <p className="text-2xl font-semibold">{card.value}</p>
                                {card.label === 'Reviews' && <p className="text-muted-foreground text-xs">{reviews.count} total</p>}
                            </CardContent>
                        </Card>
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
                                        <th className="p-3 font-medium">Title</th>
                                        <th className="p-3 font-medium">Type</th>
                                        <th className="p-3 font-medium">Status</th>
                                        <th className="p-3 text-center font-medium">Score</th>
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
                                            <td className="p-3 text-center">{piece.optimization_score}/100</td>
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
