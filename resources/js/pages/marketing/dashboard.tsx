import { LineChart, type LinePoint } from '@/components/charts/line-chart';
import { SegmentBar } from '@/components/charts/segment-bar';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

/** Lifecycle stages in funnel order, so the mix reads left-to-right. */
const LIFECYCLE_ORDER = ['subscriber', 'lead', 'mql', 'sql', 'opportunity', 'customer', 'evangelist'];

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Marketing', href: '/marketing' }];

type Stats = {
    lists: number;
    forms: number;
    campaigns: number;
    workflows: number;
    contacts: number;
    leads: number;
};

type RecentCampaign = {
    id: number;
    name: string;
    channel: string;
    status: string;
    sent: number;
    opened: number;
    clicked: number;
};

const STAT_CARDS: { key: keyof Stats; label: string }[] = [
    { key: 'contacts', label: 'Contacts' },
    { key: 'leads', label: 'Leads' },
    { key: 'lists', label: 'Lists' },
    { key: 'forms', label: 'Forms' },
    { key: 'campaigns', label: 'Campaigns' },
    { key: 'workflows', label: 'Workflows' },
];

export default function MarketingDashboard({
    trend,
    stats,
    lifecycle,
    recentCampaigns,
}: {
    trend: LinePoint[];
    stats: Stats;
    lifecycle: Record<string, number>;
    recentCampaigns: RecentCampaign[];
}) {
    const lifecycleSegments = [
        ...LIFECYCLE_ORDER.filter((stage) => stage in lifecycle),
        ...Object.keys(lifecycle).filter((s) => !LIFECYCLE_ORDER.includes(s)),
    ].map((stage) => ({ label: stage.replace(/_/g, ' '), value: lifecycle[stage] }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Marketing" />
            <div className="space-y-6 p-4">
                <PageHeader title="Marketing" description="Lists, forms, campaigns and automation at a glance." />

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    {STAT_CARDS.map((card) => (
                        <StatCard key={card.key} label={card.label} value={stats[card.key].toLocaleString('en-US')} />
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardContent className="p-4">
                            <h3 className="text-sm font-medium">New contacts — last 30 days</h3>
                            <LineChart
                                className="mt-3"
                                data={trend}
                                ariaLabel="New contacts per day over the last 30 days"
                                emptyText="No new contacts in the last 30 days yet."
                            />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <h3 className="text-sm font-medium">By lifecycle stage</h3>
                            <SegmentBar
                                className="mt-3"
                                segments={lifecycleSegments}
                                ariaLabel="Contacts by lifecycle stage"
                                emptyText="No contacts yet. Add contacts to see the lifecycle mix."
                            />
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4">
                    <div>
                        <h3 className="mb-2 text-sm font-medium">Recent campaigns</h3>
                        {recentCampaigns.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No campaigns yet. Create one from the Campaigns page to see it here.</p>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground">
                                        <tr>
                                            <th className="p-3 font-medium">Name</th>
                                            <th className="p-3 font-medium">Channel</th>
                                            <th className="p-3 font-medium">Status</th>
                                            <th className="p-3 text-center font-medium">Sent</th>
                                            <th className="p-3 text-center font-medium">Opened</th>
                                            <th className="p-3 text-center font-medium">Clicked</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {recentCampaigns.map((campaign) => (
                                            <tr key={campaign.id} className="hover:bg-muted/40">
                                                <td className="p-3">
                                                    <Link
                                                        href={route('marketing.campaigns.show', campaign.id)}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {campaign.name}
                                                    </Link>
                                                </td>
                                                <td className="p-3">
                                                    <Badge variant="outline">{campaign.channel}</Badge>
                                                </td>
                                                <td className="p-3">
                                                    <Badge variant={campaign.status === 'sent' ? 'default' : 'secondary'}>{campaign.status}</Badge>
                                                </td>
                                                <td className="p-3 text-center">{campaign.sent}</td>
                                                <td className="p-3 text-center">{campaign.opened}</td>
                                                <td className="p-3 text-center">{campaign.clicked}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
