import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { formatMoney } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

type LineItem = { description: string; quantity: number; unit_amount: number; amount: number };

type Invoice = {
    id: number;
    number: string;
    status: string;
    currency: string;
    subtotal: number;
    discount: number;
    tax: number;
    total: number;
    amount_paid: number;
    created_at: string;
    paid_at: string | null;
    line_items: LineItem[];
};

export default function InvoiceDetail({ invoice }: { invoice: Invoice }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Billing', href: '/billing' },
        { title: 'Invoices', href: '/billing/invoices' },
        { title: invoice.number, href: `/billing/invoices/${invoice.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Invoice ${invoice.number}`} />

            <SettingsLayout>
                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <HeadingSmall title={`Invoice ${invoice.number}`} description={new Date(invoice.created_at).toLocaleString()} />
                        <Badge variant={invoice.status === 'paid' ? 'default' : 'secondary'}>{invoice.status}</Badge>
                    </div>

                    <Card>
                        <CardContent className="p-0">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3">Description</th>
                                        <th className="p-3 text-right">Qty</th>
                                        <th className="p-3 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {invoice.line_items.map((li, i) => (
                                        <tr key={i}>
                                            <td className="p-3">{li.description}</td>
                                            <td className="p-3 text-right">{li.quantity}</td>
                                            <td className="p-3 text-right">{formatMoney(li.amount, invoice.currency)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot className="border-t">
                                    <tr>
                                        <td className="text-muted-foreground p-3 text-right" colSpan={2}>
                                            Subtotal
                                        </td>
                                        <td className="p-3 text-right">{formatMoney(invoice.subtotal, invoice.currency)}</td>
                                    </tr>
                                    {invoice.discount > 0 && (
                                        <tr>
                                            <td className="text-muted-foreground p-3 text-right" colSpan={2}>
                                                Discount
                                            </td>
                                            <td className="p-3 text-right">-{formatMoney(invoice.discount, invoice.currency)}</td>
                                        </tr>
                                    )}
                                    <tr className="font-semibold">
                                        <td className="p-3 text-right" colSpan={2}>
                                            Total
                                        </td>
                                        <td className="p-3 text-right">{formatMoney(invoice.total, invoice.currency)}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </CardContent>
                    </Card>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
