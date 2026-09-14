import { LineChart } from '@/components/charts/line-chart';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { countOf, formatDelta, type Compared } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Advertising', href: '/ads' }];

type Kpi = {
    impressions: number;
    clicks: number;
    spend: number;
    conversions: number;
    revenue: number;
    ctr: number;
    cpc: number;
    cpa: number;
    roas: number;
    conversion_rate: number;
};

type Stats = {
    campaigns: number;
    active: number;
    audiences: number;
};

type CampaignRow = {
    id: number;
    name: string;
    platform: string;
    status: string;
    kpi: Kpi;
};

function money(cents: number): string {
    return `$${(cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function statusVariant(status: string): 'default' | 'secondary' {
    return status === 'active' ? 'default' : 'secondary';
}

type Flows = Record<'spend' | 'impressions' | 'clicks' | 'conversions' | 'revenue', Compared>;

/**
 * Volumes carry their change against the previous 30 days; the ratios derived
 * from them ride along as context, so no number from the old tiles is lost.
 */
function kpiTiles(kpi: Kpi, flows: Flows, stats: Stats) {
    return [
        { label: 'Spend', value: money(kpi.spend), delta: formatDelta(flows.spend), hint: `was ${money(flows.spend.previous)}` },
        { label: 'Impressions', value: kpi.impressions.toLocaleString('en-US'), delta: formatDelta(flows.impressions), hint: `CTR ${kpi.ctr}%` },
        { label: 'Clicks', value: kpi.clicks.toLocaleString('en-US'), delta: formatDelta(flows.clicks), hint: `CPC ${money(kpi.cpc)}` },
        {
            label: 'Conversions',
            value: kpi.conversions.toLocaleString('en-US'),
            delta: formatDelta(flows.conversions),
            hint: `CPA ${money(kpi.cpa)}`,
        },
        { label: 'Revenue', value: money(kpi.revenue), delta: formatDelta(flows.revenue), hint: `ROAS ${kpi.roas}x` },
        { label: 'Active campaigns', value: stats.active, hint: `of ${stats.campaigns} · ${countOf(stats.audiences, 'audience')}` },
    ];
}

type TrendDay = { label: string; spend: number; clicks: number };

export default function AdvertisingDashboard({
    trend,
    kpi,
    flows,
    stats,
    campaigns,
}: {
    trend: TrendDay[];
    kpi: Kpi;
    flows: Flows;
    stats: Stats;
    campaigns: CampaignRow[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Advertising" />
            <div className="space-y-6 p-4">
                <PageHeader title="Advertising" description="Paid media performance across platforms — the last 30 days against the 30 before." />

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                    {kpiTiles(kpi, flows, stats).map((tile) => (
                        <StatCard key={tile.label} label={tile.label} value={tile.value} delta={tile.delta} hint={tile.hint} />
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardContent className="p-4">
                            <h3 className="text-sm font-medium">Spend per day — last 30 days</h3>
                            <LineChart
                                className="mt-3"
                                data={trend.map((d) => ({ label: d.label, value: d.spend }))}
                                formatValue={money}
                                ariaLabel="Ad spend per day over the last 30 days"
                                emptyText="No ad metrics in the last 30 days yet."
                            />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <h3 className="text-sm font-medium">Clicks per day — last 30 days</h3>
                            <LineChart
                                className="mt-3"
                                data={trend.map((d) => ({ label: d.label, value: d.clicks }))}
                                color="var(--chart-2)"
                                ariaLabel="Ad clicks per day over the last 30 days"
                                emptyText="No ad metrics in the last 30 days yet."
                            />
                        </CardContent>
                    </Card>
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Campaigns (last 30 days)</h3>
                    {campaigns.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No campaigns yet. Create a campaign to start advertising.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3">Name</th>
                                        <th className="p-3">Platform</th>
                                        <th className="p-3">Status</th>
                                        <th className="p-3 text-right">Spend</th>
                                        <th className="p-3 text-right">Clicks</th>
                                        <th className="p-3 text-right">Conversions</th>
                                        <th className="p-3 text-right">ROAS</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {campaigns.map((campaign) => (
                                        <tr key={campaign.id} className="hover:bg-muted/40">
                                            <td className="p-3">
                                                <Link href={route('ads.campaigns.show', campaign.id)} className="font-medium hover:underline">
                                                    {campaign.name}
                                                </Link>
                                            </td>
                                            <td className="p-3">
                                                <Badge variant="outline">{campaign.platform}</Badge>
                                            </td>
                                            <td className="p-3">
                                                <Badge variant={statusVariant(campaign.status)}>{campaign.status}</Badge>
                                            </td>
                                            <td className="p-3 text-right">{money(campaign.kpi.spend)}</td>
                                            <td className="p-3 text-right">{campaign.kpi.clicks}</td>
                                            <td className="p-3 text-right">{campaign.kpi.conversions}</td>
                                            <td className="p-3 text-right">{campaign.kpi.roas}x</td>
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
