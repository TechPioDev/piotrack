import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'SEO', href: '/seo' },
    { title: 'Search Health', href: '/seo/health' },
];

type SearchConsole = {
    provider: string;
    performance: { query: string; clicks: number; impressions: number; ctr: number; position: number }[];
    coverage: { indexed: number; issues: { type: string; count: number; example: string }[] } | null;
    manual_actions: { type: string; reason: string }[];
};

type Finding = { key: string; label: string; status: string; evidence: string; source: string };

type Recovery = { needed: boolean; steps: { step: string; reason: string; tool: string }[] };

type Cwv = {
    url: string;
    error: string | null;
    lab?: {
        checks: { key: string; label: string; metric: string; status: string; detail: string; fix: string }[];
        issues: number;
        by_metric: Record<string, number>;
    };
    field?: { lcp_ms: number; cls: number; inp_ms: number; verdicts: Record<string, string> };
} | null;

const statusBadge = (status: string) =>
    status === 'ok' || status === 'pass' || status === 'good'
        ? 'secondary'
        : status === 'risk' || status === 'fail' || status === 'poor'
          ? 'destructive'
          : 'outline';

export default function Health({
    domain,
    search_console,
    penalty,
    recovery,
    vitals_provider,
    cwv,
}: {
    domain: string | null;
    search_console: SearchConsole;
    penalty: { findings: Finding[]; risk: number };
    recovery: Recovery;
    vitals_provider: string;
    cwv: Cwv;
}) {
    const [cwvUrl, setCwvUrl] = useState(cwv?.url ?? '');

    const runCwv: FormEventHandler = (e) => {
        e.preventDefault();
        if (cwvUrl.trim() !== '') {
            router.get(route('seo.health'), { cwv_url: cwvUrl.trim() }, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Search Health" />
            <div className="space-y-6 p-4">
                <Heading
                    title="Search health"
                    description="Search Console monitoring, Core Web Vitals, and the penalty audit — first-party signals are real; provider data carries its driver's name."
                />

                {domain === null && (
                    <p className="text-muted-foreground text-sm">
                        Set your website URL on the brand profile (SEO → LLMO) — monitoring needs to know which domain is yours.
                    </p>
                )}

                {/* TSEO-023: Search Console monitoring through the seam */}
                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">Search Console</h2>
                    {search_console.provider === 'fixture' && (
                        <p className="text-muted-foreground rounded-lg border border-dashed p-3 text-sm">
                            Search Console data below is <strong>simulated by the fixture driver</strong> — connect the live Google Search Console
                            integration to replace it with your real property data.
                        </p>
                    )}
                    {search_console.manual_actions.length > 0 && (
                        <div className="rounded-lg border border-red-300 p-3 text-sm">
                            <p className="font-medium">Manual actions reported</p>
                            {search_console.manual_actions.map((action) => (
                                <p key={action.type} className="text-muted-foreground">
                                    {action.type} — {action.reason}
                                </p>
                            ))}
                        </div>
                    )}
                    <div className="grid gap-3 lg:grid-cols-3">
                        <div className="overflow-x-auto rounded-lg border lg:col-span-2">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Query</th>
                                        <th className="p-3 text-right font-medium">Clicks</th>
                                        <th className="p-3 text-right font-medium">Impressions</th>
                                        <th className="p-3 text-right font-medium">CTR</th>
                                        <th className="p-3 text-right font-medium">Position</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {search_console.performance.map((row) => (
                                        <tr key={row.query}>
                                            <td className="p-3 font-medium">{row.query}</td>
                                            <td className="p-3 text-right tabular-nums">{row.clicks}</td>
                                            <td className="p-3 text-right tabular-nums">{row.impressions}</td>
                                            <td className="p-3 text-right tabular-nums">{row.ctr}%</td>
                                            <td className="p-3 text-right tabular-nums">{row.position}</td>
                                        </tr>
                                    ))}
                                    {search_console.performance.length === 0 && (
                                        <tr>
                                            <td colSpan={5} className="text-muted-foreground p-3 text-sm">
                                                No performance rows yet.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-base">Index coverage</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                {search_console.coverage === null ? (
                                    <p className="text-muted-foreground">No domain configured.</p>
                                ) : (
                                    <>
                                        <p>
                                            <span className="text-2xl font-semibold tabular-nums">{search_console.coverage.indexed}</span>{' '}
                                            <span className="text-muted-foreground">pages indexed</span>
                                        </p>
                                        {search_console.coverage.issues.map((issue) => (
                                            <p key={issue.type} className="text-muted-foreground">
                                                {issue.type} · <span className="tabular-nums">{issue.count}</span>{' '}
                                                <span className="text-xs">(e.g. {issue.example})</span>
                                            </p>
                                        ))}
                                        {search_console.coverage.issues.length === 0 && <p className="text-muted-foreground">No coverage issues.</p>}
                                    </>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </section>

                {/* TSEO-019: CWV — lab factors first-party, field data via the seam */}
                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">Core Web Vitals</h2>
                    <form onSubmit={runCwv} className="flex flex-wrap items-end gap-2">
                        <div className="grid min-w-72 flex-1 gap-1">
                            <Label htmlFor="cwv-url">Page URL</Label>
                            <Input id="cwv-url" value={cwvUrl} onChange={(e) => setCwvUrl(e.target.value)} placeholder="https://your-site.com/" />
                        </div>
                        <Button type="submit">Audit</Button>
                    </form>
                    {cwv?.error && <p className="text-sm text-red-600 dark:text-red-400">{cwv.error}</p>}
                    {cwv?.lab && (
                        <div className="grid gap-3 lg:grid-cols-3">
                            <div className="overflow-x-auto rounded-lg border lg:col-span-2">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground">
                                        <tr>
                                            <th className="p-3 font-medium">Lab check</th>
                                            <th className="p-3 font-medium">Metric</th>
                                            <th className="p-3 font-medium">Result</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {cwv.lab.checks.map((check) => (
                                            <tr key={check.key}>
                                                <td className="p-3 font-medium">{check.label}</td>
                                                <td className="p-3">
                                                    <Badge variant="outline">{check.metric}</Badge>
                                                </td>
                                                <td className="p-3">
                                                    <span className="flex flex-wrap items-center gap-1">
                                                        <Badge variant={statusBadge(check.status)}>{check.status}</Badge>
                                                        <span className="text-muted-foreground text-xs">
                                                            {check.detail}
                                                            {check.fix && ` — ${check.fix}`}
                                                        </span>
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-base">Field data</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2 text-sm">
                                    {vitals_provider === 'fixture' && (
                                        <p className="text-muted-foreground text-xs">
                                            Field metrics are <strong>simulated by the fixture driver</strong> — connect PageSpeed Insights for real
                                            CrUX data.
                                        </p>
                                    )}
                                    {cwv.field && (
                                        <>
                                            <p className="flex items-center justify-between">
                                                <span>LCP {(cwv.field.lcp_ms / 1000).toFixed(1)}s</span>
                                                <Badge variant={statusBadge(cwv.field.verdicts.lcp)}>{cwv.field.verdicts.lcp}</Badge>
                                            </p>
                                            <p className="flex items-center justify-between">
                                                <span>CLS {cwv.field.cls}</span>
                                                <Badge variant={statusBadge(cwv.field.verdicts.cls)}>{cwv.field.verdicts.cls}</Badge>
                                            </p>
                                            <p className="flex items-center justify-between">
                                                <span>INP {cwv.field.inp_ms}ms</span>
                                                <Badge variant={statusBadge(cwv.field.verdicts.inp)}>{cwv.field.verdicts.inp}</Badge>
                                            </p>
                                        </>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    )}
                </section>

                {/* TSEO-024/025: penalty audit + recovery plan */}
                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">Penalty audit</h2>
                    <div className="space-y-2">
                        {penalty.findings.map((finding) => (
                            <Card key={finding.key}>
                                <CardContent className="flex items-start justify-between gap-3 p-4">
                                    <div>
                                        <p className="font-medium">{finding.label}</p>
                                        <p className="text-muted-foreground text-sm">{finding.evidence}</p>
                                        <p className="text-muted-foreground text-xs">source: {finding.source}</p>
                                    </div>
                                    <Badge variant={statusBadge(finding.status)}>{finding.status}</Badge>
                                </CardContent>
                            </Card>
                        ))}
                    </div>

                    <h3 className="text-base font-semibold">Recovery plan</h3>
                    {!recovery.needed && (
                        <p className="text-muted-foreground text-sm">No recovery needed — every audit signal is within its threshold.</p>
                    )}
                    {recovery.steps.map((step, i) => (
                        <Card key={i}>
                            <CardContent className="p-4">
                                <p className="font-medium">
                                    {i + 1}. {step.step}
                                </p>
                                <p className="text-muted-foreground text-sm">{step.reason}</p>
                                <p className="text-muted-foreground text-xs">tool: {step.tool}</p>
                            </CardContent>
                        </Card>
                    ))}
                </section>
            </div>
        </AppLayout>
    );
}
