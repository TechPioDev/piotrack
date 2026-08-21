import { OnboardingChecklist } from '@/components/onboarding-checklist';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { CalendarCheck, DollarSign, Handshake, PieChart, TrendingUp, UserPlus, Users } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

type Onboarding = { steps: { key: string; label: string; done: boolean; url: string }[]; complete: boolean };
type Metrics = {
    leads: number;
    sqls: number;
    meetings: number;
    opportunities: number;
    qualified_pipeline: number;
    closed_won: number;
    mrr: number;
    arr: number;
};

/** Minor units (cents) to a compact dollar string: 5400000 -> $54,000. */
function money(minor: number): string {
    const dollars = Math.round(minor / 100);
    if (dollars >= 1000) {
        return `$${(dollars / 1000).toLocaleString('en-US', { maximumFractionDigits: 1 })}K`;
    }
    return `$${dollars.toLocaleString('en-US')}`;
}

export default function Dashboard({ onboarding, metrics, sources }: { onboarding: Onboarding; metrics: Metrics; sources: Record<string, number> }) {
    const sourceRows = Object.entries(sources).sort((a, b) => b[1] - a[1]);
    const sourceTotal = sourceRows.reduce((sum, [, n]) => sum + n, 0);

    // Grow the source bars in from zero on first paint - a small, calm flourish.
    const [grown, setGrown] = useState(false);
    useEffect(() => {
        const id = requestAnimationFrame(() => setGrown(true));
        return () => cancelAnimationFrame(id);
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="space-y-6 p-4">
                <PageHeader title="Dashboard" description="Your growth at a glance — pipeline, revenue, and where leads are coming from." />

                {!onboarding.complete && <OnboardingChecklist onboarding={onboarding} />}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="New Leads" value={metrics.leads.toLocaleString('en-US')} icon={UserPlus} />
                    <StatCard label="SQLs" value={metrics.sqls.toLocaleString('en-US')} icon={Users} />
                    <StatCard label="Meetings" value={metrics.meetings.toLocaleString('en-US')} icon={CalendarCheck} />
                    <StatCard label="Open Opportunities" value={metrics.opportunities.toLocaleString('en-US')} icon={Handshake} />
                    <StatCard label="Qualified Pipeline" value={money(metrics.qualified_pipeline)} icon={TrendingUp} />
                    <StatCard label="Customers Won" value={metrics.closed_won.toLocaleString('en-US')} icon={Handshake} />
                    <StatCard label="New MRR" value={money(metrics.mrr)} icon={DollarSign} />
                    <StatCard label="ARR" value={money(metrics.arr)} icon={DollarSign} />
                </div>

                <div className="border-border bg-card rounded-lg border">
                    <div className="border-border flex items-center gap-2.5 border-b px-4 py-3">
                        <span className="bg-brand-soft text-brand-strong flex size-7 items-center justify-center rounded-lg">
                            <PieChart className="size-4" aria-hidden />
                        </span>
                        <h2 className="text-foreground text-sm font-semibold">Lead Sources</h2>
                        {sourceTotal > 0 && (
                            <span className="text-muted-foreground ml-auto text-xs tabular-nums">{sourceTotal.toLocaleString('en-US')} leads</span>
                        )}
                    </div>
                    {sourceRows.length === 0 ? (
                        <p className="text-muted-foreground px-4 py-6 text-sm">No leads captured yet.</p>
                    ) : (
                        <ul className="divide-border divide-y">
                            {sourceRows.map(([channel, total], i) => {
                                const pct = sourceTotal > 0 ? Math.round((total / sourceTotal) * 100) : 0;
                                return (
                                    <li key={channel} className="hover:bg-muted/40 flex items-center gap-3 px-4 py-2.5 transition-colors">
                                        <span className="bg-brand-soft text-brand-strong flex size-5 shrink-0 items-center justify-center rounded-md text-[11px] font-semibold tabular-nums">
                                            {i + 1}
                                        </span>
                                        <span className="text-foreground w-24 shrink-0 text-sm font-medium capitalize">{channel}</span>
                                        <div className="bg-muted h-2.5 flex-1 overflow-hidden rounded-full">
                                            <div
                                                className="from-brand to-brand-strong h-full rounded-full bg-gradient-to-r transition-[width] duration-700 ease-out motion-reduce:transition-none"
                                                style={{ width: grown ? `${pct}%` : '0%' }}
                                            />
                                        </div>
                                        <span className="text-muted-foreground w-16 shrink-0 text-right text-sm tabular-nums">
                                            {total} · {pct}%
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
