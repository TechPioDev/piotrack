import Heading from '@/components/heading';
import { InitialAvatar } from '@/components/initial-avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type Company = {
    id: number;
    name: string;
    domain: string | null;
    industry: string | null;
    size: string | null;
    phone: string | null;
    website: string | null;
};
type Contact = { id: number; name: string; email: string | null; title: string | null };
type DealRow = { id: number; name: string; value: number; status: string };

export default function CompanyShow({ company, contacts, deals }: { company: Company; contacts: Contact[]; deals: DealRow[] }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Companies', href: '/crm/companies' },
        { title: company.name, href: `/crm/companies/${company.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={company.name} />
            <div className="grid gap-4 p-4 lg:grid-cols-3">
                <div className="space-y-4 lg:col-span-1">
                    <div className="flex items-center gap-3">
                        <InitialAvatar name={company.name} className="size-10 rounded-md text-sm" />
                        <Heading title={company.name} description={company.industry ?? undefined} />
                    </div>
                    <Card>
                        <CardContent className="space-y-2 p-4 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Domain</span>
                                <span>{company.domain ?? '—'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Website</span>
                                <span>{company.website ?? '—'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Phone</span>
                                <span>{company.phone ?? '—'}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="space-y-4 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">Contacts ({contacts.length})</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 p-4 pt-0 text-sm">
                            {contacts.length === 0 ? (
                                <p className="text-muted-foreground">No contacts.</p>
                            ) : (
                                contacts.map((c) => (
                                    <div key={c.id} className="flex justify-between">
                                        <Link href={route('crm.contacts.show', c.id)} className="hover:text-brand-strong hover:underline">
                                            {c.name}
                                        </Link>
                                        <span className="text-muted-foreground">{c.email}</span>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">Deals ({deals.length})</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 p-4 pt-0 text-sm">
                            {deals.length === 0 ? (
                                <p className="text-muted-foreground">No deals.</p>
                            ) : (
                                deals.map((d) => (
                                    <div key={d.id} className="flex items-center justify-between">
                                        <Link href={route('crm.deals.show', d.id)} className="hover:text-brand-strong hover:underline">
                                            {d.name}
                                        </Link>
                                        <span className="flex items-center gap-2">
                                            <Badge variant="outline">{d.status}</Badge>
                                            {formatMoney(d.value)}
                                        </span>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
