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
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Enablement', href: '/sales/enablement' }];

type Asset = {
    id: number;
    type: string;
    title: string;
    description: string | null;
    url: string | null;
};

type Play = {
    id: number;
    name: string;
    description: string | null;
    target_segment: string | null;
    steps: unknown[];
};

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

type Option = { id: number; name: string };

function NewAssetDialog({ types, verticals, serviceLines }: { types: string[]; verticals: Option[]; serviceLines: Option[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{
        type: string;
        title: string;
        description: string;
        content: string;
        url: string;
        vertical_id: string;
        service_line_id: string;
    }>({
        type: types[0] ?? '',
        title: '',
        description: '',
        content: '',
        url: '',
        vertical_id: '',
        service_line_id: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            vertical_id: data.vertical_id === '' ? null : Number(data.vertical_id),
            service_line_id: data.service_line_id === '' ? null : Number(data.service_line_id),
        }));
        form.post(route('sales.enablement.assets.store'), {
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
                <Button>New asset</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New asset</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="asset_type">Type</Label>
                            <Select value={form.data.type} onValueChange={(v) => form.setData('type', v)}>
                                <SelectTrigger id="asset_type">
                                    <SelectValue placeholder="Select a type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {types.map((type) => (
                                        <SelectItem key={type} value={type}>
                                            {type}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.type} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="asset_title">Title</Label>
                            <Input id="asset_title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                            <InputError message={form.errors.title} />
                        </div>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="asset_description">Description</Label>
                        <textarea
                            id="asset_description"
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="asset_content">Content</Label>
                        <textarea
                            id="asset_content"
                            className={textareaClass}
                            value={form.data.content}
                            onChange={(e) => form.setData('content', e.target.value)}
                        />
                        <InputError message={form.errors.content} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="asset_url">URL</Label>
                        <Input id="asset_url" type="url" value={form.data.url} onChange={(e) => form.setData('url', e.target.value)} />
                        <InputError message={form.errors.url} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="asset_vertical">Vertical (optional)</Label>
                            <Select value={form.data.vertical_id} onValueChange={(v) => form.setData('vertical_id', v)}>
                                <SelectTrigger id="asset_vertical">
                                    <SelectValue placeholder="Any vertical" />
                                </SelectTrigger>
                                <SelectContent>
                                    {verticals.map((vertical) => (
                                        <SelectItem key={vertical.id} value={String(vertical.id)}>
                                            {vertical.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="asset_service">Service line (optional)</Label>
                            <Select value={form.data.service_line_id} onValueChange={(v) => form.setData('service_line_id', v)}>
                                <SelectTrigger id="asset_service">
                                    <SelectValue placeholder="Any service" />
                                </SelectTrigger>
                                <SelectContent>
                                    {serviceLines.map((service) => (
                                        <SelectItem key={service.id} value={String(service.id)}>
                                            {service.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
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

function NewPlayDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; description: string; target_segment: string }>({
        name: '',
        description: '',
        target_segment: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('sales.enablement.plays.store'), {
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
                <Button size="sm">New play</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New play</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="play_name">Name</Label>
                        <Input id="play_name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="play_target_segment">Target segment</Label>
                        <Input
                            id="play_target_segment"
                            value={form.data.target_segment}
                            onChange={(e) => form.setData('target_segment', e.target.value)}
                        />
                        <InputError message={form.errors.target_segment} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="play_description">Description</Label>
                        <textarea
                            id="play_description"
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                        />
                        <InputError message={form.errors.description} />
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

function RoiCalculatorDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm<{
        company: string;
        employees: string;
        downtime_hours_year: string;
        downtime_cost_hour: string;
        managed_cost_month: string;
        downtime_reduction_pct: string;
    }>({
        company: '',
        employees: '25',
        downtime_hours_year: '40',
        downtime_cost_hour: '500',
        managed_cost_month: '3000',
        downtime_reduction_pct: '80',
    });

    const employees = Number(form.data.employees) || 0;
    const exposure = (Number(form.data.downtime_hours_year) || 0) * (Number(form.data.downtime_cost_hour) || 0);
    const avoided = (exposure * (Number(form.data.downtime_reduction_pct) || 0)) / 100;
    const annualCost = (Number(form.data.managed_cost_month) || 0) * 12;
    const net = avoided - annualCost;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            company: data.company,
            employees: Number(data.employees),
            downtime_hours_year: Number(data.downtime_hours_year),
            downtime_cost_hour: Math.round(Number(data.downtime_cost_hour) * 100),
            managed_cost_month: Math.round(Number(data.managed_cost_month) * 100),
            downtime_reduction_pct: Number(data.downtime_reduction_pct),
        }));
        form.post(route('sales.enablement.roi'), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    const field = (key: keyof typeof form.data, label: string, type = 'number') => (
        <div className="grid gap-1">
            <Label htmlFor={`roi_${key}`}>{label}</Label>
            <Input id={`roi_${key}`} type={type} value={form.data[key]} onChange={(e) => form.setData(key, e.target.value)} />
            <InputError message={form.errors[key]} />
        </div>
    );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">ROI calculator</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Managed-IT ROI calculator</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    {field('company', 'Prospect company', 'text')}
                    <div className="grid grid-cols-2 gap-3">
                        {field('employees', 'Employees')}
                        {field('downtime_hours_year', 'Downtime hours / year')}
                        {field('downtime_cost_hour', 'Downtime cost / hour ($)')}
                        {field('managed_cost_month', 'Managed cost / month ($)')}
                    </div>
                    {field('downtime_reduction_pct', 'Expected downtime reduction (%)')}
                    <div className="bg-muted/50 rounded-md p-3 text-sm">
                        <p>
                            Exposure ${exposure.toLocaleString()}/yr · avoided ${Math.round(avoided).toLocaleString()} · cost $
                            {annualCost.toLocaleString()} · <span className="font-medium">net ${Math.round(net).toLocaleString()}/yr</span> for{' '}
                            {employees} employees
                        </p>
                        <p className="text-muted-foreground mt-1 text-xs">
                            Every figure derives from the assumptions above — saving stores the full model as a library asset.
                        </p>
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing || form.data.company === ''}>
                            Save ROI model
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function GenerateProposalDialog({ templates, deals }: { templates: { id: number; title: string }[]; deals: { id: number; name: string }[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ asset_id: string; deal_id: string }>({ asset_id: '', deal_id: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({ asset_id: Number(data.asset_id), deal_id: Number(data.deal_id) }));
        form.post(route('sales.enablement.proposal'), {
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
                <Button variant="outline">Generate proposal</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Generate a proposal from a template</DialogTitle>
                <p className="text-muted-foreground text-sm">
                    Merge fields ({'{{company}}'}, {'{{contact}}'}, {'{{deal_name}}'}, {'{{deal_value}}'}, {'{{services}}'}, {'{{date}}'}) fill from
                    the deal; the result lands in the library for review.
                </p>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="proposal_template">Template</Label>
                        <Select value={form.data.asset_id} onValueChange={(v) => form.setData('asset_id', v)}>
                            <SelectTrigger id="proposal_template">
                                <SelectValue placeholder="Pick a proposal template" />
                            </SelectTrigger>
                            <SelectContent>
                                {templates.map((template) => (
                                    <SelectItem key={template.id} value={String(template.id)}>
                                        {template.title}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.asset_id} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="proposal_deal">Deal</Label>
                        <Select value={form.data.deal_id} onValueChange={(v) => form.setData('deal_id', v)}>
                            <SelectTrigger id="proposal_deal">
                                <SelectValue placeholder="Pick an open deal" />
                            </SelectTrigger>
                            <SelectContent>
                                {deals.map((deal) => (
                                    <SelectItem key={deal.id} value={String(deal.id)}>
                                        {deal.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.deal_id} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing || form.data.asset_id === '' || form.data.deal_id === ''}>
                            Generate
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Enablement({
    assets,
    plays,
    types,
    verticals,
    service_lines,
    proposal_templates,
    open_deals,
}: {
    assets: Asset[];
    plays: Play[];
    types: string[];
    verticals: Option[];
    service_lines: Option[];
    proposal_templates: { id: number; title: string }[];
    open_deals: { id: number; name: string }[];
}) {
    const { can } = usePermissions();
    const canManage = can('sales.enablement.manage');

    const removeAsset = (id: number) => router.delete(route('sales.enablement.assets.destroy', id), { preserveScroll: true });
    const removePlay = (id: number) => router.delete(route('sales.enablement.plays.destroy', id), { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Enablement" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading title="Sales enablement" description="Assets and plays that help reps sell" />
                    {canManage && (
                        <div className="flex flex-wrap gap-2">
                            <RoiCalculatorDialog />
                            <GenerateProposalDialog templates={proposal_templates} deals={open_deals} />
                            <NewAssetDialog types={types} verticals={verticals} serviceLines={service_lines} />
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Assets library</h3>
                    {assets.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No assets yet. Add a deck, one-pager, or script to get started.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Type</th>
                                        <th className="p-3 font-medium">Title</th>
                                        <th className="p-3 font-medium">Description</th>
                                        <th className="p-3 font-medium">Link</th>
                                        {canManage && <th className="p-3 text-right font-medium">Actions</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {assets.map((asset) => (
                                        <tr key={asset.id} className="hover:bg-muted/40">
                                            <td className="p-3">
                                                <Badge variant="outline">{asset.type}</Badge>
                                            </td>
                                            <td className="p-3 font-medium">{asset.title}</td>
                                            <td className="text-muted-foreground p-3">
                                                <p className="max-w-xs truncate">{asset.description ?? '—'}</p>
                                            </td>
                                            <td className="p-3">
                                                {asset.url ? (
                                                    <a href={asset.url} target="_blank" rel="noreferrer" className="break-all hover:underline">
                                                        {asset.url}
                                                    </a>
                                                ) : (
                                                    <span className="text-muted-foreground">—</span>
                                                )}
                                            </td>
                                            {canManage && (
                                                <td className="p-3">
                                                    <div className="flex justify-end">
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="text-destructive"
                                                            onClick={() => removeAsset(asset.id)}
                                                        >
                                                            Delete
                                                        </Button>
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

                <div>
                    <div className="mb-2 flex items-center justify-between gap-2">
                        <h3 className="text-sm font-medium">Plays</h3>
                        {canManage && <NewPlayDialog />}
                    </div>
                    {plays.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No plays yet. Create a play to guide reps through a motion.</p>
                    ) : (
                        <div className="divide-y rounded-lg border">
                            {plays.map((play) => (
                                <div key={play.id} className="flex items-start justify-between gap-3 p-3">
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-medium">{play.name}</span>
                                            {play.target_segment && <Badge variant="outline">{play.target_segment}</Badge>}
                                            <span className="text-muted-foreground text-xs">{play.steps.length} steps</span>
                                        </div>
                                        {play.description && <p className="text-muted-foreground mt-0.5 text-xs">{play.description}</p>}
                                    </div>
                                    {canManage && (
                                        <Button size="sm" variant="ghost" className="text-destructive" onClick={() => removePlay(play.id)}>
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
