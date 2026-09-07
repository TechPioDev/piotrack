import Heading from '@/components/heading';
import { PortalDeliverableActions, type PortalDeliverable } from '@/components/portal-deliverable-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Portal', href: '/portal' }];

type Kpi = {
    id: number;
    metric: string;
    target: number;
    actual: number;
    attainment: number;
    on_track: boolean;
    lower_is_better: boolean;
};

type Metrics = {
    projects: number;
    open_tasks: number;
    awaiting_approval: number;
    open_tickets: number;
    kpis: Kpi[];
    leads: { total: number; mqls: number; sqls: number; meetings: number };
    revenue: { mrr: number; arr: number; contract_value: number; ltv: number };
};

type Project = {
    id: number;
    name: string;
    description: string | null;
    status: string;
    health: string;
    starts_on: string | null;
    ends_on: string | null;
};

/** Revenue arrives in minor units. */
const money = (cents: number) => `$${(cents / 100).toFixed(2)}`;

const humanize = (value: string) => value.replace(/_/g, ' ');

function healthVariant(health: string): 'default' | 'secondary' | 'destructive' {
    if (health === 'off_track') return 'destructive';
    if (health === 'at_risk') return 'secondary';

    return 'default';
}

function approvalVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'rejected') return 'destructive';
    if (status === 'approved') return 'default';
    if (status === 'pending') return 'secondary';

    return 'outline';
}

/**
 * A KPI row. For a metric where lower is better — cost per lead, for instance —
 * the target is a ceiling, so being under it is the good outcome and the row
 * says so rather than showing a low percentage that reads like a failure.
 */
function KpiRow({ kpi }: { kpi: Kpi }) {
    return (
        <tr className="hover:bg-muted/40">
            <td className="p-3 font-medium">{kpi.metric}</td>
            <td className="p-3 text-center">
                {kpi.target}
                {kpi.lower_is_better && <span className="text-muted-foreground block text-xs">or lower</span>}
            </td>
            <td className="p-3 text-center">{kpi.actual}</td>
            <td className="p-3 text-center">
                <span className="text-muted-foreground">{kpi.attainment}%</span>
                <span className="text-muted-foreground block text-xs">{kpi.lower_is_better ? 'of the ceiling' : 'of the goal'}</span>
            </td>
            <td className="p-3">
                {kpi.on_track ? <Badge>On track</Badge> : <Badge variant="destructive">Off target</Badge>}
                <span className="text-muted-foreground block text-xs">{kpi.lower_is_better ? 'Lower is better' : 'Higher is better'}</span>
            </td>
        </tr>
    );
}

type PortalCampaign = {
    id: number;
    name: string;
    channel: string;
    status: string;
    sent_at: string | null;
    sent: number;
    opened: number;
    clicked: number;
};

type RoadmapItem = {
    id: number;
    title: string;
    status: string;
    priority: string | null;
    due_on: string | null;
};

type MeetingNote = {
    id: number;
    title: string | null;
    body: string | null;
    occurred_at: string | null;
};

export default function PortalDashboard({
    metrics,
    projects,
    deliverables,
    campaigns,
    roadmap,
    meeting_notes,
}: {
    metrics: Metrics;
    projects: Project[];
    deliverables: PortalDeliverable[];
    campaigns: PortalCampaign[];
    roadmap: RoadmapItem[];
    meeting_notes: MeetingNote[];
}) {
    const counters: { label: string; value: string | number }[] = [
        { label: 'Active projects', value: metrics.projects },
        { label: 'Open tasks', value: metrics.open_tasks },
        { label: 'Awaiting your approval', value: metrics.awaiting_approval },
        { label: 'Open support requests', value: metrics.open_tickets },
    ];

    const awaiting = deliverables.filter((deliverable) => deliverable.approval_status === 'pending');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Portal" />
            <div className="space-y-6 p-4">
                <Heading title="Your dashboard" description="Where your work stands, what it has produced, and anything waiting on you" />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {counters.map((counter) => (
                        <Card key={counter.label}>
                            <CardContent className="p-4">
                                <p className="text-muted-foreground text-sm">{counter.label}</p>
                                <p className="text-2xl font-semibold">{counter.value}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Waiting for your approval</h3>
                    {awaiting.length === 0 ? (
                        <p className="text-muted-foreground text-sm">Nothing needs your sign-off right now.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Deliverable</th>
                                        <th className="p-3 font-medium">Type</th>
                                        <th className="p-3 font-medium">Due</th>
                                        <th className="p-3 text-right font-medium">Your decision</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {awaiting.map((deliverable) => (
                                        <tr key={deliverable.id} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{deliverable.title}</td>
                                            <td className="text-muted-foreground p-3">{deliverable.type}</td>
                                            <td className="text-muted-foreground p-3">{deliverable.due_on ?? '—'}</td>
                                            <td className="p-3">
                                                <div className="flex justify-end">
                                                    <PortalDeliverableActions deliverable={deliverable} />
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
                    <h3 className="mb-2 text-sm font-medium">Results</h3>
                    <div className="grid gap-3 lg:grid-cols-2">
                        <Card>
                            <CardContent className="space-y-2 p-4">
                                <p className="text-sm font-medium">Leads</p>
                                <div className="grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <p className="text-muted-foreground">Total</p>
                                        <p className="text-xl font-semibold">{metrics.leads.total}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">Marketing qualified</p>
                                        <p className="text-xl font-semibold">{metrics.leads.mqls}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">Sales qualified</p>
                                        <p className="text-xl font-semibold">{metrics.leads.sqls}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">Meetings booked</p>
                                        <p className="text-xl font-semibold">{metrics.leads.meetings}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="space-y-2 p-4">
                                <p className="text-sm font-medium">Revenue</p>
                                <div className="grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <p className="text-muted-foreground">MRR</p>
                                        <p className="text-xl font-semibold">{money(metrics.revenue.mrr)}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">ARR</p>
                                        <p className="text-xl font-semibold">{money(metrics.revenue.arr)}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">Closed-won value</p>
                                        <p className="text-xl font-semibold">{money(metrics.revenue.contract_value)}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">Lifetime value</p>
                                        <p className="text-xl font-semibold">{money(metrics.revenue.ltv)}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Against your targets</h3>
                    {metrics.kpis.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No targets have been agreed yet.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Metric</th>
                                        <th className="p-3 text-center font-medium">Target</th>
                                        <th className="p-3 text-center font-medium">Actual</th>
                                        <th className="p-3 text-center font-medium">Attainment</th>
                                        <th className="p-3 font-medium">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {metrics.kpis.map((kpi) => (
                                        <KpiRow key={kpi.id} kpi={kpi} />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Your projects</h3>
                    {projects.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No projects yet.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Project</th>
                                        <th className="p-3 font-medium">Status</th>
                                        <th className="p-3 font-medium">Health</th>
                                        <th className="p-3 font-medium">Dates</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {projects.map((project) => (
                                        <tr key={project.id} className="hover:bg-muted/40 align-top">
                                            <td className="max-w-96 p-3">
                                                <p className="font-medium">{project.name}</p>
                                                {project.description && <p className="text-muted-foreground">{project.description}</p>}
                                            </td>
                                            <td className="p-3">
                                                <Badge variant="outline">{humanize(project.status)}</Badge>
                                            </td>
                                            <td className="p-3">
                                                <Badge variant={healthVariant(project.health)}>{humanize(project.health)}</Badge>
                                            </td>
                                            <td className="text-muted-foreground p-3">
                                                {project.starts_on ?? '—'} → {project.ends_on ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <div>
                    <div className="mb-4 flex items-center justify-between gap-2">
                        <h3 className="text-sm font-medium">Monthly performance report</h3>
                        <Button asChild size="sm" variant="outline">
                            <a href={route('portal.report')}>Download PDF</a>
                        </Button>
                    </div>

                    <h3 className="mb-2 text-sm font-medium">Campaign status</h3>
                    {campaigns.length === 0 ? (
                        <p className="text-muted-foreground mb-4 text-sm">No campaigns yet.</p>
                    ) : (
                        <div className="mb-4 overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Campaign</th>
                                        <th className="p-3 font-medium">Channel</th>
                                        <th className="p-3 font-medium">Status</th>
                                        <th className="p-3 text-center font-medium">Sent</th>
                                        <th className="p-3 text-center font-medium">Opened</th>
                                        <th className="p-3 text-center font-medium">Clicked</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {campaigns.map((campaign) => (
                                        <tr key={campaign.id}>
                                            <td className="p-3 font-medium">{campaign.name}</td>
                                            <td className="text-muted-foreground p-3">{campaign.channel}</td>
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

                    <h3 className="mb-2 text-sm font-medium">Strategy roadmap</h3>
                    {roadmap.length === 0 ? (
                        <p className="text-muted-foreground mb-4 text-sm">No roadmap items yet.</p>
                    ) : (
                        <ul className="mb-4 divide-y rounded-lg border">
                            {roadmap.map((item) => (
                                <li key={item.id} className="flex flex-wrap items-center gap-2 p-3 text-sm">
                                    <span className="font-medium">{item.title}</span>
                                    <Badge variant={item.status === 'done' ? 'default' : 'secondary'}>{item.status}</Badge>
                                    {item.priority && <span className="text-muted-foreground text-xs">priority {item.priority}</span>}
                                    {item.due_on && <span className="text-muted-foreground text-xs">due {item.due_on}</span>}
                                </li>
                            ))}
                        </ul>
                    )}

                    <h3 className="mb-2 text-sm font-medium">Meeting notes</h3>
                    {meeting_notes.length === 0 ? (
                        <p className="text-muted-foreground mb-4 text-sm">No shared meeting notes yet.</p>
                    ) : (
                        <ul className="mb-4 divide-y rounded-lg border">
                            {meeting_notes.map((note) => (
                                <li key={note.id} className="space-y-1 p-3 text-sm">
                                    <p className="font-medium">{note.title ?? 'Meeting'}</p>
                                    {note.body && <p className="text-muted-foreground whitespace-pre-wrap">{note.body}</p>}
                                    {note.occurred_at && (
                                        <p className="text-muted-foreground text-xs">{new Date(note.occurred_at).toLocaleString()}</p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}

                    <h3 className="mb-2 text-sm font-medium">Recent deliverables</h3>
                    {deliverables.length === 0 ? (
                        <p className="text-muted-foreground text-sm">Nothing has been shared with you yet.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Deliverable</th>
                                        <th className="p-3 font-medium">Type</th>
                                        <th className="p-3 font-medium">Approval</th>
                                        <th className="p-3 font-medium">Due</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {deliverables.map((deliverable) => (
                                        <tr key={deliverable.id} className="hover:bg-muted/40 align-top">
                                            <td className="p-3 font-medium">{deliverable.title}</td>
                                            <td className="text-muted-foreground p-3">{deliverable.type}</td>
                                            <td className="p-3">
                                                <Badge variant={approvalVariant(deliverable.approval_status)}>
                                                    {humanize(deliverable.approval_status)}
                                                </Badge>
                                                {deliverable.rejection_reason !== null && (
                                                    <p className="text-muted-foreground mt-1 max-w-64 text-xs">
                                                        Your feedback: {deliverable.rejection_reason}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="text-muted-foreground p-3">{deliverable.due_on ?? '—'}</td>
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
