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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Campaigns', href: '/marketing/campaigns' }];

type Campaign = {
    id: number;
    name: string;
    channel: string;
    status: string;
    list: string | null;
    stat_sent: number;
    stat_opened: number;
    stat_clicked: number;
};

type ListOption = { id: number; name: string };

export default function Campaigns({ campaigns, lists }: { campaigns: Campaign[]; lists: ListOption[] }) {
    const { can } = usePermissions();
    const canManage = can('marketing.campaigns.manage');
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; channel: 'email' | 'sms'; type: string; marketing_list_id: string }>({
        name: '',
        channel: 'email',
        type: '',
        marketing_list_id: '',
    });

    const create: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('marketing.campaigns.store'), {
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
                                            <Label htmlFor="channel">Channel</Label>
                                            <Select value={form.data.channel} onValueChange={(v) => form.setData('channel', v as 'email' | 'sms')}>
                                                <SelectTrigger id="channel">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="email">Email</SelectItem>
                                                    <SelectItem value="sms">SMS</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="grid gap-1">
                                            <Label htmlFor="type">Type</Label>
                                            <Input id="type" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} />
                                        </div>
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="marketing_list_id">List</Label>
                                        <Select value={form.data.marketing_list_id} onValueChange={(v) => form.setData('marketing_list_id', v)}>
                                            <SelectTrigger id="marketing_list_id">
                                                <SelectValue placeholder="Select a list" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {lists.map((list) => (
                                                    <SelectItem key={list.id} value={String(list.id)}>
                                                        {list.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={form.errors.marketing_list_id} />
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

                {campaigns.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No campaigns yet. Create a campaign to reach your lists.</p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3">Name</th>
                                    <th className="p-3">Channel</th>
                                    <th className="p-3">Status</th>
                                    <th className="p-3">List</th>
                                    <th className="p-3 text-right">Sent</th>
                                    <th className="p-3 text-right">Opened</th>
                                    <th className="p-3 text-right">Clicked</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {campaigns.map((campaign) => (
                                    <tr key={campaign.id} className="hover:bg-muted/40">
                                        <td className="p-3">
                                            <Link href={route('marketing.campaigns.show', campaign.id)} className="font-medium hover:underline">
                                                {campaign.name}
                                            </Link>
                                        </td>
                                        <td className="p-3">
                                            <Badge variant="outline">{campaign.channel}</Badge>
                                        </td>
                                        <td className="p-3">
                                            <Badge variant={campaign.status === 'sent' ? 'default' : 'secondary'}>{campaign.status}</Badge>
                                        </td>
                                        <td className="text-muted-foreground p-3">{campaign.list ?? '—'}</td>
                                        <td className="p-3 text-right">{campaign.stat_sent}</td>
                                        <td className="p-3 text-right">{campaign.stat_opened}</td>
                                        <td className="p-3 text-right">{campaign.stat_clicked}</td>
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
