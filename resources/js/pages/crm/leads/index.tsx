import { EmptyState } from '@/components/empty-state';
import { InitialAvatar } from '@/components/initial-avatar';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Leads', href: '/crm/leads' }];

type Lead = {
    id: number;
    name: string;
    email: string | null;
    company_name: string | null;
    source: string | null;
    status: string;
    owner: string | null;
};
type Paginated = { data: Lead[]; links: { url: string | null; label: string; active: boolean }[]; total: number };

const statusVariant: Record<string, 'default' | 'secondary' | 'outline'> = {
    new: 'secondary',
    qualified: 'default',
    converted: 'outline',
};

export default function Leads({ leads, filters, statuses }: { leads: Paginated; filters: { search?: string; status?: string }; statuses: string[] }) {
    const { can } = usePermissions();
    const [open, setOpen] = useState(false);
    const create = useForm({ first_name: '', last_name: '', email: '', company_name: '', source: '' });

    const submitCreate: FormEventHandler = (e) => {
        e.preventDefault();
        create.post(route('crm.leads.store'), {
            preserveScroll: true,
            onSuccess: () => {
                create.reset();
                setOpen(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Leads" />
            <div className="space-y-4 p-4">
                <PageHeader
                    title="Leads"
                    description={`Capture and qualify inbound prospects · ${leads.total} total`}
                    actions={
                        can('crm.lead.create') && (
                            <Dialog open={open} onOpenChange={setOpen}>
                                <DialogTrigger asChild>
                                    <Button>New lead</Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogTitle>New lead</DialogTitle>
                                    <form onSubmit={submitCreate} className="space-y-3">
                                        <div className="grid grid-cols-2 gap-3">
                                            <div className="grid gap-1">
                                                <Label htmlFor="first_name">First name</Label>
                                                <Input
                                                    id="first_name"
                                                    value={create.data.first_name}
                                                    onChange={(e) => create.setData('first_name', e.target.value)}
                                                />
                                                <InputError message={create.errors.first_name} />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="last_name">Last name</Label>
                                                <Input
                                                    id="last_name"
                                                    value={create.data.last_name}
                                                    onChange={(e) => create.setData('last_name', e.target.value)}
                                                />
                                            </div>
                                        </div>
                                        <div className="grid gap-1">
                                            <Label htmlFor="email">Email</Label>
                                            <Input
                                                id="email"
                                                type="email"
                                                value={create.data.email}
                                                onChange={(e) => create.setData('email', e.target.value)}
                                            />
                                        </div>
                                        <div className="grid grid-cols-2 gap-3">
                                            <div className="grid gap-1">
                                                <Label htmlFor="company_name">Company</Label>
                                                <Input
                                                    id="company_name"
                                                    value={create.data.company_name}
                                                    onChange={(e) => create.setData('company_name', e.target.value)}
                                                />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="source">Source</Label>
                                                <Input
                                                    id="source"
                                                    value={create.data.source}
                                                    onChange={(e) => create.setData('source', e.target.value)}
                                                />
                                            </div>
                                        </div>
                                        <DialogFooter>
                                            <Button type="submit" disabled={create.processing}>
                                                Create
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        )
                    }
                />

                <div className="flex gap-2">
                    <Select
                        value={filters.status ?? 'all'}
                        onValueChange={(v) =>
                            router.get(route('crm.leads.index'), v === 'all' ? {} : { status: v }, { preserveState: true, replace: true })
                        }
                    >
                        <SelectTrigger className="w-44">
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {statuses.map((s) => (
                                <SelectItem key={s} value={s} className="capitalize">
                                    {s}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {leads.data.length === 0 ? (
                    <EmptyState
                        icon={UserPlus}
                        title="No leads yet"
                        description="New form submissions and manually added prospects will appear here."
                        action={can('crm.lead.create') && <Button onClick={() => setOpen(true)}>New lead</Button>}
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <tr>
                                <TableHead>Name</TableHead>
                                <TableHead>Company</TableHead>
                                <TableHead>Source</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </tr>
                        </TableHeader>
                        <TableBody>
                            {leads.data.map((lead) => (
                                <TableRow key={lead.id}>
                                    <TableCell>
                                        <div className="flex items-center gap-2.5">
                                            <InitialAvatar name={lead.name} />
                                            <div>
                                                <span className="font-medium">{lead.name}</span>
                                                {lead.email && <span className="text-muted-foreground"> · {lead.email}</span>}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{lead.company_name ?? '—'}</TableCell>
                                    <TableCell className="text-muted-foreground">{lead.source ?? '—'}</TableCell>
                                    <TableCell>
                                        <Badge variant={statusVariant[lead.status] ?? 'secondary'} className="capitalize">
                                            {lead.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {lead.status !== 'converted' && can('crm.lead.update') && <ConvertButton leadId={lead.id} />}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>
        </AppLayout>
    );
}

function ConvertButton({ leadId }: { leadId: number }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ create_deal: boolean; deal_value: string }>({ create_deal: true, deal_value: '' });

    const convert: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('crm.leads.convert', leadId), { onSuccess: () => setOpen(false) });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Convert
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Convert lead</DialogTitle>
                <form onSubmit={convert} className="space-y-4">
                    <p className="text-muted-foreground text-sm">Creates a contact (and company), and optionally opens a deal.</p>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={form.data.create_deal} onCheckedChange={(v) => form.setData('create_deal', Boolean(v))} />
                        Open a deal
                    </label>
                    {form.data.create_deal && (
                        <div className="grid max-w-xs gap-1">
                            <Label htmlFor="deal_value">Deal value ($)</Label>
                            <Input
                                id="deal_value"
                                type="number"
                                min="0"
                                value={form.data.deal_value}
                                onChange={(e) => form.setData('deal_value', e.target.value)}
                            />
                        </div>
                    )}
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Convert
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
