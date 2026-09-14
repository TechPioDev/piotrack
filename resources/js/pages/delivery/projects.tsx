import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Projects', href: '/projects' }];

type Progress = {
    tasks: number;
    done: number;
    overdue: number;
    completion: number;
    deliverables: number;
    awaiting_approval: number;
};

type Member = {
    id: number;
    user: string | null;
    role: string;
};

type Project = {
    id: number;
    name: string;
    description: string | null;
    status: string;
    health: string;
    starts_on: string | null;
    ends_on: string | null;
    progress: Progress;
    members: Member[];
};

type Sprint = {
    id: number;
    project_id: number;
    name: string;
    goal: string | null;
    status: string;
    starts_on: string | null;
    ends_on: string | null;
};

type Task = {
    id: number;
    project_id: number;
    sprint_id: number | null;
    title: string;
    status: string;
    priority: string;
    due_on: string | null;
};

type Deliverable = {
    id: number;
    project_id: number;
    title: string;
    type: string;
    status: string;
    approval_status: string;
    client_visible: boolean;
    due_on: string | null;
    rejection_reason: string | null;
};

type User = { id: number; name: string };

const taskStatuses = ['todo', 'in_progress', 'review', 'done'];
const projectStatuses = ['planning', 'active', 'on_hold', 'completed'];
const healths = ['on_track', 'at_risk', 'off_track'];
const deliverableTypes = ['document', 'content', 'website', 'campaign', 'report', 'design'];
const priorities = ['low', 'normal', 'high'];

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

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

function NewProjectDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; description: string; status: string; starts_on: string; ends_on: string }>({
        name: '',
        description: '',
        status: 'planning',
        starts_on: '',
        ends_on: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('projects.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>New project</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New project</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="project_name">Name</Label>
                        <Input id="project_name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="project_description">Description</Label>
                        <textarea
                            id="project_description"
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="grid grid-cols-3 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="project_status">Status</Label>
                            <Select value={form.data.status} onValueChange={(v) => form.setData('status', v)}>
                                <SelectTrigger id="project_status">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {projectStatuses.map((status) => (
                                        <SelectItem key={status} value={status}>
                                            {humanize(status)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.status} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="project_starts">Starts</Label>
                            <Input
                                id="project_starts"
                                type="date"
                                value={form.data.starts_on}
                                onChange={(e) => form.setData('starts_on', e.target.value)}
                            />
                            <InputError message={form.errors.starts_on} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="project_ends">Ends</Label>
                            <Input
                                id="project_ends"
                                type="date"
                                value={form.data.ends_on}
                                onChange={(e) => form.setData('ends_on', e.target.value)}
                            />
                            <InputError message={form.errors.ends_on} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Create
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AssignMemberDialog({ project, users, roles }: { project: Project; users: User[]; roles: string[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ user_id: string; role: string }>({ user_id: '', role: roles[0] ?? 'strategist' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({ user_id: Number(data.user_id), role: data.role }));
        form.post(route('projects.members.store', project.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Staff a role
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Staff a delivery role on {project.name}</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor={`member_user_${project.id}`}>Team member</Label>
                        <Select value={form.data.user_id} onValueChange={(v) => form.setData('user_id', v)}>
                            <SelectTrigger id={`member_user_${project.id}`}>
                                <SelectValue placeholder="Choose a person" />
                            </SelectTrigger>
                            <SelectContent>
                                {users.map((user) => (
                                    <SelectItem key={user.id} value={String(user.id)}>
                                        {user.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.user_id} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`member_role_${project.id}`}>Delivery role</Label>
                        <Select value={form.data.role} onValueChange={(v) => form.setData('role', v)}>
                            <SelectTrigger id={`member_role_${project.id}`}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((role) => (
                                    <SelectItem key={role} value={role}>
                                        {humanize(role)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.role} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing || form.data.user_id === ''}>
                            Assign
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function NewSprintDialog({ project }: { project: Project }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; goal: string; starts_on: string; ends_on: string }>({
        name: '',
        goal: '',
        starts_on: '',
        ends_on: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('projects.sprints.store', project.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    New sprint
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New sprint on {project.name}</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor={`sprint_name_${project.id}`}>Name</Label>
                        <Input id={`sprint_name_${project.id}`} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`sprint_goal_${project.id}`}>Goal</Label>
                        <textarea
                            id={`sprint_goal_${project.id}`}
                            className={textareaClass}
                            value={form.data.goal}
                            onChange={(e) => form.setData('goal', e.target.value)}
                        />
                        <InputError message={form.errors.goal} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor={`sprint_starts_${project.id}`}>Starts</Label>
                            <Input
                                id={`sprint_starts_${project.id}`}
                                type="date"
                                value={form.data.starts_on}
                                onChange={(e) => form.setData('starts_on', e.target.value)}
                            />
                            <InputError message={form.errors.starts_on} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor={`sprint_ends_${project.id}`}>Ends</Label>
                            <Input
                                id={`sprint_ends_${project.id}`}
                                type="date"
                                value={form.data.ends_on}
                                onChange={(e) => form.setData('ends_on', e.target.value)}
                            />
                            <InputError message={form.errors.ends_on} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Create
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function NewTaskDialog({ project, sprints, users }: { project: Project; sprints: Sprint[]; users: User[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ title: string; description: string; sprint_id: string; assignee_id: string; priority: string; due_on: string }>({
        title: '',
        description: '',
        sprint_id: '',
        assignee_id: '',
        priority: 'normal',
        due_on: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            sprint_id: data.sprint_id === '' ? null : Number(data.sprint_id),
            assignee_id: data.assignee_id === '' ? null : Number(data.assignee_id),
        }));
        form.post(route('projects.tasks.store', project.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Add task
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Add a task to {project.name}</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor={`task_title_${project.id}`}>Title</Label>
                        <Input id={`task_title_${project.id}`} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                        <InputError message={form.errors.title} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`task_description_${project.id}`}>Description</Label>
                        <textarea
                            id={`task_description_${project.id}`}
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor={`task_sprint_${project.id}`}>Sprint</Label>
                            <Select value={form.data.sprint_id} onValueChange={(v) => form.setData('sprint_id', v)}>
                                <SelectTrigger id={`task_sprint_${project.id}`}>
                                    <SelectValue placeholder="Backlog" />
                                </SelectTrigger>
                                <SelectContent>
                                    {sprints.map((sprint) => (
                                        <SelectItem key={sprint.id} value={String(sprint.id)}>
                                            {sprint.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.sprint_id} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor={`task_assignee_${project.id}`}>Assignee</Label>
                            <Select value={form.data.assignee_id} onValueChange={(v) => form.setData('assignee_id', v)}>
                                <SelectTrigger id={`task_assignee_${project.id}`}>
                                    <SelectValue placeholder="Unassigned" />
                                </SelectTrigger>
                                <SelectContent>
                                    {users.map((user) => (
                                        <SelectItem key={user.id} value={String(user.id)}>
                                            {user.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.assignee_id} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor={`task_priority_${project.id}`}>Priority</Label>
                            <Select value={form.data.priority} onValueChange={(v) => form.setData('priority', v)}>
                                <SelectTrigger id={`task_priority_${project.id}`}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {priorities.map((priority) => (
                                        <SelectItem key={priority} value={priority}>
                                            {priority}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.priority} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor={`task_due_${project.id}`}>Due</Label>
                            <Input
                                id={`task_due_${project.id}`}
                                type="date"
                                value={form.data.due_on}
                                onChange={(e) => form.setData('due_on', e.target.value)}
                            />
                            <InputError message={form.errors.due_on} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Add
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function NewDeliverableDialog({ project }: { project: Project }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ title: string; type: string; notes: string; due_on: string; client_visible: boolean }>({
        title: '',
        type: 'document',
        notes: '',
        due_on: '',
        client_visible: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('projects.deliverables.store', project.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Add deliverable
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Add a deliverable to {project.name}</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor={`deliverable_title_${project.id}`}>Title</Label>
                        <Input
                            id={`deliverable_title_${project.id}`}
                            value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)}
                        />
                        <InputError message={form.errors.title} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor={`deliverable_type_${project.id}`}>Type</Label>
                            <Select value={form.data.type} onValueChange={(v) => form.setData('type', v)}>
                                <SelectTrigger id={`deliverable_type_${project.id}`}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {deliverableTypes.map((type) => (
                                        <SelectItem key={type} value={type}>
                                            {type}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.type} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor={`deliverable_due_${project.id}`}>Due</Label>
                            <Input
                                id={`deliverable_due_${project.id}`}
                                type="date"
                                value={form.data.due_on}
                                onChange={(e) => form.setData('due_on', e.target.value)}
                            />
                            <InputError message={form.errors.due_on} />
                        </div>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`deliverable_notes_${project.id}`}>Notes</Label>
                        <textarea
                            id={`deliverable_notes_${project.id}`}
                            className={textareaClass}
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                    <label className="flex items-start gap-3 rounded-lg border p-3" htmlFor={`deliverable_visible_${project.id}`}>
                        <Checkbox
                            id={`deliverable_visible_${project.id}`}
                            checked={form.data.client_visible}
                            onCheckedChange={(v) => form.setData('client_visible', v === true)}
                        />
                        <span className="text-sm">
                            <span className="font-medium">Visible to the client</span>
                            <span className="text-muted-foreground block">
                                The client portal only ever shows deliverables marked visible. Submitting for approval sets this automatically.
                            </span>
                        </span>
                    </label>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Add
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Rejection carries a mandatory reason, so it needs a dialog where approval does
 * not. Both sit side by side with the same weight — an approval is a decision,
 * not the default outcome.
 */
function RejectDeliverableDialog({ deliverable }: { deliverable: Deliverable }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ reason: string }>({ reason: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('projects.deliverables.reject', deliverable.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Reject
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Reject {deliverable.title}</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <p className="text-muted-foreground text-sm">
                        The deliverable returns to in progress so the work can be revised. Nothing is deleted.
                    </p>
                    <div className="grid gap-1">
                        <Label htmlFor={`reject_reason_${deliverable.id}`}>Reason (required)</Label>
                        <textarea
                            id={`reject_reason_${deliverable.id}`}
                            className={textareaClass}
                            value={form.data.reason}
                            onChange={(e) => form.setData('reason', e.target.value)}
                            placeholder="What needs to change before this can be approved?"
                        />
                        <InputError message={form.errors.reason} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing || form.data.reason.trim() === ''}>
                            Send back for revision
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ProjectStatusControls({ project }: { project: Project }) {
    const setStatus = (status: string) => router.patch(route('projects.update', project.id), { status }, { preserveScroll: true });
    const setHealth = (health: string) => router.patch(route('projects.update', project.id), { health }, { preserveScroll: true });

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Select value={project.status} onValueChange={setStatus}>
                <SelectTrigger className="w-40" aria-label={`Status of ${project.name}`}>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {projectStatuses.map((status) => (
                        <SelectItem key={status} value={status}>
                            {humanize(status)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Select value={project.health} onValueChange={setHealth}>
                <SelectTrigger className="w-40" aria-label={`Health of ${project.name}`}>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {healths.map((health) => (
                        <SelectItem key={health} value={health}>
                            {humanize(health)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

export default function DeliveryProjects({
    projects,
    sprints,
    tasks,
    deliverables,
    members,
    roles,
}: {
    projects: Project[];
    sprints: Sprint[];
    tasks: Task[];
    deliverables: Deliverable[];
    members: User[];
    roles: string[];
}) {
    const { can } = usePermissions();
    const canManage = can('projects.manage');
    const canApprove = can('projects.approve');

    const setTaskStatus = (task: Task, status: string) => router.patch(route('projects.tasks.update', task.id), { status }, { preserveScroll: true });
    const submitDeliverable = (id: number) => router.post(route('projects.deliverables.submit', id), {}, { preserveScroll: true });
    const approveDeliverable = (id: number) => router.post(route('projects.deliverables.approve', id), {}, { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Projects" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading title="Projects" description="Delivery work, the team staffed on it, sprints, tasks and client approvals" />
                    {canManage && <NewProjectDialog />}
                </div>

                {projects.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No projects yet. Create one to start tracking delivery.</p>
                ) : (
                    <div className="space-y-4">
                        {projects.map((project) => {
                            const projectSprints = sprints.filter((sprint) => sprint.project_id === project.id);
                            const projectTasks = tasks.filter((task) => task.project_id === project.id);
                            const projectDeliverables = deliverables.filter((deliverable) => deliverable.project_id === project.id);

                            return (
                                <Card key={project.id}>
                                    <CardContent className="space-y-4 p-4">
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div className="min-w-0 space-y-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="font-medium">{project.name}</p>
                                                    <Badge variant="outline">{humanize(project.status)}</Badge>
                                                    <Badge variant={healthVariant(project.health)}>{humanize(project.health)}</Badge>
                                                </div>
                                                {project.description && <p className="text-muted-foreground text-sm">{project.description}</p>}
                                                <p className="text-muted-foreground text-sm">
                                                    {project.starts_on ?? 'No start date'} — {project.ends_on ?? 'No end date'}
                                                </p>
                                            </div>
                                            {canManage && (
                                                <div className="flex shrink-0 flex-wrap items-center gap-2">
                                                    <ProjectStatusControls project={project} />
                                                    <AssignMemberDialog project={project} users={members} roles={roles} />
                                                    <NewSprintDialog project={project} />
                                                    <NewTaskDialog project={project} sprints={projectSprints} users={members} />
                                                    <NewDeliverableDialog project={project} />
                                                </div>
                                            )}
                                        </div>

                                        <div className="space-y-2 rounded-lg border p-3">
                                            <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                                                <span className="font-medium">Progress</span>
                                                <span className="text-muted-foreground">
                                                    {project.progress.done} of {project.progress.tasks} tasks done · {project.progress.deliverables}{' '}
                                                    deliverables · {project.progress.awaiting_approval} awaiting approval
                                                </span>
                                            </div>
                                            <div className="bg-muted h-2 overflow-hidden rounded-full">
                                                <div
                                                    className="bg-primary h-full rounded-full"
                                                    style={{ width: `${project.progress.completion}%` }}
                                                />
                                            </div>
                                            <div className="flex flex-wrap items-center gap-3 text-sm">
                                                <span className="text-muted-foreground">{project.progress.completion}% complete</span>
                                                {project.progress.overdue > 0 && (
                                                    <Badge variant="destructive">{project.progress.overdue} overdue</Badge>
                                                )}
                                            </div>
                                        </div>

                                        <div>
                                            <h4 className="mb-2 text-sm font-medium">Team</h4>
                                            {project.members.length === 0 ? (
                                                <p className="text-muted-foreground text-sm">Nobody is staffed on this project yet.</p>
                                            ) : (
                                                <div className="overflow-x-auto rounded-lg border">
                                                    <table className="w-full text-left text-sm">
                                                        <thead className="bg-muted/50 text-muted-foreground">
                                                            <tr>
                                                                <th className="p-3">Member</th>
                                                                <th className="p-3">Delivery role</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody className="divide-y">
                                                            {project.members.map((member) => (
                                                                <tr key={member.id} className="hover:bg-muted/40">
                                                                    <td className="p-3 font-medium">{member.user ?? 'Removed user'}</td>
                                                                    <td className="p-3">
                                                                        <Badge variant="outline">{humanize(member.role)}</Badge>
                                                                    </td>
                                                                </tr>
                                                            ))}
                                                        </tbody>
                                                    </table>
                                                </div>
                                            )}
                                        </div>

                                        <div>
                                            <h4 className="mb-2 text-sm font-medium">Sprints</h4>
                                            {projectSprints.length === 0 ? (
                                                <p className="text-muted-foreground text-sm">No sprints on this project.</p>
                                            ) : (
                                                <div className="overflow-x-auto rounded-lg border">
                                                    <table className="w-full text-left text-sm">
                                                        <thead className="bg-muted/50 text-muted-foreground">
                                                            <tr>
                                                                <th className="p-3">Sprint</th>
                                                                <th className="p-3">Goal</th>
                                                                <th className="p-3">Status</th>
                                                                <th className="p-3">Window</th>
                                                                <th className="p-3 text-right">Tasks</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody className="divide-y">
                                                            {projectSprints.map((sprint) => (
                                                                <tr key={sprint.id} className="hover:bg-muted/40 align-top">
                                                                    <td className="p-3 font-medium">{sprint.name}</td>
                                                                    <td className="text-muted-foreground max-w-80 p-3">{sprint.goal ?? '—'}</td>
                                                                    <td className="p-3">
                                                                        <Badge variant="outline">{humanize(sprint.status)}</Badge>
                                                                    </td>
                                                                    <td className="text-muted-foreground p-3">
                                                                        {sprint.starts_on ?? '—'} → {sprint.ends_on ?? '—'}
                                                                    </td>
                                                                    <td className="p-3 text-right">
                                                                        {projectTasks.filter((task) => task.sprint_id === sprint.id).length}
                                                                    </td>
                                                                </tr>
                                                            ))}
                                                        </tbody>
                                                    </table>
                                                </div>
                                            )}
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
                                                                <th className="p-3">Sprint</th>
                                                                <th className="p-3">Priority</th>
                                                                <th className="p-3">Due</th>
                                                                <th className="p-3">Status</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody className="divide-y">
                                                            {projectTasks.map((task) => (
                                                                <tr key={task.id} className="hover:bg-muted/40">
                                                                    <td className="p-3 font-medium">{task.title}</td>
                                                                    <td className="text-muted-foreground p-3">
                                                                        {projectSprints.find((sprint) => sprint.id === task.sprint_id)?.name ??
                                                                            'Backlog'}
                                                                    </td>
                                                                    <td className="p-3">
                                                                        <Badge variant={task.priority === 'high' ? 'destructive' : 'outline'}>
                                                                            {task.priority}
                                                                        </Badge>
                                                                    </td>
                                                                    <td className="text-muted-foreground p-3">{task.due_on ?? '—'}</td>
                                                                    <td className="p-3">
                                                                        {canManage ? (
                                                                            <Select value={task.status} onValueChange={(v) => setTaskStatus(task, v)}>
                                                                                <SelectTrigger
                                                                                    className="w-36"
                                                                                    aria-label={`Status of ${task.title}`}
                                                                                >
                                                                                    <SelectValue />
                                                                                </SelectTrigger>
                                                                                <SelectContent>
                                                                                    {taskStatuses.map((status) => (
                                                                                        <SelectItem key={status} value={status}>
                                                                                            {humanize(status)}
                                                                                        </SelectItem>
                                                                                    ))}
                                                                                </SelectContent>
                                                                            </Select>
                                                                        ) : (
                                                                            <Badge variant="outline">{humanize(task.status)}</Badge>
                                                                        )}
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
                                                <p className="text-muted-foreground text-sm">No deliverables on this project.</p>
                                            ) : (
                                                <div className="overflow-x-auto rounded-lg border">
                                                    <table className="w-full text-left text-sm">
                                                        <thead className="bg-muted/50 text-muted-foreground">
                                                            <tr>
                                                                <th className="p-3">Deliverable</th>
                                                                <th className="p-3">Type</th>
                                                                <th className="p-3">Status</th>
                                                                <th className="p-3">Approval</th>
                                                                <th className="p-3">Client visibility</th>
                                                                <th className="p-3">Due</th>
                                                                {(canManage || canApprove) && <th className="p-3 text-right">Actions</th>}
                                                            </tr>
                                                        </thead>
                                                        <tbody className="divide-y">
                                                            {projectDeliverables.map((deliverable) => (
                                                                <tr key={deliverable.id} className="hover:bg-muted/40 align-top">
                                                                    <td className="p-3 font-medium">{deliverable.title}</td>
                                                                    <td className="text-muted-foreground p-3">{deliverable.type}</td>
                                                                    <td className="p-3">
                                                                        <Badge variant="outline">{humanize(deliverable.status)}</Badge>
                                                                    </td>
                                                                    <td className="p-3">
                                                                        <Badge variant={approvalVariant(deliverable.approval_status)}>
                                                                            {humanize(deliverable.approval_status)}
                                                                        </Badge>
                                                                        {deliverable.rejection_reason !== null && (
                                                                            <p className="text-destructive mt-1 max-w-64 text-xs">
                                                                                {deliverable.rejection_reason}
                                                                            </p>
                                                                        )}
                                                                    </td>
                                                                    <td className="p-3">
                                                                        {deliverable.client_visible ? (
                                                                            <span className="flex items-center gap-1 font-medium">
                                                                                <Eye className="size-4" aria-hidden="true" />
                                                                                Visible to client
                                                                            </span>
                                                                        ) : (
                                                                            <span className="text-muted-foreground flex items-center gap-1">
                                                                                <EyeOff className="size-4" aria-hidden="true" />
                                                                                Internal only
                                                                            </span>
                                                                        )}
                                                                    </td>
                                                                    <td className="text-muted-foreground p-3">{deliverable.due_on ?? '—'}</td>
                                                                    {(canManage || canApprove) && (
                                                                        <td className="p-3">
                                                                            <div className="flex flex-wrap justify-end gap-2">
                                                                                {canManage && deliverable.approval_status !== 'pending' && (
                                                                                    <Button
                                                                                        size="sm"
                                                                                        variant="secondary"
                                                                                        onClick={() => submitDeliverable(deliverable.id)}
                                                                                    >
                                                                                        Submit for approval
                                                                                    </Button>
                                                                                )}
                                                                                {canApprove && deliverable.approval_status === 'pending' && (
                                                                                    <>
                                                                                        <Button
                                                                                            size="sm"
                                                                                            variant="outline"
                                                                                            onClick={() => approveDeliverable(deliverable.id)}
                                                                                        >
                                                                                            Approve
                                                                                        </Button>
                                                                                        <RejectDeliverableDialog deliverable={deliverable} />
                                                                                    </>
                                                                                )}
                                                                            </div>
                                                                        </td>
                                                                    )}
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
