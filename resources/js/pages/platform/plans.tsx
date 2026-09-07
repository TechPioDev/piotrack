import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

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

export default function PlatformPlans({ plans }: { plans: Plan[] }) {
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
            </div>
        </AppLayout>
    );
}
