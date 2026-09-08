import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

type Workflow = {
    id: number;
    name: string;
    description: string | null;
    trigger_type: string;
    trigger_config: Record<string, unknown> | null;
    status: string;
    enrolled_count: number;
    completed_count: number;
};

type Condition = { field: string; operator: string; value?: string; on_fail: string };

type Step = {
    id: number;
    position: number;
    action_type: string;
    action_config: Record<string, unknown> | null;
    delay_minutes: number;
    condition: Condition | null;
};

type ListOption = { id: number; name: string };

function describeConfig(config: Record<string, unknown> | null): string {
    if (!config) return '';
    return Object.entries(config)
        .map(([key, value]) => `${key}: ${String(value)}`)
        .join(' · ');
}

export default function WorkflowShow({
    workflow,
    steps,
    actions,
    lists,
    audiences,
    condition_fields,
    condition_operators,
}: {
    workflow: Workflow;
    steps: Step[];
    actions: string[];
    lists: ListOption[];
    audiences: ListOption[];
    condition_fields: string[];
    condition_operators: string[];
}) {
    const { can } = usePermissions();
    const canManage = can('marketing.automation.manage');
    const [open, setOpen] = useState(false);
    const form = useForm<{
        action_type: string;
        delay_minutes: string;
        subject: string;
        body: string;
        message: string;
        stage: string;
        delta: string;
        list_id: string;
        title: string;
        audience_id: string;
        condition_field: string;
        condition_operator: string;
        condition_value: string;
        condition_on_fail: string;
    }>({
        action_type: actions[0] ?? '',
        delay_minutes: '0',
        subject: '',
        body: '',
        message: '',
        stage: '',
        delta: '',
        list_id: '',
        title: '',
        audience_id: '',
        condition_field: '',
        condition_operator: 'equals',
        condition_value: '',
        condition_on_fail: 'skip',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Automation', href: '/marketing/automation' },
        { title: workflow.name, href: `/marketing/automation/${workflow.id}` },
    ];

    const addStep: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => {
            const config: Record<string, string | number> = {};
            if (data.subject) config.subject = data.subject;
            if (data.body) config.body = data.body;
            if (data.message) config.message = data.message;
            if (data.stage) config.stage = data.stage;
            if (data.delta) config.delta = Number(data.delta);
            if (data.list_id) config.list_id = data.list_id;
            if (data.title) config.title = data.title;
            if (data.audience_id) config.audience_id = Number(data.audience_id);
            return {
                action_type: data.action_type,
                delay_minutes: Number(data.delay_minutes),
                action_config: config,
                condition: data.condition_field
                    ? {
                          field: data.condition_field,
                          operator: data.condition_operator,
                          value: data.condition_value,
                          on_fail: data.condition_on_fail,
                      }
                    : null,
            };
        });
        form.post(route('marketing.automation.steps.add', workflow.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={workflow.name} />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <Heading title={workflow.name} description={workflow.description ?? workflow.trigger_type.replace(/_/g, ' ')} />
                        <Badge variant={workflow.status === 'active' ? 'default' : 'secondary'}>{workflow.status}</Badge>
                    </div>
                    {canManage && (
                        <Button
                            variant="outline"
                            onClick={() => router.post(route('marketing.automation.toggle', workflow.id), {}, { preserveScroll: true })}
                        >
                            {workflow.status === 'active' ? 'Pause' : 'Activate'}
                        </Button>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-muted-foreground text-sm">Trigger</p>
                            <p className="text-lg font-semibold capitalize">{workflow.trigger_type.replace(/_/g, ' ')}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-muted-foreground text-sm">Enrolled</p>
                            <p className="text-2xl font-semibold">{workflow.enrolled_count}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-muted-foreground text-sm">Completed</p>
                            <p className="text-2xl font-semibold">{workflow.completed_count}</p>
                        </CardContent>
                    </Card>
                </div>

                <div className="space-y-2">
                    <div className="flex items-center justify-between">
                        <h3 className="text-sm font-medium">Steps</h3>
                        {canManage && (
                            <Dialog open={open} onOpenChange={setOpen}>
                                <DialogTrigger asChild>
                                    <Button size="sm">Add step</Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogTitle>Add step</DialogTitle>
                                    <form onSubmit={addStep} className="space-y-3">
                                        <div className="grid grid-cols-2 gap-3">
                                            <div className="grid gap-1">
                                                <Label htmlFor="action_type">Action</Label>
                                                <Select value={form.data.action_type} onValueChange={(v) => form.setData('action_type', v)}>
                                                    <SelectTrigger id="action_type">
                                                        <SelectValue placeholder="Select an action" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {actions.map((action) => (
                                                            <SelectItem key={action} value={action}>
                                                                {action.replace(/_/g, ' ')}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="delay_minutes">Delay (minutes)</Label>
                                                <Input
                                                    id="delay_minutes"
                                                    type="number"
                                                    min="0"
                                                    value={form.data.delay_minutes}
                                                    onChange={(e) => form.setData('delay_minutes', e.target.value)}
                                                />
                                            </div>
                                        </div>

                                        <div className="space-y-3 rounded-md border p-3">
                                            <p className="text-muted-foreground text-xs">Configuration — fill only what the action needs</p>
                                            <div className="grid gap-1">
                                                <Label htmlFor="subject">Subject (send email)</Label>
                                                <Input
                                                    id="subject"
                                                    value={form.data.subject}
                                                    onChange={(e) => form.setData('subject', e.target.value)}
                                                />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="body">Body (send email)</Label>
                                                <Input id="body" value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="message">Message (notify)</Label>
                                                <Input
                                                    id="message"
                                                    value={form.data.message}
                                                    onChange={(e) => form.setData('message', e.target.value)}
                                                />
                                            </div>
                                            <div className="grid grid-cols-2 gap-3">
                                                <div className="grid gap-1">
                                                    <Label htmlFor="stage">Stage (change lifecycle)</Label>
                                                    <Input
                                                        id="stage"
                                                        value={form.data.stage}
                                                        onChange={(e) => form.setData('stage', e.target.value)}
                                                    />
                                                </div>
                                                <div className="grid gap-1">
                                                    <Label htmlFor="delta">Delta (change score)</Label>
                                                    <Input
                                                        id="delta"
                                                        type="number"
                                                        value={form.data.delta}
                                                        onChange={(e) => form.setData('delta', e.target.value)}
                                                    />
                                                </div>
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="list_id">List (add / remove from list)</Label>
                                                <Select value={form.data.list_id} onValueChange={(v) => form.setData('list_id', v)}>
                                                    <SelectTrigger id="list_id">
                                                        <SelectValue placeholder="None" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {lists.map((list) => (
                                                            <SelectItem key={list.id} value={String(list.id)}>
                                                                {list.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="title">Title (create task)</Label>
                                                <Input id="title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                                            </div>
                                            {audiences.length > 0 && (
                                                <div className="grid gap-1">
                                                    <Label htmlFor="audience_id">Audience (add to audience)</Label>
                                                    <Select value={form.data.audience_id} onValueChange={(v) => form.setData('audience_id', v)}>
                                                        <SelectTrigger id="audience_id">
                                                            <SelectValue placeholder="None" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {audiences.map((audience) => (
                                                                <SelectItem key={audience.id} value={String(audience.id)}>
                                                                    {audience.name}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                    <p className="text-muted-foreground text-xs">
                                                        List-backed retargeting audiences only — rule-based membership stays rule-owned.
                                                    </p>
                                                </div>
                                            )}
                                        </div>

                                        <div className="space-y-3 rounded-md border p-3">
                                            <p className="text-muted-foreground text-xs">
                                                Only run when… (optional) — the check runs against the contact's real record when the step is due
                                            </p>
                                            <div className="grid grid-cols-2 gap-3">
                                                <div className="grid gap-1">
                                                    <Label htmlFor="cond-field">Field</Label>
                                                    <Select
                                                        value={form.data.condition_field}
                                                        onValueChange={(v) => form.setData('condition_field', v === 'none' ? '' : v)}
                                                    >
                                                        <SelectTrigger id="cond-field">
                                                            <SelectValue placeholder="No condition" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="none">No condition</SelectItem>
                                                            {condition_fields.map((field) => (
                                                                <SelectItem key={field} value={field}>
                                                                    {field.replace(/_/g, ' ')}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div className="grid gap-1">
                                                    <Label htmlFor="cond-op">Operator</Label>
                                                    <Select
                                                        value={form.data.condition_operator}
                                                        onValueChange={(v) => form.setData('condition_operator', v)}
                                                    >
                                                        <SelectTrigger id="cond-op">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {condition_operators.map((op) => (
                                                                <SelectItem key={op} value={op}>
                                                                    {op.replace(/_/g, ' ')}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                            <div className="grid grid-cols-2 gap-3">
                                                <div className="grid gap-1">
                                                    <Label htmlFor="cond-value">Value</Label>
                                                    <Input
                                                        id="cond-value"
                                                        value={form.data.condition_value}
                                                        onChange={(e) => form.setData('condition_value', e.target.value)}
                                                        placeholder="e.g. yes / 50 / customer"
                                                    />
                                                </div>
                                                <div className="grid gap-1">
                                                    <Label htmlFor="cond-fail">When it fails</Label>
                                                    <Select
                                                        value={form.data.condition_on_fail}
                                                        onValueChange={(v) => form.setData('condition_on_fail', v)}
                                                    >
                                                        <SelectTrigger id="cond-fail">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="skip">Skip this step</SelectItem>
                                                            <SelectItem value="exit">Exit the workflow</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                        </div>

                                        <DialogFooter>
                                            <Button type="submit" disabled={form.processing}>
                                                Add step
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        )}
                    </div>

                    {steps.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No steps yet. Add a step to define what this workflow does.</p>
                    ) : (
                        <div className="space-y-2">
                            {steps.map((step) => (
                                <div key={step.id} className="flex items-center justify-between gap-3 rounded-lg border p-3">
                                    <div className="flex items-center gap-3">
                                        <span className="bg-muted text-muted-foreground flex size-7 items-center justify-center rounded-full text-xs font-medium">
                                            {step.position}
                                        </span>
                                        <div>
                                            <p className="text-sm font-medium capitalize">{step.action_type.replace(/_/g, ' ')}</p>
                                            <p className="text-muted-foreground text-xs">
                                                Delay {step.delay_minutes} min
                                                {describeConfig(step.action_config) && ` · ${describeConfig(step.action_config)}`}
                                            </p>
                                            {step.condition && (
                                                <p className="text-muted-foreground text-xs">
                                                    Only when {step.condition.field.replace(/_/g, ' ')} {step.condition.operator.replace(/_/g, ' ')}{' '}
                                                    {step.condition.value ?? ''} · else {step.condition.on_fail === 'exit' ? 'exit workflow' : 'skip'}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                    {canManage && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="text-destructive"
                                            onClick={() => router.delete(route('marketing.automation.steps.delete', [workflow.id, step.id]))}
                                        >
                                            Delete
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
