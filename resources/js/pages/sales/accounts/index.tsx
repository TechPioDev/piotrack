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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Accounts', href: '/sales/accounts' }];

type Account = {
    id: number;
    company: string | null;
    tier: number;
    status: string;
    account_score: number;
    committee: number;
    engaged: number;
    decision_makers: number;
    multi_threaded: boolean;
};

type Company = {
    id: number;
    name: string;
};

type Status = 'targeted' | 'engaged' | 'opportunity' | 'won' | 'lost';

const TIERS = ['1', '2', '3'] as const;
const STATUSES: Status[] = ['targeted', 'engaged', 'opportunity', 'won', 'lost'];

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

function tierVariant(tier: number): 'default' | 'secondary' | 'outline' {
    if (tier === 1) return 'default';
    if (tier === 2) return 'secondary';
    return 'outline';
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' {
    if (status === 'won') return 'default';
    if (status === 'lost') return 'destructive';
    return 'secondary';
}

function NewAccountDialog({ companies, verticals }: { companies: Company[]; verticals: { id: number; name: string }[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ company_id: string; tier: (typeof TIERS)[number]; notes: string; vertical_id: string }>({
        company_id: '',
        tier: '1',
        notes: '',
        vertical_id: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            company_id: Number(data.company_id),
            tier: Number(data.tier),
            vertical_id: data.vertical_id === '' ? null : Number(data.vertical_id),
        }));
        form.post(route('sales.accounts.store'), {
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
                <Button>New target account</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New target account</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="account_company">Company</Label>
                        <Select value={form.data.company_id} onValueChange={(v) => form.setData('company_id', v)}>
                            <SelectTrigger id="account_company">
                                <SelectValue placeholder="Select a company" />
                            </SelectTrigger>
                            <SelectContent>
                                {companies.map((company) => (
                                    <SelectItem key={company.id} value={String(company.id)}>
                                        {company.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.company_id} />
                    </div>
                    {verticals.length > 0 && (
                        <div className="grid gap-1">
                            <Label htmlFor="account_vertical">Vertical (optional)</Label>
                            <Select value={form.data.vertical_id} onValueChange={(v) => form.setData('vertical_id', v)}>
                                <SelectTrigger id="account_vertical">
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
                    <div className="grid gap-1">
                        <Label htmlFor="account_tier">Tier</Label>
                        <Select value={form.data.tier} onValueChange={(v) => form.setData('tier', v as (typeof TIERS)[number])}>
                            <SelectTrigger id="account_tier">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {TIERS.map((tier) => (
                                    <SelectItem key={tier} value={tier}>
                                        Tier {tier}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.tier} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="account_notes">Notes</Label>
                        <textarea
                            id="account_notes"
                            className={textareaClass}
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                        />
                        <InputError message={form.errors.notes} />
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

function EditAccountDialog({ account }: { account: Account }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ tier: (typeof TIERS)[number]; status: Status }>({
        tier: String(account.tier) as (typeof TIERS)[number],
        status: account.status as Status,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, tier: Number(data.tier) }));
        form.patch(route('sales.accounts.update', account.id), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Edit account</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor={`edit_tier_${account.id}`}>Tier</Label>
                            <Select value={form.data.tier} onValueChange={(v) => form.setData('tier', v as (typeof TIERS)[number])}>
                                <SelectTrigger id={`edit_tier_${account.id}`}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {TIERS.map((tier) => (
                                        <SelectItem key={tier} value={tier}>
                                            Tier {tier}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.tier} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor={`edit_status_${account.id}`}>Status</Label>
                            <Select value={form.data.status} onValueChange={(v) => form.setData('status', v as Status)}>
                                <SelectTrigger id={`edit_status_${account.id}`}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {STATUSES.map((status) => (
                                        <SelectItem key={status} value={status}>
                                            {status}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.status} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AccountPageDialog({ account }: { account: Account }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ service: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('sales.accounts.page.create', account.id), {
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
                    Landing page
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Personalized landing page</DialogTitle>
                <p className="text-muted-foreground text-sm">
                    Drafts a &quot;[service] built for {account.company ?? 'this account'}&quot; page. Review and publish it under Marketing → Landing
                    pages.
                </p>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor={`abm-svc-${account.id}`}>Service</Label>
                        <Input
                            id={`abm-svc-${account.id}`}
                            placeholder="e.g. Managed Cybersecurity"
                            value={form.data.service}
                            onChange={(e) => form.setData('service', e.target.value)}
                        />
                        <InputError message={form.errors.service} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Create draft
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Accounts({
    accounts,
    companies,
    verticals,
}: {
    accounts: Account[];
    companies: Company[];
    verticals: { id: number; name: string }[];
}) {
    const { can } = usePermissions();
    const canManage = can('sales.accounts.manage');

    const rescore = (id: number) => router.post(route('sales.accounts.rescore', id), {}, { preserveScroll: true });
    const removeAccount = (id: number) => router.delete(route('sales.accounts.destroy', id), { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Accounts" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading title="Target accounts" description="Account-based marketing targets and their buying committees" />
                    <div className="flex flex-wrap gap-2">
                        {canManage &&
                            [1, 2, 3].map((tier) => (
                                <Button
                                    key={tier}
                                    size="sm"
                                    variant="secondary"
                                    onClick={() => router.post(route('sales.accounts.sync-list'), { tier }, { preserveScroll: true })}
                                >
                                    Sync Tier {tier} list
                                </Button>
                            ))}
                        {canManage && (
                            <Button size="sm" variant="outline" asChild>
                                {/* ABM-012: the company-list CSV LinkedIn Campaign Manager accepts for account targeting. */}
                                <a href={route('sales.accounts.linkedin-export')}>LinkedIn company list</a>
                            </Button>
                        )}
                        {canManage && <NewAccountDialog companies={companies} verticals={verticals} />}
                    </div>
                </div>

                {accounts.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No target accounts yet. Add a company to start account-based selling.</p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">Company</th>
                                    <th className="p-3 font-medium">Tier</th>
                                    <th className="p-3 font-medium">Status</th>
                                    <th className="p-3 text-center font-medium">Score</th>
                                    <th className="p-3 text-center font-medium">Committee</th>
                                    <th className="p-3 text-center font-medium">Engaged</th>
                                    <th className="p-3 text-center font-medium">Threading</th>
                                    {canManage && <th className="p-3 text-right font-medium">Actions</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {accounts.map((account) => (
                                    <tr key={account.id} className="hover:bg-muted/40">
                                        <td className="p-3 font-medium">{account.company ?? '—'}</td>
                                        <td className="p-3">
                                            <Badge variant={tierVariant(account.tier)}>Tier {account.tier}</Badge>
                                        </td>
                                        <td className="p-3">
                                            <Badge variant={statusVariant(account.status)}>{account.status}</Badge>
                                        </td>
                                        <td className="p-3 text-center">{account.account_score}</td>
                                        <td className="p-3 text-center">
                                            {account.committee}
                                            {account.decision_makers > 0 && (
                                                <span className="text-muted-foreground ml-1 text-xs">({account.decision_makers} DM)</span>
                                            )}
                                        </td>
                                        <td className="p-3 text-center">{account.engaged}</td>
                                        <td className="p-3 text-center">
                                            {account.multi_threaded ? (
                                                <Badge>Multi-threaded</Badge>
                                            ) : (
                                                <Badge variant="secondary">Single thread</Badge>
                                            )}
                                        </td>
                                        {canManage && (
                                            <td className="p-3">
                                                <div className="flex justify-end gap-2">
                                                    <Button size="sm" variant="ghost" asChild>
                                                        <Link href={route('sales.accounts.report', account.id)}>Report</Link>
                                                    </Button>
                                                    <AccountPageDialog account={account} />
                                                    <EditAccountDialog account={account} />
                                                    <Button size="sm" variant="secondary" onClick={() => rescore(account.id)}>
                                                        Rescore
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="text-destructive"
                                                        onClick={() => removeAccount(account.id)}
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
        </AppLayout>
    );
}
