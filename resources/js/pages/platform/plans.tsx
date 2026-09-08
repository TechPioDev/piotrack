import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Plans', href: '/platform/plans' }];

type Entitlement = {
    key: string;
    kind: string;
    bool_value: boolean | null;
    int_value: number | null;
};

type Plan = {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
    entitlements: Entitlement[];
};

type Coupon = {
    id: number;
    code: string;
    type: string;
    value: number;
    duration: string | null;
    max_redemptions: number | null;
    times_redeemed: number;
    expires_at: string | null;
    is_active: boolean;
};

type UnpaidInvoice = {
    id: number;
    number: string;
    organization: string | null;
    total: number;
    status: string;
    due_at: string | null;
};

function CouponDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm({ code: '', type: 'percent', value: '', duration: 'once', max_redemptions: '', expires_at: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('platform.coupons.store'), {
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
                <Button variant="outline" size="sm">
                    New coupon
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New coupon</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="c-code">Code</Label>
                        <Input id="c-code" value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} />
                        <InputError message={form.errors.code} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="c-type">Type</Label>
                            <Select value={form.data.type} onValueChange={(v) => form.setData('type', v)}>
                                <SelectTrigger id="c-type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="percent">Percent off</SelectItem>
                                    <SelectItem value="fixed">Fixed amount (cents)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="c-value">{form.data.type === 'percent' ? 'Percent' : 'Amount (cents)'}</Label>
                            <Input
                                id="c-value"
                                type="number"
                                min="1"
                                value={form.data.value}
                                onChange={(e) => form.setData('value', e.target.value)}
                            />
                            <InputError message={form.errors.value} />
                        </div>
                    </div>
                    <div className="grid grid-cols-3 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="c-duration">Duration</Label>
                            <Select value={form.data.duration} onValueChange={(v) => form.setData('duration', v)}>
                                <SelectTrigger id="c-duration">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="once">Once</SelectItem>
                                    <SelectItem value="forever">Forever</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="c-max">Max uses</Label>
                            <Input
                                id="c-max"
                                type="number"
                                min="1"
                                placeholder="unlimited"
                                value={form.data.max_redemptions}
                                onChange={(e) => form.setData('max_redemptions', e.target.value)}
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="c-expires">Expires</Label>
                            <Input
                                id="c-expires"
                                type="date"
                                value={form.data.expires_at}
                                onChange={(e) => form.setData('expires_at', e.target.value)}
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
    );
}

function LimitCell({ plan, ent }: { plan: Plan; ent: Entitlement }) {
    const [value, setValue] = useState(ent.int_value === null ? '' : String(ent.int_value));

    const save = () => {
        router.post(
            route('platform.plans.entitlements', plan.id),
            { key: ent.key, kind: 'limit', int_value: value === '' ? null : Number(value) },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex items-center justify-center gap-1">
            <Input
                className="h-8 w-24 text-center"
                type="number"
                min={0}
                placeholder="unlimited"
                value={value}
                onChange={(e) => setValue(e.target.value)}
                onBlur={save}
            />
        </div>
    );
}

export default function PlatformPlans({ plans, coupons, unpaid_invoices }: { plans: Plan[]; coupons: Coupon[]; unpaid_invoices: UnpaidInvoice[] }) {
    // The matrix rows are the union of every key any plan defines.
    const featureKeys = [...new Set(plans.flatMap((p) => p.entitlements.filter((e) => e.kind === 'feature').map((e) => e.key)))].sort();
    const limitKeys = [...new Set(plans.flatMap((p) => p.entitlements.filter((e) => e.kind === 'limit').map((e) => e.key)))].sort();

    const ent = (plan: Plan, key: string): Entitlement | undefined => plan.entitlements.find((e) => e.key === key);

    const toggleFeature = (plan: Plan, key: string, enabled: boolean) => {
        router.post(route('platform.plans.entitlements', plan.id), { key, kind: 'feature', bool_value: enabled }, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Plans" />
            <div className="space-y-6 p-4">
                <Heading
                    title="Plan entitlements"
                    description="The plan × entitlement matrix — feature access and limits. Tenants pick changes up on their next request."
                />

                <div>
                    <h3 className="mb-2 text-sm font-medium">Features</h3>
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">Feature</th>
                                    {plans.map((plan) => (
                                        <th key={plan.id} className="p-3 text-center font-medium">
                                            {plan.name}
                                            {!plan.is_active && (
                                                <Badge variant="secondary" className="ml-1">
                                                    inactive
                                                </Badge>
                                            )}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {featureKeys.map((key) => (
                                    <tr key={key} className="hover:bg-muted/40">
                                        <td className="p-3 font-medium">{key.replace(/_/g, ' ')}</td>
                                        {plans.map((plan) => (
                                            <td key={plan.id} className="p-3 text-center">
                                                <Checkbox
                                                    checked={ent(plan, key)?.bool_value === true}
                                                    onCheckedChange={(v) => toggleFeature(plan, key, Boolean(v))}
                                                />
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Limits</h3>
                    <p className="text-muted-foreground mb-2 text-xs">Blank = unlimited. Values save when a field loses focus.</p>
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">Limit</th>
                                    {plans.map((plan) => (
                                        <th key={plan.id} className="p-3 text-center font-medium">
                                            {plan.name}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {limitKeys.map((key) => (
                                    <tr key={key} className="hover:bg-muted/40">
                                        <td className="p-3 font-medium">{key.replace(/_/g, ' ')}</td>
                                        {plans.map((plan) => {
                                            const e = ent(plan, key);
                                            return (
                                                <td key={plan.id} className="p-3 text-center">
                                                    <LimitCell
                                                        key={`${plan.id}:${key}:${e?.int_value ?? 'null'}`}
                                                        plan={plan}
                                                        ent={e ?? { key, kind: 'limit', bool_value: null, int_value: null }}
                                                    />
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* ADMIN-002: coupon management */}
                <div>
                    <div className="mb-2 flex items-center justify-between">
                        <h3 className="text-sm font-medium">Coupons</h3>
                        <CouponDialog />
                    </div>
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">Code</th>
                                    <th className="p-3 font-medium">Discount</th>
                                    <th className="p-3 font-medium">Duration</th>
                                    <th className="p-3 text-right font-medium">Used</th>
                                    <th className="p-3 font-medium">Expires</th>
                                    <th className="p-3 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {coupons.map((coupon) => (
                                    <tr key={coupon.id} className="hover:bg-muted/40">
                                        <td className="p-3 font-medium">{coupon.code}</td>
                                        <td className="p-3">{coupon.type === 'percent' ? `${coupon.value}%` : formatMoney(coupon.value)}</td>
                                        <td className="p-3">{coupon.duration ?? 'once'}</td>
                                        <td className="p-3 text-right tabular-nums">
                                            {coupon.times_redeemed}
                                            {coupon.max_redemptions !== null && ` / ${coupon.max_redemptions}`}
                                        </td>
                                        <td className="p-3">{coupon.expires_at ?? '—'}</td>
                                        <td className="p-3">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    router.patch(route('platform.coupons.toggle', coupon.id), {}, { preserveScroll: true })
                                                }
                                            >
                                                <Badge variant={coupon.is_active ? 'secondary' : 'destructive'}>
                                                    {coupon.is_active ? 'active' : 'inactive'}
                                                </Badge>
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                                {coupons.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="text-muted-foreground p-3 text-sm">
                                            No coupons yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* ADMIN-002: manual payment actions via the provider seam */}
                <div>
                    <h3 className="mb-2 text-sm font-medium">Unpaid invoices</h3>
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">Invoice</th>
                                    <th className="p-3 font-medium">Organization</th>
                                    <th className="p-3 text-right font-medium">Total</th>
                                    <th className="p-3 font-medium">Status</th>
                                    <th className="p-3 font-medium">Due</th>
                                    <th className="p-3 font-medium"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {unpaid_invoices.map((invoice) => (
                                    <tr key={invoice.id} className="hover:bg-muted/40">
                                        <td className="p-3 font-medium">{invoice.number}</td>
                                        <td className="p-3">{invoice.organization ?? '—'}</td>
                                        <td className="p-3 text-right tabular-nums">{formatMoney(invoice.total)}</td>
                                        <td className="p-3">
                                            <Badge variant="destructive">{invoice.status}</Badge>
                                        </td>
                                        <td className="p-3">{invoice.due_at ?? '—'}</td>
                                        <td className="p-3 text-right">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    router.post(route('platform.invoices.retry', invoice.id), {}, { preserveScroll: true })
                                                }
                                            >
                                                Retry payment
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                                {unpaid_invoices.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="text-muted-foreground p-3 text-sm">
                                            No unpaid invoices.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
