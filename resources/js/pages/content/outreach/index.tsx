import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Outreach', href: '/content/outreach' }];

type Prospect = {
    id: number;
    name: string;
    domain: string | null;
    status: string;
    placement_url: string | null;
    domain_authority: number | null;
    anchor_text: string | null;
};

type Rollup = {
    total: number;
    by_status: Record<string, number>;
    placements: number;
};

type Campaign = {
    id: number;
    name: string;
    type: string;
    goal: string | null;
    status: string;
    rollup: Rollup;
    prospects: Prospect[];
};

const CAMPAIGN_TYPES = ['digital_pr', 'link_building'];
const LINK_TYPES = ['dofollow', 'nofollow'];

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

function prospectStatusVariant(status: string): 'default' | 'secondary' | 'destructive' {
    if (status === 'won') return 'default';
    if (status === 'lost') return 'destructive';
    return 'secondary';
}

function NewCampaignDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; type: string; goal: string }>({ name: '', type: 'digital_pr', goal: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('content.outreach.store'), {
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
                <Button>New campaign</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New outreach campaign</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="campaign_name">Name</Label>
                        <Input id="campaign_name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="campaign_type">Type</Label>
                        <Select value={form.data.type} onValueChange={(v) => form.setData('type', v)}>
                            <SelectTrigger id="campaign_type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {CAMPAIGN_TYPES.map((type) => (
                                    <SelectItem key={type} value={type}>
                                        {type}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="campaign_goal">Goal</Label>
                        <textarea
                            id="campaign_goal"
                            className={textareaClass}
                            value={form.data.goal}
                            onChange={(e) => form.setData('goal', e.target.value)}
                        />
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

function AddProspectDialog({ campaignId }: { campaignId: number }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; domain: string; contact_email: string; domain_authority: string }>({
        name: '',
        domain: '',
        contact_email: '',
        domain_authority: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            domain_authority: data.domain_authority ? Number(data.domain_authority) : null,
        }));
        form.post(route('content.outreach.prospects.store', campaignId), {
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
                <Button size="sm" variant="secondary">
                    Add prospect
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Add prospect</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor={`prospect_name_${campaignId}`}>Name</Label>
                        <Input id={`prospect_name_${campaignId}`} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor={`prospect_domain_${campaignId}`}>Domain</Label>
                            <Input
                                id={`prospect_domain_${campaignId}`}
                                value={form.data.domain}
                                onChange={(e) => form.setData('domain', e.target.value)}
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor={`prospect_da_${campaignId}`}>Domain authority</Label>
                            <Input
                                id={`prospect_da_${campaignId}`}
                                type="number"
                                min="0"
                                max="100"
                                value={form.data.domain_authority}
                                onChange={(e) => form.setData('domain_authority', e.target.value)}
                            />
                            <InputError message={form.errors.domain_authority} />
                        </div>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`prospect_email_${campaignId}`}>Contact email</Label>
                        <Input
                            id={`prospect_email_${campaignId}`}
                            type="email"
                            value={form.data.contact_email}
                            onChange={(e) => form.setData('contact_email', e.target.value)}
                        />
                        <InputError message={form.errors.contact_email} />
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

function MarkPlacementDialog({ prospectId }: { prospectId: number }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ placement_url: string; domain_authority: string; anchor_text: string; link_type: string; placement_kind: string }>({
        placement_kind: 'backlink',
        placement_url: '',
        domain_authority: '',
        anchor_text: '',
        link_type: 'dofollow',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            domain_authority: data.domain_authority ? Number(data.domain_authority) : null,
        }));
        form.post(route('content.outreach.prospects.placement', prospectId), {
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
                <Button size="sm" variant="secondary">
                    Mark placement
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Mark placement</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor={`placement_url_${prospectId}`}>Placement URL</Label>
                        <Input
                            id={`placement_url_${prospectId}`}
                            type="url"
                            value={form.data.placement_url}
                            onChange={(e) => form.setData('placement_url', e.target.value)}
                        />
                        <InputError message={form.errors.placement_url} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor={`placement_da_${prospectId}`}>Domain authority</Label>
                            <Input
                                id={`placement_da_${prospectId}`}
                                type="number"
                                min="0"
                                max="100"
                                value={form.data.domain_authority}
                                onChange={(e) => form.setData('domain_authority', e.target.value)}
                            />
                            <InputError message={form.errors.domain_authority} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor={`placement_link_type_${prospectId}`}>Link type</Label>
                            <Select value={form.data.link_type} onValueChange={(v) => form.setData('link_type', v)}>
                                <SelectTrigger id={`placement_link_type_${prospectId}`}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {LINK_TYPES.map((type) => (
                                        <SelectItem key={type} value={type}>
                                            {type}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`placement_anchor_${prospectId}`}>Anchor text</Label>
                        <Input
                            id={`placement_anchor_${prospectId}`}
                            value={form.data.anchor_text}
                            onChange={(e) => form.setData('anchor_text', e.target.value)}
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`placement_kind_${prospectId}`}>Placement kind</Label>
                        <Select value={form.data.placement_kind} onValueChange={(v) => form.setData('placement_kind', v)}>
                            <SelectTrigger id={`placement_kind_${prospectId}`}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {['backlink', 'article', 'press', 'expert_quote'].map((kind) => (
                                    <SelectItem key={kind} value={kind}>
                                        {kind.replace('_', ' ')}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-muted-foreground text-xs">The won placement is recorded as this kind of authority asset.</p>
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Save placement
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CampaignCard({ campaign, statuses, canManage }: { campaign: Campaign; statuses: string[]; canManage: boolean }) {
    const setStatus = (prospectId: number, status: string) =>
        router.post(route('content.outreach.prospects.status', prospectId), { status }, { preserveScroll: true });
    const removeProspect = (prospectId: number) => router.delete(route('content.outreach.prospects.destroy', prospectId), { preserveScroll: true });
    const removeCampaign = () => router.delete(route('content.outreach.destroy', campaign.id), { preserveScroll: true });

    return (
        <Card>
            <CardContent className="space-y-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <span className="font-medium">{campaign.name}</span>
                            <Badge variant="outline">{campaign.type}</Badge>
                            <Badge variant={campaign.status === 'active' ? 'default' : 'secondary'}>{campaign.status}</Badge>
                        </div>
                        <p className="text-muted-foreground text-xs">
                            {campaign.rollup.total} prospects · {campaign.rollup.placements} placements
                        </p>
                    </div>
                    {canManage && (
                        <div className="flex flex-wrap gap-2">
                            <AddProspectDialog campaignId={campaign.id} />
                            <Button size="sm" variant="ghost" className="text-destructive" onClick={removeCampaign}>
                                Delete campaign
                            </Button>
                        </div>
                    )}
                </div>

                {campaign.prospects.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No prospects yet. Add a prospect to start outreach.</p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">Name</th>
                                    <th className="p-3 font-medium">Domain</th>
                                    <th className="p-3 font-medium">Status</th>
                                    <th className="p-3 text-center font-medium">DA</th>
                                    <th className="p-3 font-medium">Anchor</th>
                                    <th className="p-3 font-medium">Placement</th>
                                    {canManage && <th className="p-3 text-right font-medium">Actions</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {campaign.prospects.map((prospect) => (
                                    <tr key={prospect.id} className="hover:bg-muted/40">
                                        <td className="p-3 font-medium">{prospect.name}</td>
                                        <td className="text-muted-foreground p-3">{prospect.domain ?? '—'}</td>
                                        <td className="p-3">
                                            <Badge variant={prospectStatusVariant(prospect.status)}>{prospect.status}</Badge>
                                        </td>
                                        <td className="p-3 text-center">{prospect.domain_authority ?? '—'}</td>
                                        <td className="text-muted-foreground p-3">{prospect.anchor_text ?? '—'}</td>
                                        <td className="p-3">
                                            {prospect.placement_url ? (
                                                <a
                                                    href={prospect.placement_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-primary hover:underline"
                                                >
                                                    Link
                                                </a>
                                            ) : (
                                                <span className="text-muted-foreground">—</span>
                                            )}
                                        </td>
                                        {canManage && (
                                            <td className="p-3">
                                                <div className="flex flex-wrap items-center justify-end gap-2">
                                                    <Select value={prospect.status} onValueChange={(v) => setStatus(prospect.id, v)}>
                                                        <SelectTrigger className="h-8 w-36">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {statuses.map((status) => (
                                                                <SelectItem key={status} value={status}>
                                                                    {status}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                    <MarkPlacementDialog prospectId={prospect.id} />
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="text-destructive"
                                                        onClick={() => removeProspect(prospect.id)}
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
            </CardContent>
        </Card>
    );
}

export default function Outreach({ campaigns, statuses }: { campaigns: Campaign[]; statuses: string[] }) {
    const { can } = usePermissions();
    const canManage = can('content.outreach.manage');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Outreach" />
            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between gap-2">
                    <Heading title="Outreach" description={`${campaigns.length} campaigns`} />
                    {canManage && <NewCampaignDialog />}
                </div>

                {campaigns.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No campaigns yet. Create a campaign to plan digital PR and link building.</p>
                ) : (
                    <div className="space-y-4">
                        {campaigns.map((campaign) => (
                            <CampaignCard key={campaign.id} campaign={campaign} statuses={statuses} canManage={canManage} />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
