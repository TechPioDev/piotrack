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
import { Users } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Contacts', href: '/crm/contacts' }];

type Contact = { id: number; name: string; email: string | null; title: string | null; company: string | null; owner: string | null };
type Paginated = { data: Contact[]; links: { url: string | null; label: string; active: boolean }[]; total: number };

export default function Contacts({ contacts, filters }: { contacts: Paginated; filters: { search?: string; owner?: number } }) {
    const { can } = usePermissions();
    const [search, setSearch] = useState(filters.search ?? '');
    const [open, setOpen] = useState(false);
    const form = useForm({ first_name: '', last_name: '', email: '', title: '', phone: '', company_id: '' });

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('crm.contacts.index'), { search }, { preserveState: true, replace: true });
    };

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
                    <form onSubmit={submitSearch} className="flex gap-2">
                        <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search name or email…" className="max-w-sm" />
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                    </form>
                </PageHeader>

                {contacts.data.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title="No contacts yet"
                        description="Add your first contact or import a CSV to start building your customer relationships."
                        action={can('crm.contact.create') && <Button onClick={() => setOpen(true)}>New contact</Button>}
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <tr>
                                <TableHead>Name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Company</TableHead>
                                <TableHead>Owner</TableHead>
                            </tr>
                        </TableHeader>
                        <TableBody>
                            {contacts.data.map((c) => (
                                <TableRow key={c.id}>
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
