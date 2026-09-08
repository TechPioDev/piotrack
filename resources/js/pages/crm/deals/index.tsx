import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Deals', href: '/crm/deals' }];

type DealCard = {
    id: number;
    name: string;
    value: number;
    status: string;
    contact: string | null;
    company: string | null;
    owner: string | null;
    service_line_id: number | null;
};
type Stage = { id: number; name: string; is_won: boolean; is_lost: boolean; deals: DealCard[]; total: number };
type ServiceLineOption = { id: number; name: string };

export default function Deals({
    pipeline,
    stages,
    service_lines,
}: {
    pipeline: { id: number; name: string };
    stages: Stage[];
    service_lines: ServiceLineOption[];
}) {
    const { can } = usePermissions();
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '', value: '', stage_id: '', service_line_id: '' });

    const create: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('crm.deals.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Deals" />
            <div className="space-y-4 p-4">
                <PageHeader
                    title="Deals"
                    description={`Your opportunity pipeline · ${pipeline.name}`}
                    actions={
                        <>
                            {can('crm.deal.read') && (
                                <Button variant="outline" asChild>
                                    <a href={route('crm.deals.export')}>Export CSV</a>
                                </Button>
                            )}
                            {can('crm.import') && (
                                <Button variant="outline" asChild>
                                    <Link href={route('crm.entity.import', 'deals')}>Import</Link>
                                </Button>
                            )}
                            {can('crm.deal.create') && (
                                <Dialog open={open} onOpenChange={setOpen}>
                                    <DialogTrigger asChild>
                                        <Button>New deal</Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogTitle>New deal</DialogTitle>
                                        <form onSubmit={create} className="space-y-3">
                                            <div className="grid gap-1">
                                                <Label htmlFor="name">Name</Label>
                                                <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                                                <InputError message={form.errors.name} />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="value">Value ($)</Label>
                                                <Input
                                                    id="value"
                                                    type="number"
                                                    min="0"
                                                    value={form.data.value}
                                                    onChange={(e) => form.setData('value', e.target.value)}
                                                />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="stage">Stage</Label>
                                                <Select value={form.data.stage_id} onValueChange={(v) => form.setData('stage_id', v)}>
                                                    <SelectTrigger id="stage">
                                                        <SelectValue placeholder="First stage" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {stages.map((s) => (
                                                            <SelectItem key={s.id} value={String(s.id)}>
                                                                {s.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            {service_lines.length > 0 && (
                                                <div className="grid gap-1">
                                                    <Label htmlFor="service-line">Service line</Label>
                                                    <Select
                                                        value={form.data.service_line_id}
                                                        onValueChange={(v) => form.setData('service_line_id', v)}
                                                    >
                                                        <SelectTrigger id="service-line">
                                                            <SelectValue placeholder="What this deal sells" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {service_lines.map((line) => (
                                                                <SelectItem key={line.id} value={String(line.id)}>
                                                                    {line.name}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
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
                        </>
                    }
                />

                <div className="flex gap-3 overflow-x-auto pb-2">
                    {stages.map((stage) => (
                        <div key={stage.id} className="bg-muted/30 w-72 shrink-0 rounded-lg border">
                            <div className="flex items-center justify-between border-b p-3">
                                <span className="flex items-center gap-2 font-medium">
                                    <span
                                        className={cn(
                                            'size-2 rounded-full',
                                            stage.is_won ? 'bg-emerald-500' : stage.is_lost ? 'bg-red-500' : 'bg-brand',
                                        )}
                                        aria-hidden
                                    />
                                    {stage.name}
                                </span>
                                <span className="text-muted-foreground text-xs tabular-nums">
                                    {stage.deals.length} · {formatMoney(stage.total)}
                                </span>
                            </div>
                            <div className="space-y-2 p-2">
                                {stage.deals.map((deal) => (
                                    <div
                                        key={deal.id}
                                        className="bg-background hover:border-brand/40 space-y-2 rounded-md border p-2 text-sm shadow-sm transition hover:-translate-y-0.5 hover:shadow-md motion-reduce:transform-none"
                                    >
                                        <Link href={route('crm.deals.show', deal.id)} className="hover:text-brand-strong font-medium hover:underline">
                                            {deal.name}
                                        </Link>
                                        <div className="text-muted-foreground text-xs">
                                            {formatMoney(deal.value)}
                                            {deal.contact && ` · ${deal.contact}`}
                                        </div>
                                        {can('crm.deal.update') && (
                                            <Select
                                                value={String(stage.id)}
                                                onValueChange={(v) =>
                                                    router.patch(route('crm.deals.stage', deal.id), { stage_id: Number(v) }, { preserveScroll: true })
                                                }
                                            >
                                                <SelectTrigger className="h-7 text-xs">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {stages.map((s) => (
                                                        <SelectItem key={s.id} value={String(s.id)}>
                                                            {s.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        )}
                                        {can('crm.deal.update') && service_lines.length > 0 && (
                                            <Select
                                                value={deal.service_line_id !== null ? String(deal.service_line_id) : undefined}
                                                onValueChange={(v) =>
                                                    router.patch(
                                                        route('crm.deals.update', deal.id),
                                                        { name: deal.name, service_line_id: Number(v) },
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            >
                                                <SelectTrigger className="h-7 text-xs">
                                                    <SelectValue placeholder="Service line" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {service_lines.map((line) => (
                                                        <SelectItem key={line.id} value={String(line.id)}>
                                                            {line.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        )}
                                    </div>
                                ))}
                                {stage.deals.length === 0 && <p className="text-muted-foreground p-2 text-xs">No deals</p>}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
