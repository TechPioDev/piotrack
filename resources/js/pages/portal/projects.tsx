import Heading from '@/components/heading';
import { PortalDeliverableActions, type PortalDeliverable } from '@/components/portal-deliverable-actions';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Portal', href: '/portal' },
    { title: 'Projects', href: '/portal/projects' },
];

type Project = {
    id: number;
    name: string;
    description: string | null;
    status: string;
    health: string;
    starts_on: string | null;
    ends_on: string | null;
};

type Task = {
    id: number;
    project_id: number;
    title: string;
    status: string;
    priority: string;
    due_on: string | null;
};

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

export default function PortalProjects({ projects, tasks, deliverables }: { projects: Project[]; tasks: Task[]; deliverables: PortalDeliverable[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Projects" />
            <div className="space-y-6 p-4">
                <Heading title="Your projects" description="The work underway, what is left to do and anything waiting on your sign-off" />

                {projects.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No projects yet.</p>
                ) : (
                    <div className="space-y-4">
                        {projects.map((project) => {
                            const projectTasks = tasks.filter((task) => task.project_id === project.id);
                            const projectDeliverables = deliverables.filter((deliverable) => deliverable.project_id === project.id);
                            const done = projectTasks.filter((task) => task.status === 'done').length;

                            return (
                                <Card key={project.id}>
                                    <CardContent className="space-y-4 p-4">
                                        <div className="space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="font-medium">{project.name}</p>
                                                <Badge variant="outline">{humanize(project.status)}</Badge>
                                                <Badge variant={healthVariant(project.health)}>{humanize(project.health)}</Badge>
                                            </div>
                                            {project.description && <p className="text-muted-foreground text-sm">{project.description}</p>}
                                            <p className="text-muted-foreground text-sm">
                                                {project.starts_on ?? '—'} → {project.ends_on ?? '—'} · {done} of {projectTasks.length} tasks done
                                            </p>
                                        </div>

                                        <div>
                                            <h4 className="mb-2 text-sm font-medium">Tasks</h4>
                                            {projectTasks.length === 0 ? (
                                                <p className="text-muted-foreground text-sm">No tasks on this project.</p>
                                            ) : (
                                                <div className="overflow-x-auto rounded-lg border">
                                                    <table className="w-full text-left text-sm">
                                                        <thead className="bg-muted/50 text-muted-foreground">
                                                            <tr>
                                                                <th className="p-3">Task</th>
                                                                <th className="p-3">Priority</th>
                                                                <th className="p-3">Due</th>
                                                                <th className="p-3">Status</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody className="divide-y">
                                                            {projectTasks.map((task) => (
                                                                <tr key={task.id} className="hover:bg-muted/40">
                                                                    <td className="p-3 font-medium">{task.title}</td>
                                                                    <td className="text-muted-foreground p-3">{task.priority}</td>
                                                                    <td className="text-muted-foreground p-3">{task.due_on ?? '—'}</td>
                                                                    <td className="p-3">
                                                                        <Badge variant="outline">{humanize(task.status)}</Badge>
                                                                    </td>
                                                                </tr>
                                                            ))}
                                                        </tbody>
                                                    </table>
                                                </div>
                                            )}
                                        </div>

                                        <div>
                                            <h4 className="mb-2 text-sm font-medium">Deliverables</h4>
                                            {projectDeliverables.length === 0 ? (
                                                <p className="text-muted-foreground text-sm">Nothing has been shared on this project yet.</p>
                                            ) : (
                                                <div className="overflow-x-auto rounded-lg border">
                                                    <table className="w-full text-left text-sm">
                                                        <thead className="bg-muted/50 text-muted-foreground">
                                                            <tr>
                                                                <th className="p-3">Deliverable</th>
                                                                <th className="p-3">Type</th>
                                                                <th className="p-3">Approval</th>
                                                                <th className="p-3">Due</th>
                                                                <th className="p-3 text-right">Your decision</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody className="divide-y">
                                                            {projectDeliverables.map((deliverable) => (
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
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
