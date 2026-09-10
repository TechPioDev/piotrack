import { BarList } from '@/components/charts/bar-list';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Attribution', href: '/analytics/attribution' }];

type Journey = {
    id: number;
    name: string;
    first_touch: string;
    last_touch: string;
    multi_touch: Record<string, number>;
};

function money(cents: number): string {
    return `$${(cents / 100).toFixed(2)}`;
}

function share(value: number, total: number): string {
    return total > 0 ? `${Math.round((value / total) * 100)}%` : '—';
}

function RevenueBars({ rows, label, empty, color }: { rows: [string, number][]; label: string; empty: string; color?: string }) {
    const total = rows.reduce((sum, [, revenue]) => sum + revenue, 0);

    return (
        <div>
            <h3 className="mb-2 text-sm font-medium">{label}</h3>
            <div className="rounded-lg border p-4">
                <BarList
                    ariaLabel={label}
                    items={rows.map(([bucket, revenue]) => ({ label: bucket, value: revenue, hint: share(revenue, total) }))}
                    formatValue={money}
                    color={color}
                    emptyText={empty}
                />
            </div>
        </div>
    );
}

type DimensionRow = { bucket: string; contacts: number; revenue: number };

const DIMENSION_LABELS: Record<string, string> = {
    keywords: 'Keywords (utm_term)',
    ads: 'Ads (utm_content)',
    landing_pages: 'Landing pages',
    content: 'Content',
    forms: 'Forms',
    calls: 'Call sources',
};

export default function Attribution({
    channels,
    campaigns,
    cac,
    roi,
    journeys,
    dimensions,
}: {
    channels: Record<string, number>;
    campaigns: Record<string, number>;
    cac: number;
    roi: number;
    journeys: Journey[];
    dimensions: Record<string, DimensionRow[]>;
}) {
    const summary: { label: string; value: string }[] = [
        { label: 'Customer acquisition cost', value: money(cac) },
        { label: 'Marketing ROI', value: `${roi}x` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Attribution" />
            <div className="space-y-6 p-4">
                <Heading title="Attribution" description="Revenue credited to channels and campaigns, with per-prospect touch models" />

                <div className="grid grid-cols-1 gap-3 sm:max-w-md sm:grid-cols-2">
                    {summary.map((card) => (
                        <Card key={card.label}>
                            <CardContent className="p-4">
                                <p className="text-muted-foreground text-sm">{card.label}</p>
                                <p className="text-2xl font-semibold">{card.value}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <RevenueBars
                        rows={Object.entries(channels)}
                        label="Revenue by channel"
                        empty="No attributed revenue yet. Channels appear here once deals are won."
                    />

                    <RevenueBars
                        rows={Object.entries(campaigns)}
                        label="Revenue by campaign"
                        empty="No campaign revenue yet. Campaigns appear here once deals are won."
                        color="var(--chart-2)"
                    />
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Prospect journeys</h3>
                    {journeys.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No contacts yet. Journeys appear here once prospects are captured.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Contact</th>
                                        <th className="p-3 font-medium">First touch</th>
                                        <th className="p-3 font-medium">Last touch</th>
                                        <th className="p-3 font-medium">Multi-touch credit</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {journeys.map((journey) => (
                                        <tr key={journey.id} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{journey.name}</td>
                                            <td className="p-3">
                                                <Badge variant="outline">{journey.first_touch}</Badge>
                                            </td>
                                            <td className="p-3">
                                                <Badge variant="outline">{journey.last_touch}</Badge>
                                            </td>
                                            <td className="p-3">
                                                <div className="flex flex-wrap gap-1">
                                                    {Object.entries(journey.multi_touch).map(([channel, credit]) => (
                                                        <Badge key={channel} variant="secondary">
                                                            {channel} {Math.round(credit * 100)}%
                                                        </Badge>
                                                    ))}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-1 text-sm font-medium">Revenue by dimension</h3>
                    <p className="text-muted-foreground mb-3 text-sm">
                        Won revenue joined visitor-first-touch → identified contact → closed deals. Anonymous visitors and unwon contacts contribute
                        nothing.
                    </p>
                    <div className="grid gap-6 lg:grid-cols-2">
                        {Object.entries(dimensions).map(([key, rows]) => (
                            <div key={key}>
                                <h4 className="mb-1 text-sm font-medium">{DIMENSION_LABELS[key] ?? key}</h4>
                                {rows.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">No attributed contacts yet for this dimension.</p>
                                ) : (
                                    <div className="overflow-x-auto rounded-lg border">
                                        <table className="w-full text-left text-sm">
                                            <thead className="bg-muted/50 text-muted-foreground">
                                                <tr>
                                                    <th className="p-3 font-medium">Bucket</th>
                                                    <th className="p-3 text-center font-medium">Contacts</th>
                                                    <th className="p-3 text-right font-medium">Won revenue</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y">
                                                {rows.map((row) => (
                                                    <tr key={row.bucket} className="hover:bg-muted/40">
                                                        <td className="p-3 font-medium break-all">{row.bucket}</td>
                                                        <td className="p-3 text-center">{row.contacts}</td>
                                                        <td className="p-3 text-right">{money(row.revenue)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
