import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Local SEO', href: '/seo/local' }];

type Citation = {
    id: number;
    source: string;
    status: string;
    mismatches: string[];
    listed_name: string | null;
    listed_address: string | null;
    listed_phone: string | null;
};

type Location = {
    id: number;
    name: string;
    address: string;
    phone: string | null;
    website: string | null;
    citations: Citation[];
};

function citationVariant(status: string): 'default' | 'secondary' | 'destructive' {
    if (status === 'consistent') return 'default';
    if (status === 'inconsistent') return 'destructive';
    return 'secondary';
}

function AddCitationDialog({ location }: { location: Location }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ source: string; listed_name: string; listed_address: string; listed_phone: string; url: string }>({
        source: '',
        listed_name: '',
        listed_address: '',
        listed_phone: '',
        url: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('seo.local.citations.store', location.id), {
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
                <Button size="sm" variant="outline">
                    Add citation
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Add citation</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor={`source-${location.id}`}>Source</Label>
                        <Input
                            id={`source-${location.id}`}
                            placeholder="Google Business Profile"
                            value={form.data.source}
                            onChange={(e) => form.setData('source', e.target.value)}
                        />
                        <InputError message={form.errors.source} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`listed_name-${location.id}`}>Listed name</Label>
                        <Input
                            id={`listed_name-${location.id}`}
                            value={form.data.listed_name}
                            onChange={(e) => form.setData('listed_name', e.target.value)}
                        />
                        <InputError message={form.errors.listed_name} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`listed_address-${location.id}`}>Listed address</Label>
                        <Input
                            id={`listed_address-${location.id}`}
                            value={form.data.listed_address}
                            onChange={(e) => form.setData('listed_address', e.target.value)}
                        />
                        <InputError message={form.errors.listed_address} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`listed_phone-${location.id}`}>Listed phone</Label>
                        <Input
                            id={`listed_phone-${location.id}`}
                            value={form.data.listed_phone}
                            onChange={(e) => form.setData('listed_phone', e.target.value)}
                        />
                        <InputError message={form.errors.listed_phone} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`url-${location.id}`}>URL</Label>
                        <Input
                            id={`url-${location.id}`}
                            type="url"
                            placeholder="https://…"
                            value={form.data.url}
                            onChange={(e) => form.setData('url', e.target.value)}
                        />
                        <InputError message={form.errors.url} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Add citation
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CreatePageDialog({ location }: { location: Location }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ service: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('seo.local.page.create', location.id), {
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
                    Create landing page
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Location landing page</DialogTitle>
                <p className="text-muted-foreground text-sm">
                    Generates a draft &quot;[service] in {location.name}&quot; page with this branch&apos;s address and phone baked in. Review and
                    publish it under Marketing → Landing pages.
                </p>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor={`svc-${location.id}`}>Service</Label>
                        <Input
                            id={`svc-${location.id}`}
                            placeholder="e.g. Managed IT Services"
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

function LocationCard({ location, canManage }: { location: Location; canManage: boolean }) {
    return (
        <Card>
            <CardContent className="space-y-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="space-y-0.5">
                        <p className="font-medium">{location.name}</p>
                        <p className="text-muted-foreground text-sm">{location.address || '—'}</p>
                        <p className="text-muted-foreground text-sm">{location.phone ?? '—'}</p>
                    </div>
                    {canManage && (
                        <div className="flex gap-2">
                            <CreatePageDialog location={location} />
                            <AddCitationDialog location={location} />
                            <Button
                                size="sm"
                                variant="ghost"
                                className="text-destructive"
                                onClick={() => router.delete(route('seo.local.destroy', location.id), { preserveScroll: true })}
                            >
                                Delete
                            </Button>
                        </div>
                    )}
                </div>

                {location.citations.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No citations yet. Add a citation to check NAP consistency.</p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">Source</th>
                                    <th className="p-3 font-medium">Status</th>
                                    <th className="p-3 font-medium">Mismatches</th>
                                    {canManage && <th className="p-3 text-right font-medium">Actions</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {location.citations.map((citation) => (
                                    <tr key={citation.id} className="hover:bg-muted/40">
                                        <td className="p-3 font-medium">{citation.source}</td>
                                        <td className="p-3">
                                            <Badge variant={citationVariant(citation.status)}>{citation.status}</Badge>
                                        </td>
                                        <td className="text-muted-foreground p-3">
                                            {citation.mismatches.length > 0 ? citation.mismatches.join(', ') : '—'}
                                        </td>
                                        {canManage && (
                                            <td className="p-3">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.post(
                                                                route('seo.local.citations.check', [location.id, citation.id]),
                                                                {},
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        Re-check
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="text-destructive"
                                                        onClick={() =>
                                                            router.delete(route('seo.local.citations.destroy', [location.id, citation.id]), {
                                                                preserveScroll: true,
                                                            })
                                                        }
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

export default function Local({ locations }: { locations: Location[] }) {
    const { can } = usePermissions();
    const canManage = can('seo.local.manage');
    const [open, setOpen] = useState(false);
    const form = useForm<{
        name: string;
        street: string;
        city: string;
        region: string;
        postal_code: string;
        country: string;
        phone: string;
        website: string;
    }>({
        name: '',
        street: '',
        city: '',
        region: '',
        postal_code: '',
        country: '',
        phone: '',
        website: '',
    });

    const create: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('seo.local.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Local SEO" />
            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between gap-2">
                    <Heading title="Local SEO" description={`${locations.length} locations`} />
                    {canManage && (
                        <Dialog open={open} onOpenChange={setOpen}>
                            <DialogTrigger asChild>
                                <Button>Add location</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>Add location</DialogTitle>
                                <form onSubmit={create} className="space-y-3">
                                    <div className="grid gap-1">
                                        <Label htmlFor="name">Name</Label>
                                        <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                                        <InputError message={form.errors.name} />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="street">Street</Label>
                                        <Input id="street" value={form.data.street} onChange={(e) => form.setData('street', e.target.value)} />
                                        <InputError message={form.errors.street} />
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="grid gap-1">
                                            <Label htmlFor="city">City</Label>
                                            <Input id="city" value={form.data.city} onChange={(e) => form.setData('city', e.target.value)} />
                                            <InputError message={form.errors.city} />
                                        </div>
                                        <div className="grid gap-1">
                                            <Label htmlFor="region">Region</Label>
                                            <Input id="region" value={form.data.region} onChange={(e) => form.setData('region', e.target.value)} />
                                            <InputError message={form.errors.region} />
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="grid gap-1">
                                            <Label htmlFor="postal_code">Postal code</Label>
                                            <Input
                                                id="postal_code"
                                                value={form.data.postal_code}
                                                onChange={(e) => form.setData('postal_code', e.target.value)}
                                            />
                                            <InputError message={form.errors.postal_code} />
                                        </div>
                                        <div className="grid gap-1">
                                            <Label htmlFor="country">Country</Label>
                                            <Input id="country" value={form.data.country} onChange={(e) => form.setData('country', e.target.value)} />
                                            <InputError message={form.errors.country} />
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="grid gap-1">
                                            <Label htmlFor="phone">Phone</Label>
                                            <Input id="phone" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                                            <InputError message={form.errors.phone} />
                                        </div>
                                        <div className="grid gap-1">
                                            <Label htmlFor="website">Website</Label>
                                            <Input
                                                id="website"
                                                type="url"
                                                placeholder="https://…"
                                                value={form.data.website}
                                                onChange={(e) => form.setData('website', e.target.value)}
                                            />
                                            <InputError message={form.errors.website} />
                                        </div>
                                    </div>
                                    <DialogFooter>
                                        <Button type="submit" disabled={form.processing}>
                                            Add location
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>

                {locations.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No locations yet. Add a location to track local citations and NAP consistency.</p>
                ) : (
                    <div className="space-y-4">
                        {locations.map((location) => (
                            <LocationCard key={location.id} location={location} canManage={canManage} />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
