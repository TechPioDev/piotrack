import { LineChart } from '@/components/charts/line-chart';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
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

const SMALL_STATS: { key: keyof Stats; label: string }[] = [
    { key: 'campaigns', label: 'Campaigns' },
    { key: 'active', label: 'Active' },
    { key: 'audiences', label: 'Audiences' },
];

function money(cents: number): string {
    return `$${(cents / 100).toFixed(2)}`;
}

function statusVariant(status: string): 'default' | 'secondary' {
    return status === 'active' ? 'default' : 'secondary';
}

function kpiCards(kpi: Kpi): { label: string; value: string | number }[] {
    return [
        { label: 'Spend', value: money(kpi.spend) },
        { label: 'Impressions', value: kpi.impressions },
        { label: 'Clicks', value: kpi.clicks },
        { label: 'CTR', value: `${kpi.ctr}%` },
        { label: 'Conversions', value: kpi.conversions },
        { label: 'CPA', value: money(kpi.cpa) },
        { label: 'ROAS', value: `${kpi.roas}x` },
    ];
}

type TrendDay = { label: string; spend: number; clicks: number };

export default function AdvertisingDashboard({
    trend,
    kpi,
    stats,
    campaigns,
}: {
    trend: TrendDay[];
    kpi: Kpi;
    stats: Stats;
    campaigns: CampaignRow[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Advertising" />
            <div className="space-y-6 p-4">
                <PageHeader title="Advertising" description="Paid media performance across platforms." />

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-7">
                    {kpiCards(kpi).map((card) => (
                        <StatCard key={card.label} label={card.label} value={card.value} />
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

                <div className="grid grid-cols-3 gap-3 sm:max-w-md">
                    {SMALL_STATS.map((stat) => (
                        <Card key={stat.key}>
                            <CardContent className="p-4">
                                <p className="text-muted-foreground text-sm">{stat.label}</p>
                                <p className="text-2xl font-semibold">{stats[stat.key]}</p>
                            </CardContent>
                        </Card>
                    ))}
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
                                        <th className="p-3 font-medium">Name</th>
                                        <th className="p-3 font-medium">Platform</th>
                                        <th className="p-3 font-medium">Status</th>
                                        <th className="p-3 text-center font-medium">Spend</th>
                                        <th className="p-3 text-center font-medium">Clicks</th>
                                        <th className="p-3 text-center font-medium">Conversions</th>
                                        <th className="p-3 text-center font-medium">ROAS</th>
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
                                            <td className="p-3 text-center">{money(campaign.kpi.spend)}</td>
                                            <td className="p-3 text-center">{campaign.kpi.clicks}</td>
                                            <td className="p-3 text-center">{campaign.kpi.conversions}</td>
                                            <td className="p-3 text-center">{campaign.kpi.roas}x</td>
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
