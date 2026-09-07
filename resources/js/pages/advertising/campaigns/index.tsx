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
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Campaigns', href: '/ads/campaigns' }];

type Campaign = {
    id: number;
    name: string;
    platform: string;
    type: string | null;
    objective: string;
    status: string;
    daily_budget: number;
};

type Objective = 'leads' | 'awareness' | 'traffic' | 'conversions';

const OBJECTIVES: Objective[] = ['leads', 'awareness', 'traffic', 'conversions'];

function money(cents: number): string {
    return `$${(cents / 100).toFixed(2)}`;
}

function statusVariant(status: string): 'default' | 'secondary' {
    return status === 'active' ? 'default' : 'secondary';
}

type AuditFinding = {
    campaign_id: number;
    campaign: string;
    severity: string;
    finding: string;
};

function AdLeadsDialog({ label, title, hint, routeName }: { label: string; title: string; hint: string; routeName: string }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ file: File | null; campaign: string }>({ file: null, campaign: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route(routeName), {
            preserveScroll: true,
            forceFormData: true,
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
                    {label}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <p className="text-muted-foreground text-sm">
                    {hint} Contacts are matched by email; a lead&rsquo;s original source is never overwritten.
                </p>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="leads_file">Leads CSV</Label>
                        <Input
                            id="leads_file"
                            type="file"
                            accept=".csv,text/csv"
                            onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
                        />
                        <InputError message={form.errors.file} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="leads_campaign">Campaign name (optional fallback)</Label>
                        <Input id="leads_campaign" value={form.data.campaign} onChange={(e) => form.setData('campaign', e.target.value)} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing || !form.data.file}>
                            Import
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Campaigns({
    campaigns,
    platforms,
    service_lines,
    audit,
}: {
    campaigns: Campaign[];
    platforms: string[];
    service_lines: { id: number; name: string }[];
    audit: AuditFinding[];
}) {
    const { can } = usePermissions();
    const canManage = can('ads.campaigns.manage');
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; platform: string; type: string; objective: Objective; daily_budget: string; service_line_id: string }>({
        name: '',
        platform: '',
        type: '',
        objective: 'leads',
        daily_budget: '',
        service_line_id: '',
    });

    const create: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            daily_budget: data.daily_budget ? Math.round(Number(data.daily_budget) * 100) : null,
            service_line_id: data.service_line_id === '' ? null : Number(data.service_line_id),
        }));
        form.post(route('ads.campaigns.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Campaigns" />
            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between gap-2">
                    <Heading title="Campaigns" description={`${campaigns.length} total`} />
                    {canManage && (
                        <Dialog open={open} onOpenChange={setOpen}>
                            <DialogTrigger asChild>
                                <Button>New campaign</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>New campaign</DialogTitle>
                                <form onSubmit={create} className="space-y-3">
                                    <div className="grid gap-1">
                                        <Label htmlFor="name">Name</Label>
                                        <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                                        <InputError message={form.errors.name} />
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="grid gap-1">
                                            <Label htmlFor="platform">Platform</Label>
                                            <Select value={form.data.platform} onValueChange={(v) => form.setData('platform', v)}>
                                                <SelectTrigger id="platform">
                                                    <SelectValue placeholder="Select a platform" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {platforms.map((platform) => (
                                                        <SelectItem key={platform} value={platform}>
                                                            {platform}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={form.errors.platform} />
                                        </div>
                                        <div className="grid gap-1">
                                            <Label htmlFor="type">Type</Label>
                                            <Input id="type" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} />
                                        </div>
                                    </div>
                                    {service_lines.length > 0 && (
                                        <div className="grid gap-1">
                                            <Label htmlFor="campaign_service">Service line (optional)</Label>
                                            <Select value={form.data.service_line_id} onValueChange={(v) => form.setData('service_line_id', v)}>
                                                <SelectTrigger id="campaign_service">
                                                    <SelectValue placeholder="Not bound to one service" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {service_lines.map((service) => (
                                                        <SelectItem key={service.id} value={String(service.id)}>
                                                            {service.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={form.errors.service_line_id} />
                                        </div>
                                    )}
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="grid gap-1">
                                            <Label htmlFor="objective">Objective</Label>
                                            <Select value={form.data.objective} onValueChange={(v) => form.setData('objective', v as Objective)}>
                                                <SelectTrigger id="objective">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {OBJECTIVES.map((objective) => (
                                                        <SelectItem key={objective} value={objective}>
                                                            {objective}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="grid gap-1">
                                            <Label htmlFor="daily_budget">Daily budget ($)</Label>
                                            <Input
                                                id="daily_budget"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={form.data.daily_budget}
                                                onChange={(e) => form.setData('daily_budget', e.target.value)}
                                            />
                                            <InputError message={form.errors.daily_budget} />
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
                    )}
                </div>

                {canManage && (
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-muted-foreground text-xs font-medium uppercase">LinkedIn</span>
                            {[1, 2, 3].map((tier) => (
                                <Button
                                    key={tier}
                                    size="sm"
                                    variant="outline"
                                    onClick={() => router.post(route('ads.linkedin.abm'), { tier }, { preserveScroll: true })}
                                >
                                    ABM Tier {tier} campaign
                                </Button>
                            ))}
                            <AdLeadsDialog
                                label="Import LinkedIn leads"
                                title="Import LinkedIn lead-gen leads"
                                hint="Export leads from LinkedIn Campaign Manager (Account Assets → Lead Gen Forms → Download leads) and upload the CSV unchanged."
                                routeName="ads.linkedin.leads"
                            />
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-muted-foreground text-xs font-medium uppercase">Meta</span>
                            <Button size="sm" variant="outline" onClick={() => router.post(route('ads.meta.proof'), {}, { preserveScroll: true })}>
                                Proof campaign from reviews
                            </Button>
                            <AdLeadsDialog
                                label="Import Meta leads"
                                title="Import Meta lead ads leads"
                                hint="Download leads from Meta Ads Manager (Lead ads forms → Download) and upload the CSV unchanged."
                                routeName="ads.meta.leads"
                            />
                        </div>
                    </div>
                )}

                {audit.length > 0 && (
                    <div className="space-y-2">
                        <h3 className="text-sm font-medium">Account audit</h3>
                        <p className="text-muted-foreground text-xs">
                            Findings from the structure and metrics recorded here — auditing a live ads account additionally needs the platform API
                            connection.
                        </p>
                        <ul className="divide-y rounded-lg border">
                            {audit.map((item, i) => (
                                <li key={i} className="flex items-center gap-3 p-3 text-sm">
                                    <Badge variant={item.severity === 'error' ? 'destructive' : 'secondary'}>{item.severity}</Badge>
                                    <Link href={route('ads.campaigns.show', item.campaign_id)} className="font-medium hover:underline">
                                        {item.campaign}
                                    </Link>
                                    <span className="text-muted-foreground">{item.finding}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {campaigns.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No campaigns yet. Create a campaign to start advertising.</p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">Name</th>
                                    <th className="p-3 font-medium">Platform</th>
                                    <th className="p-3 font-medium">Objective</th>
                                    <th className="p-3 font-medium">Status</th>
                                    <th className="p-3 text-center font-medium">Daily budget</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {campaigns.map((campaign) => (
                                    <tr key={campaign.id} className="hover:bg-muted/40">
                                        <td className="p-3">
                                            <Link href={route('ads.campaigns.show', campaign.id)} className="font-medium hover:underline">
                                                {campaign.name}
                                            </Link>
                                        </td>
                                        <td className="p-3">
                                            <Badge variant="outline">{campaign.platform}</Badge>
                                        </td>
                                        <td className="text-muted-foreground p-3">{campaign.objective}</td>
                                        <td className="p-3">
                                            <Badge variant={statusVariant(campaign.status)}>{campaign.status}</Badge>
                                        </td>
                                        <td className="p-3 text-center">{money(campaign.daily_budget)}</td>
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
