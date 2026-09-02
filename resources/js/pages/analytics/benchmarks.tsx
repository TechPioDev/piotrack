import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Benchmarks', href: '/analytics/benchmarks' }];

type Benchmark = {
    metric: string;
    cohort: number;
    peer_median: number;
    top_quartile: number;
    peer_average: number;
    your_value: number | null;
    your_percentile: number | null;
};

const METRIC_LABELS: Record<string, string> = {
    cpl: 'Cost per lead',
    conversion_rate: 'Lead to customer rate',
    lead_to_sql: 'Lead to SQL rate',
    sql_to_meeting: 'SQL to meeting rate',
    meeting_to_proposal: 'Meeting to proposal rate',
    proposal_to_win: 'Proposal to win rate',
    avg_mrr: 'Average MRR',
    cac: 'Customer acquisition cost',
    time_to_close: 'Time to close',
};

const MONEY_METRICS = ['cpl', 'cac', 'avg_mrr'];
const PERCENT_METRICS = ['conversion_rate', 'lead_to_sql', 'sql_to_meeting', 'meeting_to_proposal', 'proposal_to_win'];

function label(metric: string): string {
    return METRIC_LABELS[metric] ?? metric.replace(/_/g, ' ');
}

function formatValue(metric: string, value: number): string {
    if (MONEY_METRICS.includes(metric)) return `$${(value / 100).toFixed(2)}`;
    if (PERCENT_METRICS.includes(metric)) return `${value}%`;
    if (metric === 'time_to_close') return `${value} days`;

    return String(value);
}

function percentileVariant(percentile: number): 'default' | 'secondary' {
    return percentile >= 50 ? 'default' : 'secondary';
}

export default function Benchmarks({
    benchmarks,
    metrics,
    min_cohort,
}: {
    benchmarks: Record<string, Benchmark>;
    metrics: string[];
    min_cohort: number;
}) {
    const suppressed = metrics.filter((metric) => !(metric in benchmarks));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Benchmarks" />
            <div className="space-y-6 p-4">
                <Heading title="Benchmarks" description="Anonymized peer benchmarks and where you stand against them" />

                <p className="text-muted-foreground text-sm">
                    Benchmarks are aggregated across organizations and released only when at least {min_cohort}{' '}
                    {min_cohort === 1 ? 'organization contributes' : 'organizations contribute'} data. No single organization&rsquo;s figures are ever
                    exposed.
                </p>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="p-3 font-medium">Metric</th>
                                <th className="p-3 text-center font-medium">Your value</th>
                                <th className="p-3 text-center font-medium">Peer median</th>
                                <th className="p-3 text-center font-medium">Top quartile</th>
                                <th className="p-3 text-center font-medium">Peer average</th>
                                <th className="p-3 text-center font-medium">Percentile</th>
                                <th className="p-3 text-center font-medium">Cohort</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {metrics
                                .filter((metric) => metric in benchmarks)
                                .map((metric) => {
                                    const benchmark = benchmarks[metric];

                                    return (
                                        <tr key={metric} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{label(metric)}</td>
                                            <td className="p-3 text-center">
                                                {benchmark.your_value === null ? (
                                                    <span className="text-muted-foreground">No data</span>
                                                ) : (
                                                    formatValue(metric, benchmark.your_value)
                                                )}
                                            </td>
                                            <td className="text-muted-foreground p-3 text-center">{formatValue(metric, benchmark.peer_median)}</td>
                                            <td className="text-muted-foreground p-3 text-center">{formatValue(metric, benchmark.top_quartile)}</td>
                                            <td className="text-muted-foreground p-3 text-center">{formatValue(metric, benchmark.peer_average)}</td>
                                            <td className="p-3 text-center">
                                                {benchmark.your_percentile === null ? (
                                                    <span className="text-muted-foreground">—</span>
                                                ) : (
                                                    <Badge variant={percentileVariant(benchmark.your_percentile)}>
                                                        {benchmark.your_percentile}th
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="text-muted-foreground p-3 text-center">{benchmark.cohort}</td>
                                        </tr>
                                    );
                                })}
                            {suppressed.map((metric) => (
                                <tr key={metric} className="text-muted-foreground">
                                    <td className="p-3 font-medium">{label(metric)}</td>
                                    <td className="p-3" colSpan={6}>
                                        Withheld to protect anonymity — fewer than {min_cohort} organizations contributed data for this metric.
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {Object.keys(benchmarks).length === 0 && (
                    <p className="text-muted-foreground text-sm">
                        No benchmarks are available yet. They unlock as more organizations contribute enough data to anonymize.
                    </p>
                )}
            </div>
        </AppLayout>
    );
}
