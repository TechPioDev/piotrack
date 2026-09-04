import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

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

type OrgNode = {
    id: number;
    name: string;
    title: string | null;
    buying_role: string | null;
    is_decision_maker: boolean;
    reports: OrgNode[];
};

type PlayStep = { step: string; detail: string };

type ReportProps = {
    account: { id: number; company: string | null; tier: number; status: string; score: number };
    engagement: { committee_size: number; engaged: number; decision_makers: number; multi_threaded: boolean };
    committee: Committee[];
    org_chart: OrgNode[];
    content: { id: number; title: string; status: string; content_type: string }[];
    available_content: { id: number; title: string; status: string }[];
    plays: { key: string; description: string }[];
    deals: { id: number; name: string; status: string; value: number; mrr: number }[];
    bookings: { id: number; name: string; status: string; scheduled_at: string | null }[];
    signals: { type: string; weight: number; url: string | null; occurred_at: string | null }[];
};

function OrgChartNode({ node, depth }: { node: OrgNode; depth: number }) {
    return (
        <div style={{ marginLeft: depth * 20 }} className="py-1">
            <span className="font-medium">{node.name}</span>
            {node.title !== null && <span className="text-muted-foreground"> · {node.title}</span>}
            {node.is_decision_maker && (
                <Badge className="ml-2" variant="default">
                    Decision maker
                </Badge>
            )}
            {node.reports.map((report) => (
                <OrgChartNode key={report.id} node={report} depth={depth + 1} />
            ))}
        </div>
    );
}

const money = (cents: number) => `$${(cents / 100).toLocaleString('en-US', { minimumFractionDigits: 0 })}`;
const when = (iso: string | null) => (iso ? new Date(iso).toLocaleString() : '—');

export default function AccountReport({
    account,
    engagement,
    committee,
    org_chart,
    content,
    available_content,
    plays,
    deals,
    bookings,
    signals,
}: ReportProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Accounts', href: '/sales/accounts' },
        { title: account.company ?? 'Account', href: `/sales/accounts/${account.id}/report` },
    ];
    const { can } = usePermissions();
    const canManage = can('sales.accounts.manage');
    const flash = usePage().props.flash as { play_result?: { play: string; steps: PlayStep[] } } | undefined;
    const playResult = flash?.play_result ?? null;
    const [attachId, setAttachId] = useState('');
    const [managerFor, setManagerFor] = useState<Record<number, string>>({});

    const runPlay = (play: string) => router.post(route('sales.accounts.play', account.id), { play }, { preserveScroll: true });

    const attachContent = () => {
        if (attachId === '') return;
        router.post(
            route('sales.accounts.content.attach', account.id),
            { content_piece_id: Number(attachId) },
            { preserveScroll: true, onSuccess: () => setAttachId('') },
        );
    };

    const setManager = (contactId: number, managerId: string) => {
        setManagerFor((prev) => ({ ...prev, [contactId]: managerId }));
        router.patch(
            route('sales.accounts.manager', contactId),
            { reports_to_contact_id: managerId === 'none' ? null : Number(managerId) },
            { preserveScroll: true },
        );
    };

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

                {canManage && (
                    <div className="rounded-lg border p-4">
                        <h3 className="mb-1 text-sm font-medium">Orchestration plays</h3>
                        <p className="text-muted-foreground mb-3 text-sm">
                            A play coordinates sales and marketing steps against this account and reports exactly what it did. Nothing is sent to
                            anyone — drafts land as tasks, audiences are built for export.
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {plays.map((play) => (
                                <Button key={play.key} variant="outline" size="sm" title={play.description} onClick={() => runPlay(play.key)}>
                                    {play.key.replace(/_/g, ' ')}
                                </Button>
                            ))}
                        </div>
                        {playResult !== null && (
                            <div className="bg-muted/50 mt-3 rounded-md p-3 text-sm">
                                <p className="mb-1 font-medium">{playResult.play.replace(/_/g, ' ')} — what happened</p>
                                <ul className="list-disc space-y-1 pl-5">
                                    {playResult.steps.map((step, index) => (
                                        <li key={index}>{step.detail}</li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>
                )}

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
                                    {canManage && <th className="p-3 font-medium">Reports to</th>}
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
                                        {canManage && (
                                            <td className="p-3">
                                                <Select value={managerFor[person.id] ?? ''} onValueChange={(value) => setManager(person.id, value)}>
                                                    <SelectTrigger className="h-8 w-40">
                                                        <SelectValue placeholder="Set manager" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="none">No manager</SelectItem>
                                                        {committee
                                                            .filter((other) => other.id !== person.id)
                                                            .map((other) => (
                                                                <SelectItem key={other.id} value={String(other.id)}>
                                                                    {other.name}
                                                                </SelectItem>
                                                            ))}
                                                    </SelectContent>
                                                </Select>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <div>
                        <h3 className="mb-2 text-sm font-medium">Org chart</h3>
                        {org_chart.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No committee contacts yet.</p>
                        ) : (
                            <div className="rounded-lg border p-4 text-sm">
                                {org_chart.map((node) => (
                                    <OrgChartNode key={node.id} node={node} depth={0} />
                                ))}
                                {canManage && (
                                    <p className="text-muted-foreground mt-2 text-xs">
                                        Reporting lines come from the &quot;Reports to&quot; column above — set them as you learn the account.
                                    </p>
                                )}
                            </div>
                        )}
                    </div>

                    <div>
                        <h3 className="mb-2 text-sm font-medium">Account content</h3>
                        {content.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No content targeted at this account yet.</p>
                        ) : (
                            <ul className="space-y-1 rounded-lg border p-4 text-sm">
                                {content.map((piece) => (
                                    <li key={piece.id} className="flex items-center justify-between gap-2">
                                        <span className="font-medium">{piece.title}</span>
                                        <span className="text-muted-foreground text-xs">
                                            {piece.content_type.replace(/_/g, ' ')} · {piece.status}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                        {canManage && available_content.length > 0 && (
                            <div className="mt-2 flex items-center gap-2">
                                <Select value={attachId} onValueChange={setAttachId}>
                                    <SelectTrigger className="h-8 flex-1">
                                        <SelectValue placeholder="Target an existing piece at this account" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {available_content.map((piece) => (
                                            <SelectItem key={piece.id} value={String(piece.id)}>
                                                {piece.title} ({piece.status})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Button size="sm" variant="outline" disabled={attachId === ''} onClick={attachContent}>
                                    Attach
                                </Button>
                            </div>
                        )}
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
