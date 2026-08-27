import { SortHeader } from '@/components/crm/sort-header';
import { ScoreCell, StagePill } from '@/components/crm/stage-pill';
import { EmptyState } from '@/components/empty-state';
import { InitialAvatar } from '@/components/initial-avatar';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Bookmark, Trash2, Users, X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Contacts', href: '/crm/contacts' }];

type Contact = {
    id: number;
    name: string;
    email: string | null;
    title: string | null;
    company: string | null;
    owner: string | null;
    lifecycle_stage: string | null;
    lead_score: number;
    temperature: string;
};
type Paginated = { data: Contact[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
type Filters = { search?: string; owner?: number; lifecycle?: string; source?: string; sort?: string; dir?: string };
type SavedView = { id: number; name: string; filters: Filters };
type Option = { id: number; name: string };

export default function Contacts({
    contacts,
    filters,
    owners,
    lifecycleStages,
    sources,
    views,
}: {
    contacts: Paginated;
    filters: Filters;
    owners: Option[];
    lifecycleStages: string[];
    sources: string[];
    views: SavedView[];
}) {
    const { can } = usePermissions();
    const [search, setSearch] = useState(filters.search ?? '');
    const [open, setOpen] = useState(false);
    const [saveViewOpen, setSaveViewOpen] = useState(false);
    const [viewName, setViewName] = useState('');
    const [selected, setSelected] = useState<number[]>([]);
    const [bulkBusy, setBulkBusy] = useState(false);
    const form = useForm({ first_name: '', last_name: '', email: '', title: '', phone: '', company_id: '' });

    const hasFilters = Boolean(filters.search || filters.owner || filters.lifecycle || filters.source);

    /** Navigate with a merged filter set; empty values drop out of the URL. */
    const applyFilters = (patch: Partial<Filters>) => {
        const merged: Record<string, string | number | undefined> = { ...filters, search, ...patch };
        const next: Record<string, string | number> = {};
        Object.entries(merged).forEach(([key, value]) => {
            if (value !== '' && value !== undefined && value !== null) next[key] = value;
        });
        setSelected([]);
        router.get(route('crm.contacts.index'), next, { preserveState: true, replace: true });
    };

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        applyFilters({ search });
    };

    const runBulk = (payload: Record<string, unknown>) => {
        setBulkBusy(true);
        router.post(
            route('crm.contacts.bulk'),
            { ids: selected, ...payload },
            {
                preserveScroll: true,
                onSuccess: () => setSelected([]),
                onFinish: () => setBulkBusy(false),
            },
        );
    };

    const pageIds = contacts.data.map((c) => c.id);
    const allSelected = pageIds.length > 0 && pageIds.every((id) => selected.includes(id));

    const create: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('crm.contacts.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    const saveView: FormEventHandler = (e) => {
        e.preventDefault();
        router.post(
            route('crm.contacts.views.store'),
            { name: viewName, filters: { ...filters, search: search || undefined } },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setViewName('');
                    setSaveViewOpen(false);
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Contacts" />
            <div className="space-y-4 p-4">
                <PageHeader
                    title="Contacts"
                    description={`Manage and qualify your customer relationships · ${contacts.total} total`}
                    actions={
                        <>
                            {can('crm.contact.read') && (
                                <Button variant="outline" asChild>
                                    <a href={route('crm.contacts.export')}>Export CSV</a>
                                </Button>
                            )}
                            {can('crm.import') && (
                                <Button variant="outline" asChild>
                                    <Link href={route('crm.contacts.import')}>Import</Link>
                                </Button>
                            )}
                            {can('crm.contact.create') && (
                                <Dialog open={open} onOpenChange={setOpen}>
                                    <DialogTrigger asChild>
                                        <Button>New contact</Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogTitle>New contact</DialogTitle>
                                        <form onSubmit={create} className="space-y-3">
                                            <div className="grid grid-cols-2 gap-3">
                                                <div className="grid gap-1">
                                                    <Label htmlFor="first_name">First name</Label>
                                                    <Input
                                                        id="first_name"
                                                        value={form.data.first_name}
                                                        onChange={(e) => form.setData('first_name', e.target.value)}
                                                    />
                                                    <InputError message={form.errors.first_name} />
                                                </div>
                                                <div className="grid gap-1">
                                                    <Label htmlFor="last_name">Last name</Label>
                                                    <Input
                                                        id="last_name"
                                                        value={form.data.last_name}
                                                        onChange={(e) => form.setData('last_name', e.target.value)}
                                                    />
                                                </div>
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="email">Email</Label>
                                                <Input
                                                    id="email"
                                                    type="email"
                                                    value={form.data.email}
                                                    onChange={(e) => form.setData('email', e.target.value)}
                                                />
                                                <InputError message={form.errors.email} />
                                            </div>
                                            <div className="grid grid-cols-2 gap-3">
                                                <div className="grid gap-1">
                                                    <Label htmlFor="title">Title</Label>
                                                    <Input
                                                        id="title"
                                                        value={form.data.title}
                                                        onChange={(e) => form.setData('title', e.target.value)}
                                                    />
                                                </div>
                                                <div className="grid gap-1">
                                                    <Label htmlFor="phone">Phone</Label>
                                                    <Input
                                                        id="phone"
                                                        value={form.data.phone}
                                                        onChange={(e) => form.setData('phone', e.target.value)}
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
                            )}
                        </>
                    }
                >
                    {/* Filter bar: search + the three list-shaping selects + saved views. */}
                    <div className="flex flex-wrap items-center gap-2">
                        <form onSubmit={submitSearch} className="flex gap-2">
                            <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search name or email…" className="w-56" />
                            <Button type="submit" variant="outline">
                                Search
                            </Button>
                        </form>
                        <Select value={filters.lifecycle ?? '__all'} onValueChange={(v) => applyFilters({ lifecycle: v === '__all' ? '' : v })}>
                            <SelectTrigger className="w-36" aria-label="Filter by lifecycle stage">
                                <SelectValue placeholder="Stage" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__all">Any stage</SelectItem>
                                {lifecycleStages.map((stage) => (
                                    <SelectItem key={stage} value={stage} className="capitalize">
                                        {stage.replace(/_/g, ' ')}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={filters.source ?? '__all'} onValueChange={(v) => applyFilters({ source: v === '__all' ? '' : v })}>
                            <SelectTrigger className="w-36" aria-label="Filter by lead source">
                                <SelectValue placeholder="Source" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__all">Any source</SelectItem>
                                {sources.map((source) => (
                                    <SelectItem key={source} value={source} className="capitalize">
                                        {source}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.owner ? String(filters.owner) : '__all'}
                            onValueChange={(v) => applyFilters({ owner: v === '__all' ? undefined : Number(v) })}
                        >
                            <SelectTrigger className="w-40" aria-label="Filter by owner">
                                <SelectValue placeholder="Owner" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__all">Any owner</SelectItem>
                                {owners.map((owner) => (
                                    <SelectItem key={owner.id} value={String(owner.id)}>
                                        {owner.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {hasFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setSearch('');
                                    router.get(route('crm.contacts.index'), {}, { preserveState: false, replace: true });
                                }}
                            >
                                <X className="size-3.5" aria-hidden /> Clear
                            </Button>
                        )}

                        <div className="ml-auto flex items-center gap-2">
                            {views.length > 0 && (
                                <Select
                                    value="__pick"
                                    onValueChange={(v) => {
                                        if (v === '__pick') return;
                                        const view = views.find((x) => x.id === Number(v));
                                        if (view) {
                                            setSearch(view.filters.search ?? '');
                                            router.get(route('crm.contacts.index'), view.filters as Record<string, string>, {
                                                preserveState: false,
                                                replace: true,
                                            });
                                        }
                                    }}
                                >
                                    <SelectTrigger className="w-40" aria-label="Apply a saved view">
                                        <SelectValue placeholder="Saved views" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__pick">Saved views…</SelectItem>
                                        {views.map((view) => (
                                            <SelectItem key={view.id} value={String(view.id)}>
                                                {view.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                            {hasFilters && (
                                <Dialog open={saveViewOpen} onOpenChange={setSaveViewOpen}>
                                    <DialogTrigger asChild>
                                        <Button variant="outline" size="sm">
                                            <Bookmark className="size-3.5" aria-hidden /> Save view
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogTitle>Save this view</DialogTitle>
                                        <p className="text-muted-foreground -mt-2 text-sm">
                                            Saves the current filters and sorting as a personal shortcut.
                                        </p>
                                        <form onSubmit={saveView} className="space-y-3">
                                            <div className="grid gap-1">
                                                <Label htmlFor="view_name">Name</Label>
                                                <Input
                                                    id="view_name"
                                                    value={viewName}
                                                    onChange={(e) => setViewName(e.target.value)}
                                                    placeholder="Hot SQLs from chat"
                                                />
                                            </div>
                                            <DialogFooter>
                                                <Button type="submit" disabled={viewName.trim() === ''}>
                                                    Save
                                                </Button>
                                            </DialogFooter>
                                        </form>
                                        {views.length > 0 && (
                                            <div className="border-border mt-2 border-t pt-3">
                                                <p className="text-muted-foreground mb-2 text-xs font-medium uppercase">Your views</p>
                                                <ul className="space-y-1">
                                                    {views.map((view) => (
                                                        <li key={view.id} className="flex items-center justify-between gap-2 text-sm">
                                                            {view.name}
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="text-red-600"
                                                                aria-label={`Delete view ${view.name}`}
                                                                onClick={() =>
                                                                    router.delete(route('crm.contacts.views.destroy', view.id), {
                                                                        preserveScroll: true,
                                                                    })
                                                                }
                                                            >
                                                                <Trash2 className="size-3.5" aria-hidden />
                                                            </Button>
                                                        </li>
                                                    ))}
                                                </ul>
                                            </div>
                                        )}
                                    </DialogContent>
                                </Dialog>
                            )}
                        </div>
                    </div>
                </PageHeader>

                {/* Bulk bar appears only with a selection; each action keeps its permission. */}
                {selected.length > 0 && can('crm.contact.update') && (
                    <div className="border-brand/40 bg-brand-soft/50 flex flex-wrap items-center gap-2 rounded-lg border px-3 py-2">
                        <span className="text-sm font-medium">{selected.length} selected</span>
                        <Select value="__pick" onValueChange={(v) => v !== '__pick' && runBulk({ action: 'assign', owner_id: Number(v) })}>
                            <SelectTrigger className="h-8 w-40" aria-label="Assign selected to owner" disabled={bulkBusy}>
                                <SelectValue placeholder="Assign to…" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__pick">Assign to…</SelectItem>
                                {owners.map((owner) => (
                                    <SelectItem key={owner.id} value={String(owner.id)}>
                                        {owner.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value="__pick" onValueChange={(v) => v !== '__pick' && runBulk({ action: 'stage', lifecycle_stage: v })}>
                            <SelectTrigger className="h-8 w-40" aria-label="Set lifecycle stage for selected" disabled={bulkBusy}>
                                <SelectValue placeholder="Set stage…" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__pick">Set stage…</SelectItem>
                                {lifecycleStages.map((stage) => (
                                    <SelectItem key={stage} value={stage} className="capitalize">
                                        {stage.replace(/_/g, ' ')}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {can('crm.contact.delete') && (
                            <Button
                                variant="outline"
                                size="sm"
                                className="text-red-600"
                                disabled={bulkBusy}
                                onClick={() => {
                                    if (confirm(`Delete ${selected.length} contacts? This cannot be undone.`)) {
                                        runBulk({ action: 'delete' });
                                    }
                                }}
                            >
                                <Trash2 className="size-3.5" aria-hidden /> Delete
                            </Button>
                        )}
                        <Button variant="ghost" size="sm" className="ml-auto" onClick={() => setSelected([])}>
                            Cancel
                        </Button>
                    </div>
                )}

                {contacts.data.length === 0 ? (
                    hasFilters ? (
                        <EmptyState icon={Users} title="No contacts match" description="Loosen or clear the filters to see more of your list." />
                    ) : (
                        <EmptyState
                            icon={Users}
                            title="No contacts yet"
                            description="Add your first contact or import a CSV to start building your customer relationships."
                            action={can('crm.contact.create') && <Button onClick={() => setOpen(true)}>New contact</Button>}
                        />
                    )
                ) : (
                    <Table>
                        <TableHeader>
                            <tr>
                                {can('crm.contact.update') && (
                                    <TableHead className="w-8">
                                        <input
                                            type="checkbox"
                                            aria-label="Select all on this page"
                                            checked={allSelected}
                                            onChange={(e) =>
                                                setSelected(
                                                    e.target.checked
                                                        ? [...new Set([...selected, ...pageIds])]
                                                        : selected.filter((id) => !pageIds.includes(id)),
                                                )
                                            }
                                        />
                                    </TableHead>
                                )}
                                <SortHeader
                                    label="Name"
                                    column="name"
                                    sort={filters.sort}
                                    dir={filters.dir}
                                    onSort={(c, d) => applyFilters({ sort: c, dir: d })}
                                />
                                <SortHeader
                                    label="Email"
                                    column="email"
                                    sort={filters.sort}
                                    dir={filters.dir}
                                    onSort={(c, d) => applyFilters({ sort: c, dir: d })}
                                />
                                <TableHead>Company</TableHead>
                                <TableHead>Stage</TableHead>
                                <SortHeader
                                    label="Score"
                                    column="lead_score"
                                    sort={filters.sort}
                                    dir={filters.dir}
                                    onSort={(c, d) => applyFilters({ sort: c, dir: d })}
                                />
                                <TableHead>Owner</TableHead>
                            </tr>
                        </TableHeader>
                        <TableBody>
                            {contacts.data.map((c) => (
                                <TableRow key={c.id} className={selected.includes(c.id) ? 'bg-brand-soft/30' : undefined}>
                                    {can('crm.contact.update') && (
                                        <TableCell>
                                            <input
                                                type="checkbox"
                                                aria-label={`Select ${c.name}`}
                                                checked={selected.includes(c.id)}
                                                onChange={(e) =>
                                                    setSelected(e.target.checked ? [...selected, c.id] : selected.filter((id) => id !== c.id))
                                                }
                                            />
                                        </TableCell>
                                    )}
                                    <TableCell>
                                        <div className="flex items-center gap-2.5">
                                            <InitialAvatar name={c.name} />
                                            <div>
                                                <Link
                                                    href={route('crm.contacts.show', c.id)}
                                                    className="hover:text-brand-strong font-medium hover:underline"
                                                >
                                                    {c.name}
                                                </Link>
                                                {c.title && <span className="text-muted-foreground"> · {c.title}</span>}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{c.email}</TableCell>
                                    <TableCell className="text-muted-foreground">{c.company ?? '—'}</TableCell>
                                    <TableCell>
                                        <StagePill stage={c.lifecycle_stage} />
                                    </TableCell>
                                    <TableCell>
                                        <ScoreCell score={c.lead_score} temperature={c.temperature} />
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{c.owner ?? '—'}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}

                {contacts.links.length > 3 && (
                    <div className="flex flex-wrap gap-1">
                        {contacts.links.map((link, i) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    className={`rounded px-3 py-1 text-sm transition-colors ${link.active ? 'bg-brand text-brand-foreground' : 'hover:bg-muted'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span key={i} className="text-muted-foreground px-3 py-1 text-sm" dangerouslySetInnerHTML={{ __html: link.label }} />
                            ),
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
