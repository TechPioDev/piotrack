import { PartialFailure } from '@/components/async-states';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'AI Visibility', href: '/seo/ai-visibility' }];

type Check = {
    id: number;
    prompt: string;
    engine: string;
    brand: string;
    mentioned: boolean;
    position: number | null;
    share_of_answer: number;
    cited_sources: string[];
    competitors: string[];
    checked_at: string | null;
};

type AiSource = { name: string; live: boolean };

type Summary = {
    total: number;
    mentioned: number;
    avg_share: number;
};

type DimensionRecommendation = {
    dimension: string;
    value: string | null;
    evidence: string;
    action: string;
};

type CitationSource = {
    host: string;
    citations: number;
    status: 'covered' | 'gap';
};

function formatTime(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}

export default function AiVisibility({
    engines,
    checks,
    summary,
    aiSource,
    dimension_recommendations,
    citation_sources,
}: {
    engines: string[];
    checks: Check[];
    summary: Summary;
    aiSource?: AiSource;
    dimension_recommendations: DimensionRecommendation[];
    citation_sources: CitationSource[];
}) {
    const { can } = usePermissions();
    const canManage = can('seo.ai.manage');
    const targetSource = (host: string) => router.post(route('seo.ai.target-source'), { host }, { preserveScroll: true });
    const form = useForm<{ prompt: string; brand: string; engine: string }>({
        prompt: '',
        brand: '',
        engine: engines[0] ?? '',
    });

    const run: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('seo.ai.check'), { preserveScroll: true, onSuccess: () => form.reset('prompt', 'brand') });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="AI Visibility" />
            <div className="space-y-6 p-4">
                <Heading title="AI Visibility" description="Track how AI answer engines mention your brand" />

                {aiSource && !aiSource.live && (
                    <PartialFailure
                        failed={['Live AI engine data']}
                        detail={`Results below come from the built-in ${aiSource.name} provider until engine API keys are configured.`}
                    />
                )}

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-muted-foreground text-sm">Total checks</p>
                            <p className="text-2xl font-semibold">{summary.total}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-muted-foreground text-sm">Mentioned</p>
                            <p className="text-2xl font-semibold">{summary.mentioned}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-muted-foreground text-sm">Avg share</p>
                            <p className="text-2xl font-semibold">{summary.avg_share}%</p>
                        </CardContent>
                    </Card>
                </div>

                {canManage && (
                    <Card>
                        <CardContent className="p-4">
                            <form onSubmit={run} className="space-y-3">
                                <div className="grid gap-1">
                                    <Label htmlFor="prompt">Prompt</Label>
                                    <Input
                                        id="prompt"
                                        placeholder="What is the best MSP in Chicago?"
                                        value={form.data.prompt}
                                        onChange={(e) => form.setData('prompt', e.target.value)}
                                    />
                                    <InputError message={form.errors.prompt} />
                                </div>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="grid gap-1">
                                        <Label htmlFor="brand">Brand</Label>
                                        <Input id="brand" value={form.data.brand} onChange={(e) => form.setData('brand', e.target.value)} />
                                        <InputError message={form.errors.brand} />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="engine">Engine</Label>
                                        <Select value={form.data.engine} onValueChange={(v) => form.setData('engine', v)}>
                                            <SelectTrigger id="engine">
                                                <SelectValue placeholder="Select an engine" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {engines.map((engine) => (
                                                    <SelectItem key={engine} value={engine}>
                                                        {engine}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={form.errors.engine} />
                                    </div>
                                </div>
                                <Button type="submit" disabled={form.processing}>
                                    Run visibility check
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {checks.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No checks yet. Run a visibility check to see how engines answer.</p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">Prompt</th>
                                    <th className="p-3 font-medium">Engine</th>
                                    <th className="p-3 font-medium">Brand</th>
                                    <th className="p-3 font-medium">Mentioned</th>
                                    <th className="p-3 font-medium">Share</th>
                                    <th className="p-3 font-medium">Sources</th>
                                    <th className="p-3 font-medium">Competitors</th>
                                    <th className="p-3 font-medium">Checked</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {checks.map((check) => (
                                    <tr key={check.id} className="hover:bg-muted/40">
                                        <td className="p-3">{check.prompt}</td>
                                        <td className="p-3">
                                            <Badge variant="outline">{check.engine}</Badge>
                                        </td>
                                        <td className="p-3">{check.brand}</td>
                                        <td className="p-3">
                                            {check.mentioned ? <Badge>Mentioned</Badge> : <Badge variant="secondary">Not mentioned</Badge>}
                                        </td>
                                        <td className="p-3">{check.share_of_answer}%</td>
                                        <td className="text-muted-foreground p-3">
                                            {check.cited_sources.length > 0 ? check.cited_sources.join(', ') : '—'}
                                        </td>
                                        <td className="text-muted-foreground p-3">
                                            {check.competitors.length > 0 ? check.competitors.join(', ') : '—'}
                                        </td>
                                        <td className="text-muted-foreground p-3">{formatTime(check.checked_at)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <div>
                        <h3 className="mb-1 text-sm font-medium">Dimension recommendations</h3>
                        <p className="text-muted-foreground mb-2 text-xs">
                            City, service and vertical visibility gaps with the platform action that works each one.
                        </p>
                        {dimension_recommendations.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No weak dimensions — every measured city, service and vertical holds up.</p>
                        ) : (
                            <ul className="space-y-2 rounded-lg border p-3 text-sm">
                                {dimension_recommendations.map((rec, index) => (
                                    <li key={index}>
                                        <p className="font-medium">
                                            <Badge variant="outline" className="mr-2">
                                                {rec.dimension}
                                            </Badge>
                                            {rec.value ?? 'not measured'}
                                        </p>
                                        <p className="text-muted-foreground text-xs">{rec.evidence}</p>
                                        <p className="text-xs">{rec.action}</p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div>
                        <h3 className="mb-1 text-sm font-medium">Cited sources</h3>
                        <p className="text-muted-foreground mb-2 text-xs">
                            The hosts AI answers cite. Target a gap to work it through the outreach pipeline — pitch, placement, authority asset.
                        </p>
                        {citation_sources.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No cited sources captured yet. They appear as visibility checks run.</p>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground">
                                        <tr>
                                            <th className="p-3 font-medium">Source</th>
                                            <th className="p-3 text-center font-medium">Citations</th>
                                            <th className="p-3 font-medium">Status</th>
                                            {canManage && <th className="p-3 text-right font-medium">Action</th>}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {citation_sources.map((source) => (
                                            <tr key={source.host} className="hover:bg-muted/40">
                                                <td className="p-3 font-medium break-all">{source.host}</td>
                                                <td className="p-3 text-center">{source.citations}</td>
                                                <td className="p-3">
                                                    <Badge variant={source.status === 'covered' ? 'default' : 'secondary'}>{source.status}</Badge>
                                                </td>
                                                {canManage && (
                                                    <td className="p-3 text-right">
                                                        {source.status === 'gap' && (
                                                            <Button size="sm" variant="outline" onClick={() => targetSource(source.host)}>
                                                                Target
                                                            </Button>
                                                        )}
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
