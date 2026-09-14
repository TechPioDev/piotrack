import { LineChart, type LinePoint } from '@/components/charts/line-chart';
import { SegmentBar } from '@/components/charts/segment-bar';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { countOf, formatDelta, shareOf, type Compared } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

/** Lifecycle stages in funnel order, so the mix reads left-to-right. */
const LIFECYCLE_ORDER = ['subscriber', 'lead', 'mql', 'sql', 'opportunity', 'customer', 'evangelist'];

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Marketing', href: '/marketing' }];

type Stats = {
    lists: number;
    list_members: number;
    forms: number;
    campaigns: number;
    workflows: number;
    workflows_total: number;
    contacts: number;
    leads: number;
};

type Flows = {
    new_contacts: Compared;
    submissions: Compared;
    messages_sent: Compared;
    messages_opened: number;
    enrollments: Compared;
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

export default function MarketingDashboard({
    trend,
    stats,
    flows,
    lifecycle,
    recentCampaigns,
}: {
    trend: LinePoint[];
    stats: Stats;
    flows: Flows;
    lifecycle: Record<string, number>;
    recentCampaigns: RecentCampaign[];
}) {
    // Activity leads with its change against the previous 30 days; stock
    // counts carry a supporting fact instead of an invented trend.
    const leadShare = shareOf(stats.leads, stats.contacts);
    const tiles = [
        {
            label: 'New contacts',
            value: flows.new_contacts.value,
            delta: formatDelta(flows.new_contacts),
            hint: `${stats.contacts.toLocaleString('en-US')} total`,
        },
        { label: 'Leads', value: stats.leads, hint: leadShare ? `${leadShare} of contacts` : 'No contacts yet' },
        {
            label: 'Form submissions',
            value: flows.submissions.value,
            delta: formatDelta(flows.submissions),
            hint: `across ${countOf(stats.forms, 'form')}`,
        },
        {
            label: 'Messages sent',
            value: flows.messages_sent.value,
            delta: formatDelta(flows.messages_sent),
            hint: flows.messages_sent.value > 0 ? `${shareOf(flows.messages_opened, flows.messages_sent.value)} opened` : 'None sent yet',
        },
        {
            label: 'Active workflows',
            value: stats.workflows,
            hint: `of ${stats.workflows_total} · ${flows.enrollments.value.toLocaleString('en-US')} enrolled`,
        },
        { label: 'Lists', value: stats.lists, hint: countOf(stats.list_members, 'member') },
    ];

    const lifecycleSegments = [
        ...LIFECYCLE_ORDER.filter((stage) => stage in lifecycle),
        ...Object.keys(lifecycle).filter((s) => !LIFECYCLE_ORDER.includes(s)),
    ].map((stage) => ({ label: stage.replace(/_/g, ' '), value: lifecycle[stage] }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Marketing" />
            <div className="space-y-6 p-4">
                <PageHeader
                    title="Marketing"
                    description="Lists, forms, campaigns and automation — activity in the last 30 days against the 30 before."
                />

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                    {tiles.map((tile) => (
                        <StatCard
                            key={tile.label}
                            label={tile.label}
                            value={tile.value.toLocaleString('en-US')}
                            delta={tile.delta}
                            hint={tile.hint}
                        />
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
                                            <th className="p-3">Name</th>
                                            <th className="p-3">Channel</th>
                                            <th className="p-3">Status</th>
                                            <th className="p-3 text-right">Sent</th>
                                            <th className="p-3 text-right">Opened</th>
                                            <th className="p-3 text-right">Clicked</th>
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
                                                <td className="p-3 text-right">{campaign.sent}</td>
                                                <td className="p-3 text-right">{campaign.opened}</td>
                                                <td className="p-3 text-right">{campaign.clicked}</td>
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
