import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { formatMoney } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Billing', href: '/billing' },
    { title: 'Invoices', href: '/billing/invoices' },
];

type Invoice = {
    id: number;
    number: string;
    status: string;
    total: number;
    currency: string;
    created_at: string;
    paid_at: string | null;
};

type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function Invoices({ invoices }: { invoices: Paginated<Invoice> }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Invoices" />

            <SettingsLayout>
                <div className="space-y-4">
                    <HeadingSmall title="Invoices" description="Your full billing history" />

                    {invoices.data.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No invoices yet.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3">Invoice</th>
                                        <th className="p-3">Status</th>
                                        <th className="p-3">Date</th>
                                        <th className="p-3 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {invoices.data.map((inv) => (
                                        <tr key={inv.id}>
                                            <td className="p-3">
                                                <Link href={route('billing.invoices.show', inv.id)} className="font-medium hover:underline">
                                                    {inv.number}
                                                </Link>
                                            </td>
                                            <td className="p-3">
                                                <Badge variant={inv.status === 'paid' ? 'default' : 'secondary'}>{inv.status}</Badge>
                                            </td>
                                            <td className="text-muted-foreground p-3">{new Date(inv.created_at).toLocaleDateString()}</td>
                                            <td className="p-3 text-right">{formatMoney(inv.total, inv.currency)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
