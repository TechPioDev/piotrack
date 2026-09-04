import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { formatMoney, humanizeKey } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Billing', href: '/billing' }];

type UsageRow = { key: string; used: number; limit: number | null; remaining: number | null };
type InvoiceRow = { id: number; number: string; status: string; total: number; currency: string; created_at: string };

type Subscription = {
    id: number;
    plan: { code: string; name: string };
    status: string;
    interval: string;
    quantity: number;
    on_trial: boolean;
    trial_ends_at: string | null;
    current_period_end: string | null;
    cancel_at_period_end: boolean;
    ends_at: string | null;
};

type BillingProfile = {
    billing_email: string | null;
    company_name: string | null;
    tax_id: string | null;
} | null;

type AddonOption = { code: string; name: string; price: number };

type BillingProps = {
    subscription: Subscription | null;
    usage: UsageRow[];
    invoices: InvoiceRow[];
    billingProfile: BillingProfile;
    addonCatalog: AddonOption[];
    attachedAddons: AddonOption[];
};

const statusVariant: Record<string, 'default' | 'secondary' | 'outline' | 'destructive'> = {
    active: 'default',
    trialing: 'secondary',
    past_due: 'destructive',
    suspended: 'destructive',
    canceled: 'outline',
    expired: 'outline',
};

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString() : '—';
}

export default function Billing({ subscription, usage, invoices, billingProfile, addonCatalog, attachedAddons }: BillingProps) {
    const { can } = usePermissions();
    const attachedCodes = new Set(attachedAddons.map((addon) => addon.code));
    const profile = useForm({
        billing_email: billingProfile?.billing_email ?? '',
        company_name: billingProfile?.company_name ?? '',
        tax_id: billingProfile?.tax_id ?? '',
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Billing" />

            <SettingsLayout>
                <div className="space-y-8">
                    <div className="space-y-4">
                        <HeadingSmall title="Subscription" description="Your current plan and status" />

                        {subscription ? (
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="flex items-center gap-2">
                                            {subscription.plan.name}
                                            <Badge variant={statusVariant[subscription.status] ?? 'secondary'}>
                                                {subscription.status.replace('_', ' ')}
                                            </Badge>
                                        </CardTitle>
                                        <Button asChild variant="outline" size="sm">
                                            <Link href={route('billing.plans')}>Change plan</Link>
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-3 text-sm">
                                    {subscription.on_trial && (
                                        <p className="text-muted-foreground">Trial ends {formatDate(subscription.trial_ends_at)}.</p>
                                    )}
                                    {subscription.cancel_at_period_end ? (
                                        <div className="flex items-center justify-between rounded-md bg-amber-50 p-3 dark:bg-amber-950/20">
                                            <span>Cancels on {formatDate(subscription.ends_at)}.</span>
                                            <Button
                                                size="sm"
                                                onClick={() => router.post(route('billing.subscription.resume'), {}, { preserveScroll: true })}
                                            >
                                                Resume
                                            </Button>
                                        </div>
                                    ) : (
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground">
                                                Renews {formatDate(subscription.current_period_end)} · {subscription.interval}
                                            </span>
                                            {subscription.status !== 'canceled' && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-red-600"
                                                    onClick={() =>
                                                        router.post(
                                                            route('billing.subscription.cancel'),
                                                            { immediately: false },
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                >
                                                    Cancel subscription
                                                </Button>
                                            )}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ) : (
                            <p className="text-muted-foreground text-sm">
                                No active subscription.{' '}
                                <Link href={route('billing.plans')} className="underline">
                                    Choose a plan
                                </Link>
                                .
                            </p>
                        )}

                        {subscription && can('billing.manage') && (
                            <div className="space-y-3">
                                <HeadingSmall title="Add-ons" description="Entitlements apply immediately; billing starts with the next renewal" />
                                <div className="space-y-2">
                                    {addonCatalog.map((addon) => (
                                        <div key={addon.code} className="flex items-center justify-between rounded-md border p-3 text-sm">
                                            <div>
                                                <p className="font-medium">{addon.name}</p>
                                                <p className="text-muted-foreground text-xs">${(addon.price / 100).toFixed(2)}/mo</p>
                                            </div>
                                            {attachedCodes.has(addon.code) ? (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="text-red-600"
                                                    onClick={() =>
                                                        router.delete(route('billing.addons.destroy'), {
                                                            data: { code: addon.code },
                                                            preserveScroll: true,
                                                        })
                                                    }
                                                >
                                                    Remove
                                                </Button>
                                            ) : (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(route('billing.addons.store'), { code: addon.code }, { preserveScroll: true })
                                                    }
                                                >
                                                    Add
                                                </Button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                                <Button asChild variant="outline" size="sm">
                                    <a href={route('billing.payment-method')}>Manage payment method</a>
                                </Button>
                                <p className="text-muted-foreground text-xs">
                                    Card details are managed by the payment provider and never touch this application.
                                </p>
                            </div>
                        )}
                    </div>

                    <div className="space-y-4">
                        <HeadingSmall title="Usage" description="Your consumption against plan limits" />
                        <div className="space-y-3">
                            {usage.map((row) => {
                                const pct = row.limit ? Math.min(100, Math.round((row.used / row.limit) * 100)) : 0;
                                return (
                                    <div key={row.key} className="space-y-1">
                                        <div className="flex items-center justify-between text-sm">
                                            <span>{humanizeKey(row.key)}</span>
                                            <span className="text-muted-foreground">
                                                {row.used} / {row.limit === null ? 'Unlimited' : row.limit}
                                            </span>
                                        </div>
                                        {row.limit !== null && (
                                            <div className="bg-muted h-2 overflow-hidden rounded-full">
                                                <div
                                                    className={`h-full rounded-full ${pct >= 100 ? 'bg-red-500' : 'bg-primary'}`}
                                                    style={{ width: `${pct}%` }}
                                                />
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <HeadingSmall title="Invoices" description="Recent billing history" />
                            <Button asChild variant="outline" size="sm">
                                <Link href={route('billing.invoices.index')}>View all</Link>
                            </Button>
                        </div>

                        {invoices.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No invoices yet.</p>
                        ) : (
                            <ul className="divide-y rounded-lg border">
                                {invoices.map((inv) => (
                                    <li key={inv.id} className="flex items-center justify-between p-3 text-sm">
                                        <Link href={route('billing.invoices.show', inv.id)} className="font-medium hover:underline">
                                            {inv.number}
                                        </Link>
                                        <div className="flex items-center gap-3">
                                            <Badge variant={inv.status === 'paid' ? 'default' : 'secondary'}>{inv.status}</Badge>
                                            <span>{formatMoney(inv.total, inv.currency)}</span>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    {can('billing.manage') && (
                        <form
                            onSubmit={
                                ((e) => {
                                    e.preventDefault();
                                    profile.patch(route('billing.profile.update'), { preserveScroll: true });
                                }) as FormEventHandler
                            }
                            className="space-y-4"
                        >
                            <HeadingSmall title="Billing details" description="Used on your invoices and receipts" />
                            <div className="grid max-w-md gap-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="billing_email">Billing email</Label>
                                    <Input
                                        id="billing_email"
                                        type="email"
                                        value={profile.data.billing_email}
                                        onChange={(e) => profile.setData('billing_email', e.target.value)}
                                    />
                                    <InputError message={profile.errors.billing_email} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="company_name">Company name</Label>
                                    <Input
                                        id="company_name"
                                        value={profile.data.company_name}
                                        onChange={(e) => profile.setData('company_name', e.target.value)}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="tax_id">Tax ID</Label>
                                    <Input id="tax_id" value={profile.data.tax_id} onChange={(e) => profile.setData('tax_id', e.target.value)} />
                                </div>
                            </div>
                            <Button disabled={profile.processing}>Save billing details</Button>
                        </form>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
