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

function MessagingCell({ vertical, canManage }: { vertical: Vertical; canManage: boolean }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ value_proposition: string; pain_points: string; differentiators: string; compliance_notes: string }>({
        value_proposition: vertical.messaging?.value_proposition ?? '',
        pain_points: vertical.messaging?.pain_points ?? '',
        differentiators: vertical.messaging?.differentiators ?? '',
        compliance_notes: vertical.compliance_notes ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.patch(route('web.taxonomy.vertical', vertical.id), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    const summary = vertical.messaging?.value_proposition;

    return (
        <div className="space-y-1">
            <p className="text-muted-foreground text-xs">{summary ?? 'No messaging yet.'}</p>
            {canManage && (
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogTrigger asChild>
                        <Button size="sm" variant="outline">
                            {summary ? 'Edit messaging' : 'Add messaging'}
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>{vertical.name} messaging</DialogTitle>
                        <p className="text-muted-foreground text-sm">
                            The framing every page, campaign and sequence targeting this vertical draws on.
                        </p>
                        <form onSubmit={submit} className="space-y-3">
                            <div className="grid gap-1">
                                <Label htmlFor="msg_value">Value proposition</Label>
                                <Input
                                    id="msg_value"
                                    value={form.data.value_proposition}
                                    onChange={(e) => form.setData('value_proposition', e.target.value)}
                                />
                                <InputError message={form.errors.value_proposition} />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="msg_pains">Pain points</Label>
                                <Input id="msg_pains" value={form.data.pain_points} onChange={(e) => form.setData('pain_points', e.target.value)} />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="msg_diff">Differentiators</Label>
                                <Input
                                    id="msg_diff"
                                    value={form.data.differentiators}
                                    onChange={(e) => form.setData('differentiators', e.target.value)}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="msg_compliance">Compliance notes</Label>
                                <Input
                                    id="msg_compliance"
                                    value={form.data.compliance_notes}
                                    onChange={(e) => form.setData('compliance_notes', e.target.value)}
                                />
                            </div>
                            <DialogFooter>
                                <Button type="submit" disabled={form.processing}>
                                    Save
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            )}
        </div>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Website', href: '/website' },
    { title: 'Taxonomy & Locations', href: '/website/taxonomy' },
];

type Coverage = {
    id: number;
    key: string;
    name: string;
    pages: number;
    published_pages: number;
    keywords: number;
    campaigns: number;
    content: number;
};

type ServiceLine = Coverage & { category: string | null };

type Messaging = {
    value_proposition?: string;
    pain_points?: string;
    differentiators?: string;
};

type Vertical = Coverage & {
    compliance_notes: string | null;
    messaging: Messaging | null;
    ads: number;
    case_studies: number;
    sequences: number;
    accounts: number;
};

type Location = {
    id: number;
    name: string;
    city: string | null;
    region: string | null;
    territory: string | null;
    is_active: boolean;
    has_page: boolean;
    published_page: boolean;
    leads: number;
    sqls: number;
    won_value: number;
    citations: number;
    consistent_citations: number;
    geo_keywords: number;
    campaigns: number;
    active_campaigns: number;
    content_pieces: number;
};

type CalendarGroup = {
    location: string;
    entries: { month: string; kind: string; title: string; status: string }[];
};

type ComplianceRow = {
    location: string;
    ok: boolean;
    checks: { key: string; label: string; ok: boolean; detail: string }[];
};

function money(cents: number): string {
    return `$${(cents / 100).toFixed(2)}`;
}

/**
 * Coverage in one word. A taxonomy row only earns its place if something targets
 * it, so "nothing built" is stated rather than left as a zero to interpret.
 */
function coverageLabel(row: Coverage): { label: string; variant: 'default' | 'secondary' | 'outline' } {
    if (row.pages === 0) return { label: 'Gap — no page', variant: 'outline' };
    if (row.published_pages === 0) return { label: 'Draft only', variant: 'secondary' };

    return { label: 'Live', variant: 'default' };
}

function CoverageCell({ value }: { value: number }) {
    return value === 0 ? <span className="text-muted-foreground">0</span> : <span className="font-medium">{value}</span>;
}

function ProvisionCard({ canManage }: { canManage: boolean }) {
    const provision = () => router.post(route('web.taxonomy.provision'), {}, { preserveScroll: true });

    return (
        <Card>
            <CardContent className="flex flex-wrap items-center justify-between gap-3 p-4">
                <div className="min-w-0 space-y-1">
                    <p className="font-medium">Standard MSP taxonomy</p>
                    <p className="text-muted-foreground text-sm">
                        Adds any of the standard MSP service lines and verticals that are missing. Rows that already exist are left exactly as they
                        are — names, categories and compliance notes you have edited are never overwritten, and nothing is removed. Safe to run more
                        than once.
                    </p>
                </div>
                {canManage ? (
                    <Button variant="secondary" onClick={provision}>
                        Provision taxonomy
                    </Button>
                ) : (
                    <p className="text-muted-foreground text-sm">
                        Provisioning requires the <code>web.taxonomy.manage</code> permission.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

function NewLocationDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm<{
        name: string;
        street: string;
        city: string;
        region: string;
        postal_code: string;
        country: string;
        phone: string;
        territory: string;
        gbp_place_id: string;
    }>({
        name: '',
        street: '',
        city: '',
        region: '',
        postal_code: '',
        country: '',
        phone: '',
        territory: '',
        gbp_place_id: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('web.locations.store'), {
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
                <Button>New location</Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <DialogTitle>New location</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <p className="text-muted-foreground text-sm">
                        The territory is what attributes leads and deals to this branch, by matching a company's city or region. A branch with no
                        territory, city or region attributes nothing.
                    </p>
                    <div className="grid gap-1">
                        <Label htmlFor="location_name">Name</Label>
                        <Input id="location_name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="location_street">Street</Label>
                        <Input id="location_street" value={form.data.street} onChange={(e) => form.setData('street', e.target.value)} />
                        <InputError message={form.errors.street} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="location_city">City</Label>
                            <Input id="location_city" value={form.data.city} onChange={(e) => form.setData('city', e.target.value)} />
                            <InputError message={form.errors.city} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="location_region">Region</Label>
                            <Input id="location_region" value={form.data.region} onChange={(e) => form.setData('region', e.target.value)} />
                            <InputError message={form.errors.region} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="location_postal">Postal code</Label>
                            <Input id="location_postal" value={form.data.postal_code} onChange={(e) => form.setData('postal_code', e.target.value)} />
                            <InputError message={form.errors.postal_code} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="location_country">Country</Label>
                            <Input id="location_country" value={form.data.country} onChange={(e) => form.setData('country', e.target.value)} />
                            <InputError message={form.errors.country} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="location_phone">Phone</Label>
                            <Input id="location_phone" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                            <InputError message={form.errors.phone} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="location_territory">Territory</Label>
                            <Input id="location_territory" value={form.data.territory} onChange={(e) => form.setData('territory', e.target.value)} />
                            <InputError message={form.errors.territory} />
                        </div>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="location_gbp">Google Business Profile place ID</Label>
                        <Input id="location_gbp" value={form.data.gbp_place_id} onChange={(e) => form.setData('gbp_place_id', e.target.value)} />
                        <InputError message={form.errors.gbp_place_id} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing || form.data.name.trim() === '' || form.data.city.trim() === ''}>
                            Add location
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function WebTaxonomy({
    service_lines,
    verticals,
    locations,
    calendar,
    compliance,
}: {
    service_lines: ServiceLine[];
    verticals: Vertical[];
    locations: Location[];
    calendar: CalendarGroup[];
    compliance: ComplianceRow[];
}) {
    const { can } = usePermissions();
    const canManage = can('web.taxonomy.manage');

    const serviceGaps = service_lines.filter((service) => service.pages === 0).length;
    const verticalGaps = verticals.filter((vertical) => vertical.pages === 0).length;
    const locationsWithPage = locations.filter((location) => location.published_page).length;

    const summary: { label: string; value: string | number; note: string }[] = [
        {
            label: 'Service lines',
            value: service_lines.length,
            note: serviceGaps === 0 ? 'every one has a page' : `${serviceGaps} with no page at all`,
        },
        {
            label: 'Verticals',
            value: verticals.length,
            note: verticalGaps === 0 ? 'every one has a page' : `${verticalGaps} with no page at all`,
        },
        {
            label: 'Locations',
            value: locations.length,
            note: `${locationsWithPage} with a published page`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Taxonomy & Locations" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title="Taxonomy & locations"
                        description="What the site actually targets — service lines, verticals and branches — and where nothing has been built yet"
                    />
                    {canManage && <NewLocationDialog />}
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    {summary.map((card) => (
                        <Card key={card.label}>
                            <CardContent className="p-4">
                                <p className="text-muted-foreground text-sm">{card.label}</p>
                                <p className="text-2xl font-semibold">{card.value}</p>
                                <p className="text-muted-foreground text-xs">{card.note}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <ProvisionCard canManage={canManage} />

                <div>
                    <h3 className="mb-2 text-sm font-medium">Service line coverage</h3>
                    {service_lines.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            No service lines yet. Provision the standard MSP taxonomy above to start from a known-good set.
                        </p>
                    ) : (
                        <div className="space-y-2">
                            <p className="text-muted-foreground text-sm">
                                Weakest first: the service lines with the fewest pages are at the top, because those are the ones nothing is selling.
                            </p>
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground">
                                        <tr>
                                            <th className="p-3 font-medium">Service line</th>
                                            <th className="p-3 font-medium">Coverage</th>
                                            <th className="p-3 text-center font-medium">Pages</th>
                                            <th className="p-3 text-center font-medium">Published</th>
                                            <th className="p-3 text-center font-medium">Keywords</th>
                                            <th className="p-3 text-center font-medium">Campaigns</th>
                                            <th className="p-3 text-center font-medium">Content</th>
                                            <th className="p-3 text-center font-medium">Ads</th>
                                            <th className="p-3 text-center font-medium">Case studies</th>
                                            <th className="p-3 text-center font-medium">Sequences</th>
                                            <th className="p-3 text-center font-medium">Accounts</th>
                                            <th className="p-3 font-medium">Messaging</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {service_lines.map((service) => {
                                            const coverage = coverageLabel(service);

                                            return (
                                                <tr key={service.id} className="hover:bg-muted/40 align-top">
                                                    <td className="p-3">
                                                        <p className="font-medium">{service.name}</p>
                                                        {service.category !== null && (
                                                            <p className="text-muted-foreground text-xs">{service.category}</p>
                                                        )}
                                                    </td>
                                                    <td className="p-3">
                                                        <Badge variant={coverage.variant}>{coverage.label}</Badge>
                                                    </td>
                                                    <td className="p-3 text-center">
                                                        <CoverageCell value={service.pages} />
                                                    </td>
                                                    <td className="p-3 text-center">
                                                        <CoverageCell value={service.published_pages} />
                                                    </td>
                                                    <td className="p-3 text-center">
                                                        <CoverageCell value={service.keywords} />
                                                    </td>
                                                    <td className="p-3 text-center">
                                                        <CoverageCell value={service.campaigns} />
                                                    </td>
                                                    <td className="p-3 text-center">
                                                        <CoverageCell value={service.content} />
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Vertical coverage</h3>
                    {verticals.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            No verticals yet. Provision the standard MSP taxonomy above to start from a known-good set.
                        </p>
                    ) : (
                        <div className="space-y-2">
                            <p className="text-muted-foreground text-sm">
                                Weakest first. Compliance notes travel with the vertical — they shape what a page for it is allowed to claim.
                            </p>
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground">
                                        <tr>
                                            <th className="p-3 font-medium">Vertical</th>
                                            <th className="p-3 font-medium">Coverage</th>
                                            <th className="p-3 font-medium">Compliance notes</th>
                                            <th className="p-3 text-center font-medium">Pages</th>
                                            <th className="p-3 text-center font-medium">Published</th>
                                            <th className="p-3 text-center font-medium">Keywords</th>
                                            <th className="p-3 text-center font-medium">Campaigns</th>
                                            <th className="p-3 text-center font-medium">Content</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {verticals.map((vertical) => {
                                            const coverage = coverageLabel(vertical);

                                            return (
                                                <tr key={vertical.id} className="hover:bg-muted/40 align-top">
                                                    <td className="p-3 font-medium">{vertical.name}</td>
                                                    <td className="p-3">
                                                        <Badge variant={coverage.variant}>{coverage.label}</Badge>
                                                    </td>
                                                    <td className="text-muted-foreground max-w-80 p-3">{vertical.compliance_notes ?? '—'}</td>
                                                    <td className="p-3 text-center">
                                                        <CoverageCell value={vertical.pages} />
                                                    </td>
                                                    <td className="p-3 text-center">
                                                        <CoverageCell value={vertical.published_pages} />
                                                    </td>
                                                    <td className="p-3 text-center">
                                                        <CoverageCell value={vertical.keywords} />
                                                    </td>
                                                    <td className="p-3 text-center">
                                                        <CoverageCell value={vertical.campaigns} />
                                                    </td>
                                                    <td className="p-3 text-center">
                                                        <CoverageCell value={vertical.content} />
                                                    </td>
                                                    <td className="p-3 text-center">
                                                        <CoverageCell value={vertical.ads} />
                                                    </td>
                                                    <td className="p-3 text-center">
                                                        <CoverageCell value={vertical.case_studies} />
                                                    </td>
                                                    <td className="p-3 text-center">
                                                        <CoverageCell value={vertical.sequences} />
                                                    </td>
                                                    <td className="p-3 text-center">
                                                        <CoverageCell value={vertical.accounts} />
                                                    </td>
                                                    <td className="max-w-64 p-3">
                                                        <MessagingCell vertical={vertical} canManage={canManage} />
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Locations</h3>
                    {locations.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No locations yet. Add a branch to track its own page and its own results.</p>
                    ) : (
                        <div className="space-y-2">
                            <p className="text-muted-foreground text-sm">
                                Leads and deals are attributed to a branch by matching a company's city or region to the branch's territory. A branch
                                with no matching companies shows zero rather than borrowing another branch's numbers.
                            </p>
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground">
                                        <tr>
                                            <th className="p-3 font-medium">Location</th>
                                            <th className="p-3 font-medium">Territory</th>
                                            <th className="p-3 font-medium">Location page</th>
                                            <th className="p-3 text-center font-medium">Citations</th>
                                            <th className="p-3 text-center font-medium">Geo keywords</th>
                                            <th className="p-3 text-center font-medium">Campaigns</th>
                                            <th className="p-3 text-center font-medium">Content</th>
                                            <th className="p-3 text-center font-medium">Leads</th>
                                            <th className="p-3 text-center font-medium">SQLs</th>
                                            <th className="p-3 text-center font-medium">Won value</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {locations.map((location) => (
                                            <tr key={location.id} className="hover:bg-muted/40 align-top">
                                                <td className="p-3">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="font-medium">{location.name}</span>
                                                        {!location.is_active && <Badge variant="outline">Inactive</Badge>}
                                                    </div>
                                                    <p className="text-muted-foreground text-xs">
                                                        {[location.city, location.region].filter(Boolean).join(', ') || 'No city or region'}
                                                    </p>
                                                </td>
                                                <td className="text-muted-foreground p-3">{location.territory ?? 'Not set'}</td>
                                                <td className="p-3">
                                                    {location.published_page ? (
                                                        <Badge variant="default">Published</Badge>
                                                    ) : location.has_page ? (
                                                        <Badge variant="secondary">Draft only</Badge>
                                                    ) : (
                                                        <Badge variant="outline">Gap — no page</Badge>
                                                    )}
                                                </td>
                                                <td className="p-3 text-center">
                                                    <span className="text-muted-foreground">
                                                        {location.consistent_citations}/{location.citations}
                                                    </span>
                                                </td>
                                                <td className="p-3 text-center">
                                                    <CoverageCell value={location.geo_keywords} />
                                                </td>
                                                <td className="p-3 text-center">
                                                    <span className="text-muted-foreground">
                                                        {location.active_campaigns}/{location.campaigns}
                                                    </span>
                                                </td>
                                                <td className="p-3 text-center">
                                                    <CoverageCell value={location.content_pieces} />
                                                </td>
                                                <td className="p-3 text-center">
                                                    <CoverageCell value={location.leads} />
                                                </td>
                                                <td className="p-3 text-center">
                                                    <CoverageCell value={location.sqls} />
                                                </td>
                                                <td className="text-muted-foreground p-3 text-center">{money(location.won_value)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Regional marketing calendar</h3>
                    {calendar.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            No campaigns or content yet. Bind a campaign or content piece to a branch to see the regional programme; unbound work
                            shows under Central.
                        </p>
                    ) : (
                        <div className="grid gap-4 lg:grid-cols-2">
                            {calendar.map((group) => (
                                <Card key={group.location} className="min-w-0">
                                    <CardContent className="p-4">
                                        <h4 className="mb-2 text-sm font-medium">{group.location}</h4>
                                        <ul className="space-y-1 text-sm">
                                            {group.entries.map((entry, i) => (
                                                <li key={`${entry.title}-${i}`} className="flex flex-wrap items-center gap-2">
                                                    <span className="text-muted-foreground w-16 shrink-0 text-xs">{entry.month}</span>
                                                    <Badge variant="outline">{entry.kind}</Badge>
                                                    <span className="min-w-0 flex-1 truncate">{entry.title}</span>
                                                    <Badge variant="secondary">{entry.status}</Badge>
                                                </li>
                                            ))}
                                        </ul>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Brand consistency by branch</h3>
                    {compliance.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No active branches to check.</p>
                    ) : (
                        <div className="grid gap-4 lg:grid-cols-2">
                            {compliance.map((row) => (
                                <Card key={row.location}>
                                    <CardContent className="p-4">
                                        <div className="mb-2 flex items-center gap-2">
                                            <h4 className="text-sm font-medium">{row.location}</h4>
                                            <Badge variant={row.ok ? 'default' : 'secondary'}>{row.ok ? 'Consistent' : 'Needs work'}</Badge>
                                        </div>
                                        <ul className="space-y-1 text-sm">
                                            {row.checks.map((check) => (
                                                <li key={check.key} className="text-muted-foreground">
                                                    <span className={check.ok ? 'text-green-600' : 'text-destructive'}>{check.ok ? '✓' : '✗'}</span>{' '}
                                                    <span className="text-foreground font-medium">{check.label}</span> — {check.detail}
                                                </li>
                                            ))}
                                        </ul>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
