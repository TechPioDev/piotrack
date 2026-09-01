import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type Committee = {
    id: number;
    name: string;
    title: string | null;
    buying_role: string | null;
    is_decision_maker: boolean;
    lead_score: number;
    intent_score: number;
    lifecycle_stage: string;
};

type ReportProps = {
    account: { id: number; company: string | null; tier: number; status: string; score: number };
    engagement: { committee_size: number; engaged: number; decision_makers: number; multi_threaded: boolean };
    committee: Committee[];
    deals: { id: number; name: string; status: string; value: number; mrr: number }[];
    bookings: { id: number; name: string; status: string; scheduled_at: string | null }[];
    signals: { type: string; weight: number; url: string | null; occurred_at: string | null }[];
};

const money = (cents: number) => `$${(cents / 100).toLocaleString('en-US', { minimumFractionDigits: 0 })}`;
const when = (iso: string | null) => (iso ? new Date(iso).toLocaleString() : '—');

export default function AccountReport({ account, engagement, committee, deals, bookings, signals }: ReportProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Accounts', href: '/sales/accounts' },
        { title: account.company ?? 'Account', href: `/sales/accounts/${account.id}/report` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${account.company ?? 'Account'} — account report`} />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title={`${account.company ?? 'Account'} — engagement report`}
                        description={`Tier ${account.tier} · ${account.status} · account score ${account.score}`}
                    />
                    <Button variant="secondary" asChild>
                        <Link href={route('sales.accounts.index')}>Back to accounts</Link>
                    </Button>
                </div>

                <div className="grid gap-3 sm:grid-cols-4">
                    {[
                        { label: 'Committee', value: engagement.committee_size },
                        { label: 'Engaged', value: engagement.engaged },
                        { label: 'Decision makers', value: engagement.decision_makers },
                        { label: 'Threading', value: engagement.multi_threaded ? 'Multi-threaded' : 'Single thread' },
                    ].map((kpi) => (
                        <div key={kpi.label} className="rounded-lg border p-4">
                            <p className="text-muted-foreground text-xs font-medium uppercase">{kpi.label}</p>
                            <p className="mt-1 text-2xl font-semibold">{kpi.value}</p>
                        </div>
                    ))}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Buying committee</h3>
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">Name</th>
                                    <th className="p-3 font-medium">Title</th>
                                    <th className="p-3 font-medium">Role</th>
                                    <th className="p-3 font-medium">Stage</th>
                                    <th className="p-3 text-center font-medium">Lead score</th>
                                    <th className="p-3 text-center font-medium">Intent</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {committee.map((person) => (
                                    <tr key={person.id}>
                                        <td className="p-3 font-medium">
                                            <Link className="hover:underline" href={route('crm.contacts.show', person.id)}>
                                                {person.name}
                                            </Link>
                                        </td>
                                        <td className="text-muted-foreground p-3">{person.title ?? '—'}</td>
                                        <td className="p-3">
                                            {person.is_decision_maker ? (
                                                <Badge>Decision maker</Badge>
                                            ) : person.buying_role ? (
                                                <Badge variant="secondary">{person.buying_role.replace('_', ' ')}</Badge>
                                            ) : (
                                                <span className="text-muted-foreground">—</span>
                                            )}
                                        </td>
                                        <td className="text-muted-foreground p-3 uppercase">{person.lifecycle_stage}</td>
                                        <td className="p-3 text-center">{person.lead_score}</td>
                                        <td className="p-3 text-center">{person.intent_score}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <div>
                        <h3 className="mb-2 text-sm font-medium">Deals</h3>
                        {deals.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No deals for this account yet.</p>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground">
                                        <tr>
                                            <th className="p-3 font-medium">Deal</th>
                                            <th className="p-3 font-medium">Status</th>
                                            <th className="p-3 text-right font-medium">Value</th>
                                            <th className="p-3 text-right font-medium">MRR</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {deals.map((deal) => (
                                            <tr key={deal.id}>
                                                <td className="p-3 font-medium">{deal.name}</td>
                                                <td className="p-3">
                                                    <Badge
                                                        variant={
                                                            deal.status === 'won' ? 'default' : deal.status === 'lost' ? 'destructive' : 'secondary'
                                                        }
                                                    >
                                                        {deal.status}
                                                    </Badge>
                                                </td>
                                                <td className="p-3 text-right">{money(deal.value)}</td>
                                                <td className="p-3 text-right">{money(deal.mrr)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    <div>
                        <h3 className="mb-2 text-sm font-medium">Meetings</h3>
                        {bookings.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No meetings booked yet.</p>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground">
                                        <tr>
                                            <th className="p-3 font-medium">With</th>
                                            <th className="p-3 font-medium">Status</th>
                                            <th className="p-3 font-medium">When</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {bookings.map((booking) => (
                                            <tr key={booking.id}>
                                                <td className="p-3 font-medium">{booking.name}</td>
                                                <td className="text-muted-foreground p-3">{booking.status}</td>
                                                <td className="text-muted-foreground p-3">{when(booking.scheduled_at)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Recent intent signals</h3>
                    {signals.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No intent signals recorded yet.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Signal</th>
                                        <th className="p-3 text-center font-medium">Weight</th>
                                        <th className="p-3 font-medium">Page</th>
                                        <th className="p-3 font-medium">When</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {signals.map((signal, index) => (
                                        <tr key={index}>
                                            <td className="p-3 font-medium">{signal.type.replace(/_/g, ' ')}</td>
                                            <td className="p-3 text-center">{signal.weight}</td>
                                            <td className="text-muted-foreground p-3 break-all">{signal.url ?? '—'}</td>
                                            <td className="text-muted-foreground p-3">{when(signal.occurred_at)}</td>
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
