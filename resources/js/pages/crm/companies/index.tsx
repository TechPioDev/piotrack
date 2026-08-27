import { SortHeader } from '@/components/crm/sort-header';
import { EmptyState } from '@/components/empty-state';
import { InitialAvatar } from '@/components/initial-avatar';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Companies', href: '/crm/companies' }];

type Company = { id: number; name: string; domain: string | null; industry: string | null; contacts_count: number; deals_count: number };
type Paginated = { data: Company[]; links: { url: string | null; label: string; active: boolean }[]; total: number };

export default function Companies({ companies, filters }: { companies: Paginated; filters: { search?: string; sort?: string; dir?: string } }) {
    const { can } = usePermissions();
    const sortBy = (column: string, dir: 'asc' | 'desc') =>
        router.get(
            route('crm.companies.index'),
            { ...(filters.search ? { search: filters.search } : {}), sort: column, dir },
            { preserveState: true, replace: true },
        );
    const [search, setSearch] = useState(filters.search ?? '');
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '', domain: '', industry: '', website: '' });

    const create: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('crm.companies.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Companies" />
            <div className="space-y-4 p-4">
                <PageHeader
                    title="Companies"
                    description={`Track the organizations behind your contacts · ${companies.total} total`}
                    actions={
                        can('crm.company.create') && (
                            <Dialog open={open} onOpenChange={setOpen}>
                                <DialogTrigger asChild>
                                    <Button>New company</Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogTitle>New company</DialogTitle>
                                    <form onSubmit={create} className="space-y-3">
                                        <div className="grid gap-1">
                                            <Label htmlFor="name">Name</Label>
                                            <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                                            <InputError message={form.errors.name} />
                                        </div>
                                        <div className="grid grid-cols-2 gap-3">
                                            <div className="grid gap-1">
                                                <Label htmlFor="domain">Domain</Label>
                                                <Input
                                                    id="domain"
                                                    value={form.data.domain}
                                                    onChange={(e) => form.setData('domain', e.target.value)}
                                                />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="industry">Industry</Label>
                                                <Input
                                                    id="industry"
                                                    value={form.data.industry}
                                                    onChange={(e) => form.setData('industry', e.target.value)}
                                                />
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
                        )
                    }
                />

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(route('crm.companies.index'), { search }, { preserveState: true, replace: true });
                    }}
                    className="flex gap-2"
                >
                    <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search name or domain…" className="max-w-sm" />
                    <Button type="submit" variant="outline">
                        Search
                    </Button>
                </form>

                {companies.data.length === 0 ? (
                    <EmptyState
                        icon={Building2}
                        title="No companies yet"
                        description="Add the organizations your contacts belong to."
                        action={can('crm.company.create') && <Button onClick={() => setOpen(true)}>New company</Button>}
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <tr>
                                <SortHeader label="Name" column="name" sort={filters.sort} dir={filters.dir} onSort={sortBy} />
                                <TableHead>Industry</TableHead>
                                <SortHeader
                                    label="Contacts"
                                    column="contacts_count"
                                    sort={filters.sort}
                                    dir={filters.dir}
                                    onSort={sortBy}
                                    className="text-center"
                                />
                                <SortHeader
                                    label="Deals"
                                    column="deals_count"
                                    sort={filters.sort}
                                    dir={filters.dir}
                                    onSort={sortBy}
                                    className="text-center"
                                />
                            </tr>
                        </TableHeader>
                        <TableBody>
                            {companies.data.map((c) => (
                                <TableRow key={c.id}>
                                    <TableCell>
                                        <div className="flex items-center gap-2.5">
                                            <InitialAvatar name={c.name} className="rounded-md" />
                                            <div>
                                                <Link
                                                    href={route('crm.companies.show', c.id)}
                                                    className="hover:text-brand-strong font-medium hover:underline"
                                                >
                                                    {c.name}
                                                </Link>
                                                {c.domain && <span className="text-muted-foreground"> · {c.domain}</span>}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{c.industry ?? '—'}</TableCell>
                                    <TableCell className="text-center tabular-nums">{c.contacts_count}</TableCell>
                                    <TableCell className="text-center tabular-nums">{c.deals_count}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>
        </AppLayout>
    );
}
