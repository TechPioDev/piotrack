import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Setup', href: '/onboarding/setup' }];

type Taxon = { id: number; key: string; name: string; is_active: boolean };
type Connector = { key: string; name: string; category?: string; connectable?: boolean; status?: string };

type Props = {
    services: Taxon[];
    verticals: Taxon[];
    location: { id: number; city: string | null; region: string | null } | null;
    website_url: string | null;
    goals: Record<string, number>;
    icpRules: { name: string; attribute: string; value: string; points: number; is_active: boolean }[];
    competitors: { id: number; name: string; domain: string | null }[];
    connectors: Connector[];
};

const STEPS = ['Business profile', 'Website', 'Goals', 'Ideal customer', 'Competitors', 'Integrations', 'Finish'];

export default function OnboardingSetup({ services, verticals, location, website_url, goals, icpRules, competitors, connectors }: Props) {
    const [step, setStep] = useState(0);

    const profileForm = useForm<{ services: string[]; verticals: string[]; city: string; region: string }>({
        services: services.filter((s) => s.is_active).map((s) => s.key),
        verticals: verticals.filter((v) => v.is_active).map((v) => v.key),
        city: location?.city ?? '',
        region: location?.region ?? '',
    });
    const websiteForm = useForm<{ website_url: string }>({ website_url: website_url ?? '' });
    const goalsForm = useForm<{ leads: string; sqls: string; mrr: string }>({
        leads: goals.leads ? String(goals.leads) : '',
        sqls: goals.sqls ? String(goals.sqls) : '',
        mrr: goals.mrr ? String(goals.mrr) : '',
    });
    const icpForm = useForm<{ industry: string; company_size: string; region: string }>({ industry: '', company_size: '', region: '' });
    const competitorForm = useForm<{ name: string; domain: string }>({ name: '', domain: '' });

    const toggle = (list: string[], key: string) => (list.includes(key) ? list.filter((k) => k !== key) : [...list, key]);

    const submitAnd =
        (submit: () => void): FormEventHandler =>
        (e) => {
            e.preventDefault();
            submit();
        };

    const next = () => setStep((s) => Math.min(s + 1, STEPS.length - 1));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Setup" />
            <div className="mx-auto max-w-3xl space-y-6 p-4">
                <Heading
                    title="Set up your growth engine"
                    description="Seven short steps — everything lands in the real records the platform runs on, and you can leave and come back any time"
                />

                <div className="flex flex-wrap gap-2">
                    {STEPS.map((label, i) => (
                        <Button key={label} size="sm" variant={i === step ? 'default' : 'outline'} onClick={() => setStep(i)}>
                            {i + 1}. {label}
                        </Button>
                    ))}
                </div>

                {step === 0 && (
                    <Card>
                        <CardContent className="p-4">
                            <form
                                onSubmit={submitAnd(() => profileForm.post(route('onboarding.business'), { preserveScroll: true, onSuccess: next }))}
                                className="space-y-4"
                            >
                                <div>
                                    <Label className="mb-2 block">Which services do you offer?</Label>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {services.map((service) => (
                                            <label key={service.key} className="flex items-center gap-2 text-sm">
                                                <Checkbox
                                                    checked={profileForm.data.services.includes(service.key)}
                                                    onCheckedChange={() =>
                                                        profileForm.setData('services', toggle(profileForm.data.services, service.key))
                                                    }
                                                />
                                                {service.name}
                                            </label>
                                        ))}
                                    </div>
                                </div>
                                <div>
                                    <Label className="mb-2 block">Which industries do you serve?</Label>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {verticals.map((vertical) => (
                                            <label key={vertical.key} className="flex items-center gap-2 text-sm">
                                                <Checkbox
                                                    checked={profileForm.data.verticals.includes(vertical.key)}
                                                    onCheckedChange={() =>
                                                        profileForm.setData('verticals', toggle(profileForm.data.verticals, vertical.key))
                                                    }
                                                />
                                                {vertical.name}
                                            </label>
                                        ))}
                                    </div>
                                </div>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="grid gap-1">
                                        <Label htmlFor="setup_city">Home market (city)</Label>
                                        <Input
                                            id="setup_city"
                                            value={profileForm.data.city}
                                            onChange={(e) => profileForm.setData('city', e.target.value)}
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="setup_region">Region / state</Label>
                                        <Input
                                            id="setup_region"
                                            value={profileForm.data.region}
                                            onChange={(e) => profileForm.setData('region', e.target.value)}
                                        />
                                    </div>
                                </div>
                                <Button type="submit" disabled={profileForm.processing}>
                                    Save and continue
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {step === 1 && (
                    <Card>
                        <CardContent className="p-4">
                            <form
                                onSubmit={submitAnd(() => websiteForm.post(route('onboarding.website'), { preserveScroll: true, onSuccess: next }))}
                                className="space-y-3"
                            >
                                <div className="grid gap-1">
                                    <Label htmlFor="setup_site">Your website</Label>
                                    <Input
                                        id="setup_site"
                                        type="url"
                                        placeholder="https://your-msp.com"
                                        value={websiteForm.data.website_url}
                                        onChange={(e) => websiteForm.setData('website_url', e.target.value)}
                                    />
                                    <InputError message={websiteForm.errors.website_url} />
                                    <p className="text-muted-foreground text-xs">The finish step runs your first technical audit against it.</p>
                                </div>
                                <Button type="submit" disabled={websiteForm.processing}>
                                    Save and continue
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {step === 2 && (
                    <Card>
                        <CardContent className="p-4">
                            <form
                                onSubmit={submitAnd(() => goalsForm.post(route('onboarding.goals'), { preserveScroll: true, onSuccess: next }))}
                                className="space-y-3"
                            >
                                <p className="text-muted-foreground text-sm">Targets for the next 90 days — tracked on your strategy dashboard.</p>
                                <div className="grid gap-3 sm:grid-cols-3">
                                    <div className="grid gap-1">
                                        <Label htmlFor="goal_leads">New leads</Label>
                                        <Input
                                            id="goal_leads"
                                            type="number"
                                            value={goalsForm.data.leads}
                                            onChange={(e) => goalsForm.setData('leads', e.target.value)}
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="goal_sqls">SQLs</Label>
                                        <Input
                                            id="goal_sqls"
                                            type="number"
                                            value={goalsForm.data.sqls}
                                            onChange={(e) => goalsForm.setData('sqls', e.target.value)}
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="goal_mrr">New MRR ($)</Label>
                                        <Input
                                            id="goal_mrr"
                                            type="number"
                                            value={goalsForm.data.mrr}
                                            onChange={(e) => goalsForm.setData('mrr', e.target.value)}
                                        />
                                    </div>
                                </div>
                                <Button type="submit" disabled={goalsForm.processing}>
                                    Save and continue
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {step === 3 && (
                    <Card>
                        <CardContent className="p-4">
                            <form
                                onSubmit={submitAnd(() => icpForm.post(route('onboarding.icp'), { preserveScroll: true, onSuccess: next }))}
                                className="space-y-3"
                            >
                                <p className="text-muted-foreground text-sm">
                                    Your ideal customer becomes live scoring rules — matching leads score higher immediately.
                                </p>
                                <div className="grid gap-3 sm:grid-cols-3">
                                    <div className="grid gap-1">
                                        <Label htmlFor="icp_industry">Industry</Label>
                                        <Input
                                            id="icp_industry"
                                            placeholder="Manufacturing"
                                            value={icpForm.data.industry}
                                            onChange={(e) => icpForm.setData('industry', e.target.value)}
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="icp_size">Company size</Label>
                                        <Input
                                            id="icp_size"
                                            placeholder="100-250"
                                            value={icpForm.data.company_size}
                                            onChange={(e) => icpForm.setData('company_size', e.target.value)}
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="icp_region">Region</Label>
                                        <Input
                                            id="icp_region"
                                            placeholder="PA"
                                            value={icpForm.data.region}
                                            onChange={(e) => icpForm.setData('region', e.target.value)}
                                        />
                                    </div>
                                </div>
                                {icpRules.length > 0 && (
                                    <div className="flex flex-wrap gap-1">
                                        {icpRules.map((rule) => (
                                            <Badge key={rule.name} variant="outline">
                                                {rule.name}: {rule.value} (+{rule.points})
                                            </Badge>
                                        ))}
                                    </div>
                                )}
                                <Button type="submit" disabled={icpForm.processing}>
                                    Save and continue
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {step === 4 && (
                    <Card>
                        <CardContent className="space-y-3 p-4">
                            {competitors.length > 0 && (
                                <div className="flex flex-wrap gap-1">
                                    {competitors.map((competitor) => (
                                        <Badge key={competitor.id} variant="outline">
                                            {competitor.name}
                                            {competitor.domain ? ` (${competitor.domain})` : ''}
                                        </Badge>
                                    ))}
                                </div>
                            )}
                            <form
                                onSubmit={submitAnd(() => {
                                    competitorForm.transform((data) => ({ competitors: [{ name: data.name, domain: data.domain || null }] }));
                                    competitorForm.post(route('onboarding.competitors'), {
                                        preserveScroll: true,
                                        onSuccess: () => competitorForm.reset(),
                                    });
                                })}
                                className="flex flex-wrap items-end gap-2"
                            >
                                <div className="grid min-w-40 flex-1 gap-1">
                                    <Label htmlFor="comp_name">Competitor name</Label>
                                    <Input
                                        id="comp_name"
                                        value={competitorForm.data.name}
                                        onChange={(e) => competitorForm.setData('name', e.target.value)}
                                    />
                                </div>
                                <div className="grid min-w-40 flex-1 gap-1">
                                    <Label htmlFor="comp_domain">Domain</Label>
                                    <Input
                                        id="comp_domain"
                                        placeholder="rivalmsp.com"
                                        value={competitorForm.data.domain}
                                        onChange={(e) => competitorForm.setData('domain', e.target.value)}
                                    />
                                </div>
                                <Button type="submit" variant="outline" disabled={competitorForm.processing}>
                                    Add
                                </Button>
                                <Button type="button" onClick={next}>
                                    Continue
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {step === 5 && (
                    <Card>
                        <CardContent className="space-y-3 p-4">
                            <p className="text-muted-foreground text-sm">
                                Everything Piotrack can connect to. Items marked connectable are ready for their credentials under{' '}
                                <Link href="/settings/integrations" className="underline">
                                    Settings → Integrations
                                </Link>
                                ; the rest wait on vendor app registrations.
                            </p>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {connectors.map((connector) => (
                                    <div key={connector.key} className="flex items-center justify-between gap-2 rounded-md border p-2 text-sm">
                                        <span>{connector.name}</span>
                                        <Badge
                                            variant={connector.status === 'connected' ? 'default' : connector.connectable ? 'secondary' : 'outline'}
                                        >
                                            {connector.status === 'connected' ? 'Connected' : connector.connectable ? 'Ready' : 'Needs vendor app'}
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                            <Button onClick={next}>Continue</Button>
                        </CardContent>
                    </Card>
                )}

                {step === 6 && (
                    <Card>
                        <CardContent className="space-y-3 p-4">
                            <p className="text-sm">
                                Finishing runs your first technical site audit against{' '}
                                <span className="font-medium">{websiteForm.data.website_url || 'your website (add it in step 2 for the audit)'}</span>{' '}
                                and takes you to the dashboard.
                            </p>
                            <Button onClick={() => router.post(route('onboarding.complete'))}>Finish setup</Button>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
