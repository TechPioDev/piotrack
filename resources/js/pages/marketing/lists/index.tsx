import { EmptyState } from '@/components/empty-state';
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
import { Head, Link, router, useForm } from '@inertiajs/react';
import { List } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Lists', href: '/marketing/lists' }];

type MarketingList = {
    id: number;
    name: string;
    description: string | null;
    type: string;
    member_count: number;
};

export default function Lists({ lists }: { lists: MarketingList[] }) {
    const { can } = usePermissions();
    const canManage = can('marketing.lists.manage');
    const [open, setOpen] = useState(false);
    const form = useForm<{
        name: string;
        description: string;
        type: 'static' | 'dynamic';
        criteria: { lifecycle_stage: string; lead_source: string; min_lead_score: string };
    }>({
        name: '',
        description: '',
        type: 'static',
        criteria: { lifecycle_stage: '', lead_source: '', min_lead_score: '' },
    });

    const create: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('marketing.lists.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Lists" />
            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between gap-2">
                    <Heading title="Lists" description={`${lists.length} total`} />
                    {canManage && (
                        <Dialog open={open} onOpenChange={setOpen}>
                            <DialogTrigger asChild>
                                <Button>New list</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>New list</DialogTitle>
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
                                            className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-20 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden"
                                            value={form.data.description}
                                            onChange={(e) => form.setData('description', e.target.value)}
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="type">Type</Label>
                                        <Select value={form.data.type} onValueChange={(v) => form.setData('type', v as 'static' | 'dynamic')}>
                                            <SelectTrigger id="type">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="static">Static</SelectItem>
                                                <SelectItem value="dynamic">Dynamic</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    {form.data.type === 'dynamic' && (
                                        <div className="space-y-3 rounded-md border p-3">
                                            <p className="text-muted-foreground text-xs">Optional membership criteria</p>
                                            <div className="grid gap-1">
                                                <Label htmlFor="lifecycle_stage">Lifecycle stage</Label>
                                                <Input
                                                    id="lifecycle_stage"
                                                    value={form.data.criteria.lifecycle_stage}
                                                    onChange={(e) =>
                                                        form.setData('criteria', { ...form.data.criteria, lifecycle_stage: e.target.value })
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="lead_source">Lead source</Label>
                                                <Input
                                                    id="lead_source"
                                                    value={form.data.criteria.lead_source}
                                                    onChange={(e) => form.setData('criteria', { ...form.data.criteria, lead_source: e.target.value })}
                                                />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="min_lead_score">Minimum lead score</Label>
                                                <Input
                                                    id="min_lead_score"
                                                    type="number"
                                                    value={form.data.criteria.min_lead_score}
                                                    onChange={(e) =>
                                                        form.setData('criteria', { ...form.data.criteria, min_lead_score: e.target.value })
                                                    }
                                                />
                                            </div>
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

                {lists.length === 0 ? (
                    <EmptyState
                        icon={List}
                        title="No lists yet"
                        description="Create your first list to start grouping contacts."
                        action={canManage && <Button onClick={() => setOpen(true)}>New list</Button>}
                    />
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3">Name</th>
                                    <th className="p-3">Type</th>
                                    <th className="p-3 text-right">Members</th>
                                    <th className="p-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {lists.map((list) => (
                                    <tr key={list.id} className="hover:bg-muted/40">
                                        <td className="p-3">
                                            <Link href={route('marketing.lists.show', list.id)} className="font-medium hover:underline">
                                                {list.name}
                                            </Link>
                                            {list.description && <span className="text-muted-foreground"> · {list.description}</span>}
                                        </td>
                                        <td className="p-3">
                                            <Badge variant="outline">{list.type}</Badge>
                                        </td>
                                        <td className="p-3 text-right">{list.member_count}</td>
                                        <td className="p-3 text-right">
                                            {canManage && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-destructive"
                                                    onClick={() => router.delete(route('marketing.lists.destroy', list.id))}
                                                >
                                                    Delete
                                                </Button>
                                            )}
                                        </td>
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
