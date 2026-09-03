import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Omnichannel', href: '/analytics/omnichannel' }];

type Channel = {
    channel: string;
    label: string;
    active: boolean;
    metric: string;
    value: number;
    detail: string | null;
    href: string;
};

type Touchpoint = {
    channel: string;
    at: string;
};

type Journey = {
    contact_id: number;
    name: string;
    lifecycle_stage: string | null;
    lead_score: number;
    first_touch: string;
    last_touch: string;
    touchpoints: Touchpoint[];
};

export default function Omnichannel({ channels, journeys }: { channels: Channel[]; journeys: Journey[] }) {
    const activeChannels = channels.filter((channel) => channel.active).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Omnichannel" />
            <div className="space-y-6 p-4">
                <Heading title="Omnichannel" description="Every acquisition surface side by side, plus the unified prospect journey" />

                <div>
                    <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <h3 className="text-sm font-medium">Channels</h3>
                        <p className="text-muted-foreground text-sm">
                            {activeChannels} of {channels.length} active
                        </p>
                    </div>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        {channels.map((channel) => (
                            <Card key={channel.channel}>
                                <CardContent className="space-y-1 p-4">
                                    <div className="flex items-center justify-between gap-2">
                                        <Link href={channel.href} className="text-sm font-medium hover:underline">
                                            {channel.label}
                                        </Link>
                                        <Badge variant={channel.active ? 'default' : 'secondary'}>{channel.active ? 'Active' : 'Inactive'}</Badge>
                                    </div>
                                    <p className="text-2xl font-semibold">{channel.value}</p>
                                    <p className="text-muted-foreground text-sm">{channel.metric}</p>
                                    {channel.detail !== null && <p className="text-muted-foreground text-xs">{channel.detail}</p>}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                    <p className="text-muted-foreground mt-2 text-xs">
                        Measured from this platform&apos;s own records — posts, campaigns, citations, placements. Live network-side insights (Maps
                        views, network impressions) appear once the platform connector is linked.
                    </p>
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
                                        <th className="p-3 font-medium">Lifecycle stage</th>
                                        <th className="p-3 text-center font-medium">Lead score</th>
                                        <th className="p-3 font-medium">First touch</th>
                                        <th className="p-3 font-medium">Last touch</th>
                                        <th className="p-3 font-medium">Touchpoints</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {journeys.map((journey) => (
                                        <tr key={journey.contact_id} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{journey.name}</td>
                                            <td className="text-muted-foreground p-3">{journey.lifecycle_stage ?? '—'}</td>
                                            <td className="p-3 text-center">{journey.lead_score}</td>
                                            <td className="p-3">
                                                <Badge variant="outline">{journey.first_touch}</Badge>
                                            </td>
                                            <td className="p-3">
                                                <Badge variant="outline">{journey.last_touch}</Badge>
                                            </td>
                                            <td className="p-3">
                                                <div className="flex flex-wrap gap-1">
                                                    {journey.touchpoints.map((touchpoint, index) => (
                                                        <Badge key={`${touchpoint.channel}-${index}`} variant="secondary">
                                                            {touchpoint.channel}
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
            </div>
        </AppLayout>
    );
}
