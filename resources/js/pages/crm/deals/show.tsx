import { ActivityTimeline, type Activity } from '@/components/crm/activity-timeline';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Handshake } from 'lucide-react';

type Deal = {
    id: number;
    name: string;
    value: number;
    mrr: number;
    arr: number;
    ltv: number;
    contract_term_months: number | null;
    status: string;
    stage: string | null;
    contact: { id: number; name: string } | null;
    company: { id: number; name: string } | null;
    lead_source: string | null;
    campaign: string | null;
    owner: string | null;
    marketing_owner: string | null;
    expected_close_date: string | null;
};

export default function DealShow({ deal, activities }: { deal: Deal; activities: Activity[] }) {
    const { can } = usePermissions();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Deals', href: '/crm/deals' },
        { title: deal.name, href: `/crm/deals/${deal.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={deal.name} />
            <div className="grid gap-4 p-4 lg:grid-cols-3">
                <div className="space-y-4 lg:col-span-1">
                    <div className="flex items-center gap-3">
                        <span className="bg-brand-soft text-brand-strong flex size-10 shrink-0 items-center justify-center rounded-lg">
                            <Handshake className="size-5" aria-hidden />
                        </span>
                        <Heading title={deal.name} description={`${formatMoney(deal.value)} · ${deal.stage ?? ''}`} />
                        <Badge variant={deal.status === 'won' ? 'default' : deal.status === 'lost' ? 'destructive' : 'secondary'}>
                            {deal.status}
                        </Badge>
                    </div>
                    <Card>
                        <CardContent className="space-y-2 p-4 text-sm">
                            <Row label="MRR" value={deal.mrr ? formatMoney(deal.mrr) : null} />
                            <Row label="ARR" value={deal.arr ? formatMoney(deal.arr) : null} />
                            <Row label="Lifetime value" value={deal.ltv ? formatMoney(deal.ltv) : null} />
                            <Row label="Contract term" value={deal.contract_term_months ? `${deal.contract_term_months} mo` : null} />
                            <Row
                                label="Contact"
                                value={
                                    deal.contact ? (
                                        <Link href={route('crm.contacts.show', deal.contact.id)} className="hover:text-brand-strong hover:underline">
                                            {deal.contact.name}
                                        </Link>
                                    ) : null
                                }
                            />
                            <Row
                                label="Company"
                                value={
                                    deal.company ? (
                                        <Link href={route('crm.companies.show', deal.company.id)} className="hover:text-brand-strong hover:underline">
                                            {deal.company.name}
                                        </Link>
                                    ) : null
                                }
                            />
                            <Row label="Lead source" value={deal.lead_source} />
                            <Row label="Campaign" value={deal.campaign} />
                            <Row label="Owner" value={deal.owner} />
                            <Row label="Marketing owner" value={deal.marketing_owner} />
                            <Row
                                label="Expected close"
                                value={deal.expected_close_date ? new Date(deal.expected_close_date).toLocaleDateString() : null}
                            />
                        </CardContent>
                    </Card>
                </div>

                <div className="lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">Activity</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ActivityTimeline subjectType="deal" subjectId={deal.id} activities={activities} canManage={can('crm.activity.manage')} />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

function Row({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex justify-between gap-4">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right">{value ?? '—'}</span>
        </div>
    );
}
