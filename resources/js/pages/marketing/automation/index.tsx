import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Automation', href: '/marketing/automation' }];

type Workflow = {
    id: number;
    name: string;
    trigger_type: string;
    status: string;
    steps_count: number;
    enrolled_count: number;
    completed_count: number;
};

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-20 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

export default function Automation({
    workflows,
    triggers,
    verticals,
}: {
    workflows: Workflow[];
    triggers: string[];
    verticals: { id: number; name: string }[];
}) {
    const { can } = usePermissions();
    const canManage = can('marketing.automation.manage');
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; description: string; trigger_type: string; vertical_id: string }>({
        name: '',
        description: '',
        trigger_type: triggers[0] ?? '',
        vertical_id: '',
    });

    const create: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, vertical_id: data.vertical_id === '' ? null : Number(data.vertical_id) }));
        form.post(route('marketing.automation.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Automation" />
            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between gap-2">
                    <Heading title="Automation" description={`${workflows.length} workflows`} />
                    {canManage && (
                        <Dialog open={open} onOpenChange={setOpen}>
                            <DialogTrigger asChild>
                                <Button>New workflow</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>New workflow</DialogTitle>
                                <form onSubmit={create} className="space-y-3">
                                    <div className="grid gap-1">
                                        <Label htmlFor="name">Name</Label>
                                        <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                                        <InputError message={form.errors.name} />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="description">Description</Label>
                                        <textarea
                                            id="description"
                                            className={textareaClass}
                                            value={form.data.description}
                                            onChange={(e) => form.setData('description', e.target.value)}
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="trigger_type">Trigger</Label>
                                        <Select value={form.data.trigger_type} onValueChange={(v) => form.setData('trigger_type', v)}>
                                            <SelectTrigger id="trigger_type">
                                                <SelectValue placeholder="Select a trigger" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {triggers.map((trigger) => (
                                                    <SelectItem key={trigger} value={trigger}>
                                                        {trigger.replace(/_/g, ' ')}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={form.errors.trigger_type} />
                                    </div>
                                    {verticals.length > 0 && (
                                        <div className="grid gap-1">
                                            <Label htmlFor="workflow_vertical">Vertical (optional)</Label>
                                            <Select value={form.data.vertical_id} onValueChange={(v) => form.setData('vertical_id', v)}>
                                                <SelectTrigger id="workflow_vertical">
                                                    <SelectValue placeholder="Not bound to one vertical" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {verticals.map((vertical) => (
                                                        <SelectItem key={vertical.id} value={String(vertical.id)}>
                                                            {vertical.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={form.errors.vertical_id} />
                                        </div>
                                    )}
                                    <DialogFooter>
                                        <Button type="submit" disabled={form.processing}>
                                            Create
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>

                {workflows.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No workflows yet. Create one to automate contact journeys.</p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">Name</th>
                                    <th className="p-3 font-medium">Trigger</th>
                                    <th className="p-3 font-medium">Status</th>
                                    <th className="p-3 text-center font-medium">Steps</th>
                                    <th className="p-3 text-center font-medium">Enrolled</th>
                                    <th className="p-3 text-center font-medium">Completed</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {workflows.map((workflow) => (
                                    <tr key={workflow.id} className="hover:bg-muted/40">
                                        <td className="p-3">
                                            <Link href={route('marketing.automation.show', workflow.id)} className="font-medium hover:underline">
                                                {workflow.name}
                                            </Link>
                                        </td>
                                        <td className="text-muted-foreground p-3">{workflow.trigger_type.replace(/_/g, ' ')}</td>
                                        <td className="p-3">
                                            <Badge variant={workflow.status === 'active' ? 'default' : 'secondary'}>{workflow.status}</Badge>
                                        </td>
                                        <td className="p-3 text-center">{workflow.steps_count}</td>
                                        <td className="p-3 text-center">{workflow.enrolled_count}</td>
                                        <td className="p-3 text-center">{workflow.completed_count}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
