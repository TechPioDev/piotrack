import { BarList, type BarItem } from '@/components/charts/bar-list';
import { SegmentBar } from '@/components/charts/segment-bar';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
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
    target_accounts: number;
};

type RecentAlert = {
    id: number;
    type: string;
    message: string;
    is_read: boolean;
    contact: string | null;
};

const temperatureRow: { key: keyof Temperature; label: string; variant: 'default' | 'secondary' | 'destructive' }[] = [
    { key: 'hot', label: 'Hot', variant: 'destructive' },
    { key: 'warm', label: 'Warm', variant: 'default' },
    { key: 'cold', label: 'Cold', variant: 'secondary' },
];

export default function SalesDashboard({
    pipeline,
    temperature,
    stats,
    recentAlerts,
}: {
    pipeline: BarItem[];
    temperature: Temperature;
    stats: Stats;
    recentAlerts: RecentAlert[];
}) {
    const statCards: { label: string; value: number }[] = [
        { label: 'Unread alerts', value: stats.unread_alerts },
        { label: 'Upcoming bookings', value: stats.upcoming_bookings },
        { label: 'Target accounts', value: stats.target_accounts },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sales" />
            <div className="space-y-6 p-4">
                <PageHeader title="Sales" description="Lead temperature, alerts, and pipeline signals at a glance." />

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
                            <div className="mt-4 grid grid-cols-3 gap-2">
                                {temperatureRow.map((item) => (
                                    <div key={item.key} className="rounded-lg border p-2 text-center">
                                        <Badge variant={item.variant}>{item.label}</Badge>
                                        <p className="mt-1 text-xl font-semibold tabular-nums">{temperature[item.key]}</p>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    {statCards.map((card) => (
                        <Card key={card.label}>
                            <CardContent className="p-4">
                                <p className="text-muted-foreground text-sm">{card.label}</p>
                                <p className="text-2xl font-semibold">{card.value}</p>
                            </CardContent>
                        </Card>
                    ))}
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
