import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Funnels', href: '/marketing/funnels' }];

type FunnelStage = { id: number; name: string; category: string; count: number };
type Funnel = { id: number; name: string; description: string | null; stages: FunnelStage[] };

type StageCategory = 'tof' | 'mof' | 'bof' | 'post';
type StageRow = { name: string; category: StageCategory; lifecycle_stage: string };

const CATEGORY_LABELS: Record<string, string> = {
    tof: 'Top of funnel',
    mof: 'Middle of funnel',
    bof: 'Bottom of funnel',
    post: 'Post-sale',
};

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-20 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

export default function Funnels({ funnels }: { funnels: Funnel[] }) {
    const { can } = usePermissions();
    const canManage = can('marketing.campaigns.manage');
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; description: string; stages: StageRow[] }>({
        name: '',
        description: '',
        stages: [{ name: '', category: 'tof', lifecycle_stage: '' }],
    });

    const updateStage = (index: number, patch: Partial<StageRow>) => {
        form.setData(
            'stages',
            form.data.stages.map((stage, i) => (i === index ? { ...stage, ...patch } : stage)),
        );
    };

    const addStage = () => form.setData('stages', [...form.data.stages, { name: '', category: 'tof', lifecycle_stage: '' }]);

    const removeStage = (index: number) =>
        form.setData(
            'stages',
            form.data.stages.filter((_, i) => i !== index),
        );

    const create: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('marketing.funnels.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Funnels" />
            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between gap-2">
                    <Heading title="Funnels" description={`${funnels.length} total`} />
                    {canManage && (
                        <Dialog open={open} onOpenChange={setOpen}>
                            <DialogTrigger asChild>
                                <Button>New funnel</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>New funnel</DialogTitle>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="self-start"
                                    onClick={() =>
                                        form.setData({
                                            name: 'MSP Growth Funnel',
                                            description: 'The standard full-funnel journey: attract, educate, qualify, convert, close, retain.',
                                            stages: [
                                                { name: 'Awareness', category: 'tof', lifecycle_stage: 'subscriber' },
                                                { name: 'Interest', category: 'tof', lifecycle_stage: 'lead' },
                                                { name: 'Evaluation', category: 'mof', lifecycle_stage: 'mql' },
                                                { name: 'Sales-ready', category: 'bof', lifecycle_stage: 'sql' },
                                                { name: 'Opportunity', category: 'bof', lifecycle_stage: 'opportunity' },
                                                { name: 'Customer', category: 'post', lifecycle_stage: 'customer' },
                                            ],
                                        })
                                    }
                                >
                                    Start from the MSP Growth template
                                </Button>
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

                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between">
                                            <Label>Stages</Label>
                                            <Button type="button" size="sm" variant="outline" onClick={addStage}>
                                                Add stage
                                            </Button>
                                        </div>
                                        {form.data.stages.map((stage, index) => (
                                            <div key={index} className="space-y-2 rounded-md border p-3">
                                                <div className="grid grid-cols-2 gap-2">
                                                    <Input
                                                        placeholder="name"
                                                        value={stage.name}
                                                        onChange={(e) => updateStage(index, { name: e.target.value })}
                                                    />
                                                    <Select
                                                        value={stage.category}
                                                        onValueChange={(v) => updateStage(index, { category: v as StageCategory })}
                                                    >
                                                        <SelectTrigger className="h-9">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="tof">Top of funnel</SelectItem>
                                                            <SelectItem value="mof">Middle of funnel</SelectItem>
                                                            <SelectItem value="bof">Bottom of funnel</SelectItem>
                                                            <SelectItem value="post">Post-sale</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Input
                                                        placeholder="lifecycle stage (optional)"
                                                        value={stage.lifecycle_stage}
                                                        onChange={(e) => updateStage(index, { lifecycle_stage: e.target.value })}
                                                    />
                                                    {form.data.stages.length > 1 && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            className="text-destructive"
                                                            onClick={() => removeStage(index)}
                                                        >
                                                            Remove
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>

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

                {funnels.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No funnels yet. Create a funnel to map your customer journey.</p>
                ) : (
                    <div className="space-y-4">
                        {funnels.map((funnel) => (
                            <Card key={funnel.id}>
                                <CardContent className="space-y-3 p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <Link
                                                href={route('marketing.funnels.show', funnel.id)}
                                                className="hover:text-brand-strong font-medium hover:underline"
                                            >
                                                {funnel.name}
                                            </Link>
                                            {funnel.description && <p className="text-muted-foreground text-sm">{funnel.description}</p>}
                                        </div>
                                        {canManage && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="text-destructive"
                                                onClick={() => router.delete(route('marketing.funnels.destroy', funnel.id))}
                                            >
                                                Delete
                                            </Button>
                                        )}
                                    </div>
                                    {funnel.stages.length === 0 ? (
                                        <p className="text-muted-foreground text-sm">No stages defined.</p>
                                    ) : (
                                        <div className="flex flex-wrap gap-2">
                                            {funnel.stages.map((stage) => (
                                                <div key={stage.id} className="min-w-28 rounded-md border p-3">
                                                    <p className="text-sm font-medium">{stage.name}</p>
                                                    <p className="text-2xl font-semibold">{stage.count}</p>
                                                    <p className="text-muted-foreground text-xs">
                                                        {CATEGORY_LABELS[stage.category] ?? stage.category}
                                                    </p>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
