import { BarList, type BarItem } from '@/components/charts/bar-list';
import { SegmentBar } from '@/components/charts/segment-bar';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDelta, shareOf, type Compared } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

/** Deal values arrive in minor units; show whole dollars. */
const money = (cents: number) => '$' + Math.round(cents / 100).toLocaleString('en-US');

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Sales', href: '/sales' }];

type Temperature = {
    hot: number;
    warm: number;
    cold: number;
};

type Stats = {
    unread_alerts: number;
    upcoming_bookings: number;
    next_booking_at: string | null;
    target_accounts: number;
    tier_one_accounts: number;
};

type Flows = { meetings_booked: Compared; alerts_raised: Compared };

type RecentAlert = {
    id: number;
    type: string;
    message: string;
    is_read: boolean;
    contact: string | null;
};

export default function SalesDashboard({
    pipeline,
    temperature,
    stats,
    flows,
    recentAlerts,
}: {
    pipeline: BarItem[];
    temperature: Temperature;
    stats: Stats;
    flows: Flows;
    recentAlerts: RecentAlert[];
}) {
    const hotShare = shareOf(temperature.hot, temperature.hot + temperature.warm + temperature.cold);
    const nextBooking = stats.next_booking_at
        ? new Date(stats.next_booking_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
        : null;
    const tiles = [
        {
            label: 'Meetings booked',
            value: flows.meetings_booked.value,
            delta: formatDelta(flows.meetings_booked),
            hint: nextBooking ? `${stats.upcoming_bookings} upcoming · next ${nextBooking}` : 'None upcoming',
        },
        {
            label: 'Hot leads',
            value: temperature.hot,
            hint: hotShare ? `${hotShare} of scored contacts` : 'No scored contacts yet',
        },
        { label: 'Unread alerts', value: stats.unread_alerts, hint: `${flows.alerts_raised.value} raised in 30 days` },
        { label: 'Target accounts', value: stats.target_accounts, hint: `${stats.tier_one_accounts} tier 1` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sales" />
            <div className="space-y-6 p-4">
                <PageHeader title="Sales" description="Lead temperature, alerts and pipeline signals — meetings against the previous 30 days." />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {tiles.map((tile) => (
                        <StatCard key={tile.label} label={tile.label} value={tile.value} delta={tile.delta} hint={tile.hint} />
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardContent className="p-4">
                            <h3 className="text-sm font-medium">Open pipeline by stage</h3>
                            <BarList
                                className="mt-3"
                                items={pipeline}
                                formatValue={money}
                                ariaLabel="Open deal value by pipeline stage"
                                emptyText="No open deals yet. Deals appear here as they enter the pipeline."
                            />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <h3 className="text-sm font-medium">Lead temperature</h3>
                            <SegmentBar
                                className="mt-3"
                                segments={[
                                    { label: 'Hot', value: temperature.hot, color: 'var(--chart-5)' },
                                    { label: 'Warm', value: temperature.warm, color: 'var(--chart-3)' },
                                    { label: 'Cold', value: temperature.cold, color: 'var(--chart-2)' },
                                ]}
                                ariaLabel="Contacts by lead temperature"
                                emptyText="No scored contacts yet."
                            />
                        </CardContent>
                    </Card>
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Recent alerts</h3>
                    {recentAlerts.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No alerts yet. Alerts appear here as leads heat up.</p>
                    ) : (
                        <div className="divide-y rounded-lg border">
                            {recentAlerts.map((alert) => (
                                <div key={alert.id} className="flex items-center justify-between gap-3 p-3">
                                    <div className="flex min-w-0 items-center gap-2">
                                        <Badge variant="outline">{alert.type}</Badge>
                                        <span className="truncate text-sm">{alert.message}</span>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        {alert.contact && <span className="text-muted-foreground text-xs">{alert.contact}</span>}
                                        {!alert.is_read && <Badge variant="secondary">Unread</Badge>}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
